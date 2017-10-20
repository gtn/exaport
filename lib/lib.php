<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/filelib.php';

if (block_exaport_check_competence_interaction()) {
	// TODO: don't use any of the exacomp functions, use \block_exacomp\api::method() instead!
	if (file_exists($CFG->dirroot.'/blocks/exacomp/lib/lib.php')) {
		require_once $CFG->dirroot.'/blocks/exacomp/lib/lib.php';
	} else {
		require_once $CFG->dirroot.'/blocks/exacomp/lib/div.php';
	}
}

require_once __DIR__.'/common.php';

use block_exaport\globals as g;

require_once __DIR__.'/lib.exaport.php';
require_once __DIR__.'/sharelib.php';
/*** FILE FUNCTIONS **********************************************************************/

/**
 * @param $item
 * @param $type
 * @return stored_file
 */
function block_exaport_get_file($item, $type) {
	$fs = get_file_storage();
	$files = $fs->get_area_files(context_user::instance($item->userid)->id, 'block_exaport', $type, $item->id, null, false);

	// return first file
	return reset($files);
}

function block_exaport_get_item_file($item) {
	return block_exaport_get_file($item, 'item_file');
}

function block_exaport_get_category_icon($category) {
	$fs = get_file_storage();

	$file = current($fs->get_area_files(context_user::instance($category->userid)->id, 'block_exaport', 'category_icon', $category->id, 'itemid', false));
	if ($file) {
		// hack, this logic doesn't work for other users for now
		if ($category->userid !== g::$USER->id) {
			return;
		}

		return g::$CFG->wwwroot.'/pluginfile.php/'.$file->get_contextid().'/block_exaport/category_icon/'.$file->get_itemid().'/'.$file->get_filename();
	} else {
		return null;
	}
}


/**
 * @param $itemcomment
 * @return stored_file
 * @throws dml_exception
 */
function block_exaport_get_item_comment_file($commentid) {
	$fs = get_file_storage();

	// list all files, excluding directories!
	$areafiles = $fs->get_area_files(context_system::instance()->id, 'block_exaport', 'item_comment_file', $commentid, null, false);

	if (empty($areafiles)) {
		return null;
	} else {
		return reset($areafiles);
	}
}

/**
 * wrote own function, so eclipse knows which type the output renderer is
 * @return block_exaport_renderer
 */
function block_exaport_get_renderer() {
	global $PAGE;

	return $PAGE->get_renderer('block_exaport');
}

function block_exaport_add_to_log($courseid, $module, $action, $url = '', $info = '', $cm = 0, $user = 0) {
	if (!function_exists('get_log_manager')) {
		// old style
		return add_to_log($courseid, $module, $action, $url = '', $info = '', $cm = 0, $user = 0);
	}

	// hack for new style

	// This is a nasty hack that allows us to put all the legacy stuff into legacy storage,
	// this way we may move all the legacy settings there too.
	$manager = get_log_manager();
	if (method_exists($manager, 'legacy_add_to_log')) {
		$manager->legacy_add_to_log($courseid, $module, $action, $url, $info, $cm, $user);
	}
}

function block_exaport_file_remove($item) {
	$fs = get_file_storage();
	// associated file (if it's a file item)
	$fs->delete_area_files(context_user::instance($item->userid)->id, 'block_exaport', 'item_file', $item->id);
	// item content (intro) inside the html editor
	$fs->delete_area_files(context_user::instance($item->userid)->id, 'block_exaport', 'item_content', $item->id);
}

/*** GENERAL FUNCTIONS **********************************************************************/

function block_exaport_require_login($courseid) {
	global $CFG;

	require_login($courseid);
	require_capability('block/exaport:use', context_system::instance());

	if (empty($CFG->block_exaport_allow_loginas)) {
		// login as not allowed => check
		global $USER;
		if (!empty($USER->realuser)) {
			print_error("loginasmode", "block_exaport");
		}
	}
}

function block_exaport_shareall_enabled() {
	global $CFG;

	return empty($CFG->block_exaport_disable_shareall);
}

function block_exaport_external_comments_enabled() {
	global $CFG;

	return empty($CFG->block_exaport_disable_external_comments);
}

function block_exaport_setup_default_categories() {
	global $DB, $USER, $CFG;
	if (block_exaport_course_has_desp() && !$DB->record_exists('block_exaportcate', array('userid' => $USER->id))
		&& !empty($CFG->block_exaport_create_desp_categories)
	) {
		block_exaport_import_categories("desp_categories");
	}
}

function block_exaport_import_categories($categoriesSTR) {
	global $DB, $USER;
	$categories = trim(get_string($categoriesSTR, "block_exaport"));

	if (!$categories) {
		return;
	}

	$categories = explode("\n", $categories);
	$categories = array_map('trim', $categories);

	$newentry = new stdClass();
	$newentry->timemodified = time();
	$newentry->userid = $USER->id;
	$newentry->pid = 0;

	$lastMainId = null;
	foreach ($categories as $category) {

		if ($category[0] == '-' && $lastMainId) {
			// subcategory
			$newentry->name = trim($category, '-');
			$newentry->pid = $lastMainId;
			//$categoryDB = $DB->get_records('block_exaportcate', array("name"=>trim($category,'-')));
			if (!$DB->record_exists('block_exaportcate', array("name" => trim($category, '-')))) {
				$DB->insert_record("block_exaportcate", $newentry);
			}
		} else {
			$newentry->name = $category;
			$newentry->pid = 0;
			//$categoryDB = $DB->get_records('block_exaportcate', array("name"=>$category));
			if (!$DB->record_exists('block_exaportcate', array("name" => $category))) {
				$lastMainId = $DB->insert_record("block_exaportcate", $newentry);
			} else {
				$lastMainId = $DB->get_field('block_exaportcate', 'id', array("name" => $category));
			}
		}
	}
}

function block_exaport_feature_enabled($feature) {
	global $CFG;
	if ($feature == 'views') {
		return true;
	}
	if ($feature == 'copy_to_course') {
		return !empty($CFG->block_exaport_feature_copy_to_course);
	}
	die('wrong feature');
}

/*** OTHER FUNCTIONS **********************************************************************/

function block_exaport_has_categories($userid) {
	global $CFG, $DB;
	if ($DB->count_records_sql("SELECT COUNT(*) FROM {block_exaportcate} WHERE userid=? AND pid=0", array($userid)) > 0) {
		return true;
	} else {
		return false;
	}
}

function block_exaport_moodleimport_file_area_name($userid, $assignmentid, $courseid) {
	global $CFG;

	return $courseid.'/'.$CFG->moddata.'/assignment/'.$assignmentid.'/'.$userid;
}

function block_exaport_print_file(stored_file $file) {
	global $CFG, $OUTPUT;

	$url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());

	$icon = new pix_icon(file_mimetype_icon($file->get_mimetype()), '');
	if ($file->is_valid_image()) {
		return "<img src=\"$url\" alt=\"".s($file->get_filename())."\" />";
	} else {
		$icon = $OUTPUT->pix_icon(file_file_icon($file), '');
		return '<p>'.$icon.' '.$OUTPUT->action_link($url, $file->get_filename())."</p>";
	}
}

function block_exaport_course_has_desp() {
	global $COURSE, $DB;

	if (isset($COURSE->has_desp)) {
		return $COURSE->has_desp;
	}

	// desp block installed?
	if (!is_dir(__DIR__.'/../../desp')) {
		return $COURSE->has_desp = false;
	}

	$context = context_course::instance($COURSE->id);

	return $COURSE->has_desp = $DB->record_exists('block_instances', array('blockname' => 'desp', 'parentcontextid' => $context->id));
}

function block_exaport_wrapperdivstart() {
	return html_writer::start_tag('div', array('id' => 'exaport'));
}

function block_exaport_wrapperdivend() {
	return html_writer::end_tag('div');
}

function block_exaport_init_js_css() {
	global $PAGE, $CFG;

	// only allowed to be called once
	static $js_inited = false;
	if ($js_inited) {
		return;
	}
	$js_inited = true;

	// $PAGE->requires->css('/blocks/exaport/css/jquery-ui.css');

	$PAGE->requires->jquery();
	$PAGE->requires->jquery_plugin('ui');
	$PAGE->requires->jquery_plugin('ui-css');

	//$PAGE->requires->js('/blocks/exaport/javascript/jquery.json.js', true);
	//$PAGE->requires->js_call_amd('block_exaport/json', 'initialise');

	$PAGE->requires->js('/blocks/exaport/javascript/exaport.js', true);

	$PAGE->requires->css('/blocks/exaport/css/styles.css');


	$scriptName = preg_replace('!\.[^\.]+$!', '', basename($_SERVER['PHP_SELF']));
	if (file_exists($CFG->dirroot.'/blocks/exaport/css/'.$scriptName.'.css')) {
		$PAGE->requires->css('/blocks/exaport/css/'.$scriptName.'.css');
	}
	if (file_exists($CFG->dirroot.'/blocks/exaport/javascript/'.$scriptName.'.js')) {
		$PAGE->requires->js('/blocks/exaport/javascript/'.$scriptName.'.js', true);
	}

}

/**
 * Print moodle header
 * @param string $item_identifier translation-id for this page
 * @param string $sub_item_identifier translation-id for second level if needed
 */
function block_exaport_print_header($item_identifier, $sub_item_identifier = null) {

	if (!is_string($item_identifier)) {
		throw new moodle_exception('not supported');
	}

	global $CFG, $COURSE, $PAGE;

	block_exaport_init_js_css();

	// navigationspfad
	$navlinks = array();
	$navlinks[] = array('name' => block_exaport_get_string("blocktitle"), 'link' => "view.php?courseid=".$COURSE->id, 'type' => 'title');
	$nav_item_identifier = $item_identifier;

	$icon = $item_identifier;
	$currenttab = $item_identifier;

	// haupttabs
	$tabs = array();

	if (block_exaport_course_has_desp()) {
		$tabs['back'] = new tabobject('back', $CFG->wwwroot.'/blocks/desp/index.php?courseid='.$COURSE->id, get_string("back_to_desp", "block_exaport"), '', true);
	}

	$tabs['resume_my'] = new tabobject('resume_my', $CFG->wwwroot.'/blocks/exaport/view.php?courseid='.$COURSE->id, get_string("resume_my", "block_exaport"), '', true);
	// $tabs[] = new tabobject('categories', $CFG->wwwroot . '/blocks/exaport/view_categories.php?courseid=' . $COURSE->id, get_string("categories", "block_exaport"), '', true);
	$tabs['myportfolio'] = new tabobject('myportfolio', $CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$COURSE->id, block_exaport_get_string("myportfolio"), '', true);
	$tabs['views'] = new tabobject('views', $CFG->wwwroot.'/blocks/exaport/views_list.php?courseid='.$COURSE->id, get_string("views", "block_exaport"), '', true);
	$tabs['shared_views'] = new tabobject('shared_views', $CFG->wwwroot.'/blocks/exaport/shared_views.php?courseid='.$COURSE->id, block_exaport_get_string("shared_views"), '', true);
	$tabs['shared_categories'] = new tabobject('shared_categories', $CFG->wwwroot.'/blocks/exaport/shared_categories.php?courseid='.$COURSE->id, block_exaport_get_string("shared_categories"), '', true);
	$tabs['importexport'] = new tabobject('importexport', $CFG->wwwroot.'/blocks/exaport/importexport.php?courseid='.$COURSE->id, get_string("importexport", "block_exaport"), '', true);

	$tab_item_identifier = preg_replace('!_.*!', '', $item_identifier);
	$tab_sub_item_identifier = preg_replace('!_.*!', '', $sub_item_identifier);

	if (strpos($tab_item_identifier, 'bookmarks') === 0) {
		$tab_item_identifier = 'myportfolio';
	}

	// kind of hacked here, find another solution
	if ($tab_item_identifier == 'views') {
		$id = optional_param('id', 0, PARAM_INT);
		if ($id > 0) {
			$tabs['views']->subtree[] = new tabobject('title', s($CFG->wwwroot.'/blocks/exaport/views_mod.php?courseid='.$COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=title&action=edit'), get_string("viewtitle", "block_exaport"), '', true);
			$tabs['views']->subtree[] = new tabobject('layout', s($CFG->wwwroot.'/blocks/exaport/views_mod.php?courseid='.$COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=layout&action=edit'), get_string("viewlayout", "block_exaport"), '', true);
			$tabs['views']->subtree[] = new tabobject('content', s($CFG->wwwroot.'/blocks/exaport/views_mod.php?courseid='.$COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&action=edit'), get_string("viewcontent", "block_exaport"), '', true);
			if (has_capability('block/exaport:shareextern', context_system::instance()) || has_capability('block/exaport:shareintern', context_system::instance())) {
				$tabs['views']->subtree[] = new tabobject('share', s($CFG->wwwroot.'/blocks/exaport/views_mod.php?courseid='.$COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=share&action=edit'), get_string("viewshare", "block_exaport"), '', true);
			}
		}
	}

	$tabtree = new tabtree($tabs, $currenttab);
	if ($tab_sub_item_identifier && $tabobj = $tabtree->find($tab_sub_item_identifier)) {
		// overwrite active and selected
		$tabobj->active = true;
		$tabobj->selected = true;
	}
	if ($tabobj = $tabtree->find($tab_item_identifier)) {
		// overwrite active and selected
		$tabobj->active = true;
		$tabobj->selected = true;
	}

	$item_name = get_string($nav_item_identifier, "block_exaport");
	if ($item_name[0] == '[') {
		$item_name = get_string($nav_item_identifier);
	}
	$navlinks[] = array('name' => $item_name, 'link' => null, 'type' => 'misc');

	//$navigation = build_navigation($navlinks);
	foreach ($navlinks as $navlink) {
		$PAGE->navbar->add($navlink["name"], $navlink["link"]);
	}

	$PAGE->set_title($item_name);
	$PAGE->set_heading(get_string(block_exaport_course_has_desp() ? "desp_pluginname" : 'pluginname', "block_exaport"));

	// header
	global $OUTPUT;

	echo $OUTPUT->header();
	echo block_exaport_wrapperdivstart();
	if (block_exaport_course_has_desp()) {
		// include the desp css
		echo '<link href="'.$CFG->wwwroot.'/blocks/desp/styles.css" rel="stylesheet" type="text/css" />';
	}

	echo $OUTPUT->render($tabtree);

	if (block_exaport_course_has_desp() && (strpos($currenttab, 'myportfolio') === 0)) {
		echo '<div id="messageboxses1"';
		//if (file_exists("../desp/images/message_ses1.gif")){ echo ' style="min-height:145px; background: url(\'../desp/images/message_ses1.gif\') no-repeat left top; "';}
		echo '>
			<div id="messagetxtses1">
				'.get_string("desp_einleitung", "block_exaport").'
			</div>
		</div>

		<br /><br />';
	}
}

function block_exaport_get_string($string, $param = null) {
	$manager = get_string_manager();

	if (block_exaport_course_has_desp() && $manager->string_exists('desp_'.$string, 'block_exaport')) {
		return $manager->get_string('desp_'.$string, 'block_exaport', $param);
	}

	if ($manager->string_exists($string, "block_exaport")) {
		return $manager->get_string($string, 'block_exaport', $param);
	}

	return $manager->get_string($string, '', $param);
}

function todo_string($string) {
	$manager = get_string_manager();

	if ($manager->string_exists($string, "block_exaport")) {
		return $manager->get_string($string, 'block_exaport');
	}

	return '[['.$string.']]';
}

function block_exaport_print_footer() {
	echo g::$OUTPUT->footer();
}

/**
 * Parse user submitted item_type and return a correct type
 * @param string $type
 * @param boolean $all_allowd Is the type 'all' allowed? E.g. for Item-List
 * @return string correct type
 */
function block_exaport_check_item_type($type, $all_allowed) {
	if (in_array($type, Array('link', 'file', 'note'))) {
		return $type;
	} else {
		return $all_allowed ? 'all' : false;
	}
}

/**
 * Convert item type to plural
 * @param string $type
 * @return string Plural. E.g. file->files, note->notes, all->all (has no plural)
 */
function block_exaport_get_plural_item_type($type) {
	return $type == 'all' ? $type : $type.'s';
}

/**
 * Parse user submitted item sorting and check if allowed/available!
 * @param $sort the sorting in a format like "category.desc"
 * @return Array(sortcolumn, asc|desc)
 */
function block_exaport_parse_sort($sort, array $allowedSorts, array $defaultSort = null) {
	if (!is_array($sort)) {
		$sort = explode('.', $sort);
	}

	$column = $sort[0];
	$order = isset($sort[1]) ? $sort[1] : '';

	if (!in_array($column, $allowedSorts)) {
		if ($defaultSort) {
			return $defaultSort;
		} else {
			return array(reset($allowedSorts), 'asc');
		}
	}

	// sortorder never desc allowed!
	if ($column == 'sortorder') {
		return array($column, 'asc');
	}

	if ($order != "desc") {
		$order = "asc";
	}

	return array($column, $order);
}

function block_exaport_parse_item_sort($sort, $catsortallowed) {
	if ($catsortallowed) {
		return block_exaport_parse_sort($sort, array('date', 'name', 'category', 'type'), array('date', 'desc'));
	}

	return block_exaport_parse_sort($sort, array('date', 'name', 'type'), array('date', 'desc'));
}

function block_exaport_item_sort_to_sql($sort, $catsortallowed) {
	$sort = block_exaport_parse_item_sort($sort, $catsortallowed);

	$column = $sort[0];
	$order = $sort[1];

	if ($column == "date") {
		$sql_sort = "i.timemodified ".$order;
	} elseif ($column == "category") {
		$sql_sort = "cname ".$order.", i.timemodified";
	} else {
		$sql_sort = "i.".$column." ".$order.", i.timemodified";
	}

	return ' order by '.$sql_sort;
}

function block_exaport_parse_view_sort($sort, $for_shared = false) {
	return block_exaport_parse_sort($sort, array('name', 'timemodified'));
}

function block_exaport_view_sort_to_sql($sort) {
	global $CFG;
	$sort = block_exaport_parse_view_sort($sort);

	$column = $sort[0];
	$order = $sort[1];

	if ((strcmp($column, "name") == 0) && (strcmp($CFG->dbtype, "sqlsrv") == 0)) {
		$sql_sort = "cast(v.".$column." AS varchar(max)) ".$order.", v.timemodified DESC";
	} else {
		if ((strcmp($column, "timemodified") == 0) && (strcmp($CFG->dbtype, "sqlsrv") == 0)) {
			$sql_sort = "v.timemodified DESC";
		} else {
			$sql_sort = "v.".$column." ".$order.", v.timemodified DESC";
		}
	}

	return ' order by '.$sql_sort;
}

function block_exaport_get_user_preferences_record($userid = null) {
	global $DB;

	if (is_null($userid)) {
		global $USER;
		$userid = $USER->id;
	}
	$conditions = array("user_id" => $userid);

	return $DB->get_record('block_exaportuser', $conditions);
}

function block_exaport_get_user_preferences($userid = null) {
	global $DB;
	if (is_null($userid)) {
		global $USER;
		$userid = $USER->id;
	}

	$userpreferences = block_exaport_get_user_preferences_record($userid);

	if (!$userpreferences || !$userpreferences->user_hash) {
		do {
			$hash = substr(md5(uniqid(rand(), true)), 3, 8);
		} while ($DB->record_exists("block_exaportuser", array("user_hash" => $hash)));

		block_exaport_set_user_preferences($userid, array('user_hash' => $hash, 'description' => ''));

		$userpreferences = block_exaport_get_user_preferences_record($userid);
	}

	return $userpreferences;
}

function block_exaport_check_competence_interaction() {
	global $CFG;

	return !empty($CFG->block_exaport_enable_interaction_competences) &&
	class_exists('\block_exacomp\api') && \block_exacomp\api::active();
}

function block_exaport_build_comp_table($item, $role = "teacher") {
	global $DB;

	// TODO: refactor: use block_exaport_get_active_comps_for_item instead
	$sql = "SELECT CONCAT(CONCAT(da.id,'_'),d.id) as uniquid,d.title, d.id FROM {block_exacompdescriptors} d, {block_exacompcompactiv_mm} da WHERE d.id=da.compid AND da.eportfolioitem=1 AND da.activityid=?";
	$descriptors = $DB->get_records_sql($sql, array($item->id));
	$content = "<table class='compstable flexible boxaligncenter generaltable'>
				<tr><td><h2>".$item->name."</h2></td></tr>";

	if ($role == "teacher") {
		$dis_teacher = " ";
		$dis_student = " disabled ";
	} else {
		$dis_teacher = " disabled ";
		$dis_student = " ";
	}

	$trclass = "even";
	foreach ($descriptors as $descriptor) {
		if ($trclass == "even") {
			$trclass = "odd";
			$bgcolor = ' style="background-color:#efefef" ';
		} else {
			$trclass = "even";
			$bgcolor = ' style="background-color:#ffffff" ';
		}
		$content .= '<tr '.$bgcolor.'><td>'.$descriptor->title.'</td></tr>';
		/*<td>
			<input'.$dis_teacher.'type="checkbox" name="data[' . $descriptor->id . ']" checked="###checked' . $descriptor->id . '###" />
			</td>
			<td><input'.$dis_student.'type="checkbox" name="eval[' . $descriptor->id . ']" checked="###eval' . $descriptor->id . '###" /></td></tr>';*/
	}
	//$content .= "</table><input type='submit' value='" . get_string("auswahl_speichern", "block_exais_competences") . "' /></form>";
	$content .= '</table>';
	//get teacher comps
	/*
	$competences = block_exaport_get_competences($item, 1);
	foreach ($competences as $competence) {
			$content = str_replace('###checked' . $competence->descid . '###', 'checked', $content);
		}
	$content = preg_replace('/checked="###checked([0-9_])+###"/', '', $content);
	//get student comps
	$competences = block_exaport_get_competences($item, 0);
	foreach ($competences as $competence) {
			$content = str_replace('###eval' . $competence->descid . '###', 'checked', $content);
		}
	$content = preg_replace('/checked="###eval([0-9_])+###"/', '', $content);
	*/
	echo $content;
}

function block_exaport_set_competences($values, $item, $reviewerid, $role = 1) {
	global $DB;

	$DB->delete_records('block_exacompcompuser_mm', array("activityid" => $item->id, "eportfolioitem" => 1, "role" => $role, "userid" => $item->userid));

	foreach ($values as $value) {
		$data = array(
			"activityid" => $item->id,
			"eportfolioitem" => 1,
			"compid" => $value,
			"userid" => $item->userid,
			"reviewerid" => $reviewerid,
			"role" => $role,
		);
		print_r($data);
		$DB->insert_record('block_exacompcompuser_mm', $data);
	}
}

/**
 * @deprecated refactor to use block_exaport_get_active_comps_for_item
 */
function block_exaport_get_active_compids_for_item($item) {
	$ids = array_keys(block_exaport_get_active_comps_for_item($item));

	return array_combine($ids, $ids);
}

function block_exaport_check_item_competences($item) {
	return (bool)block_exaport_get_active_comps_for_item($item);
}

function block_exaport_get_active_comps_for_item($item) {
	return \block_exacomp\api::get_active_comps_for_exaport_item($item->id);
}

function block_exaport_build_comp_tree($type, $item_or_resume, $allowEdit = true) {
	global $CFG, $USER;

	if ($type == 'skillscomp' || $type == 'goalscomp') {
		$forresume = true;
		$resume = $item_or_resume;
		$item = null;
		$active_descriptors = $resume->descriptors;
	} elseif ($type == 'item') {
		$forresume = false;
		$resume = null;
		$item = $item_or_resume;
		$active_descriptors = isset($item->compids_array) ? $item->compids_array : [];
	} else {
		throw new \block_exaport\moodle_exception("wrong \$type: $type");
	}

	if ($forresume) {
		$content = '<form id="treeform" method="post" action="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$resume->courseid.'&id='.$resume->id.'&sesskey='.sesskey().'#'.$type.'">';
	} else {
		$content = '<form id="treeform">';
	}

	$print_tree = function($items, $level = 0) use (&$print_tree, $forresume, $active_descriptors, $allowEdit) {
		if (!$items) {
			return '';
		}

		$content = '';
		if ($level == 0) {
			$content .= '<ul id="comptree" class="treeview">';
		} else {
			$content .= '<ul>';
		}

		foreach ($items as $item) {
			if ($item instanceof \block_exacomp\descriptor && in_array($item->id, $active_descriptors)) {
				$checked = 'checked="checked"';
			} else {
				$checked = '';
			}

			$content .= '<li>';
			if ($item instanceof \block_exacomp\descriptor) {
				$content .= '<input type="checkbox" name="desc'.($forresume ? '[]' : '').'" '.$checked.' value="'.$item->id.'" '.(!$allowEdit ? 'disabled="disabled"' : '').'>';
			}
			$content .=
				$item->title.
				($item->achieved ? ' '.g::$OUTPUT->pix_icon("i/badge", block_exaport\trans(['de:Erreichte Kompetenz', 'en:Achieved Competency'])) : '').
				$print_tree($item->get_subs(), $level + 1).
				'</li>';
		}

		$content .= '</ul>';

		return $content;
	};

	$compTree = \block_exacomp\api::get_comp_tree_for_exaport($USER->id);

	// no filtering
	/*
	if ($compTree && $forresume) {
		$filter_tree = function($item) use (&$filter_tree, $forresume, $active_descriptors) {
			$item->set_subs(array_filter($item->get_subs(), $filter_tree));

			if ($item instanceof \block_exacomp\descriptor) {
				// achieved, or children achieved
				return ($item->get_subs() || $item->achieved);
			} else {
				return !!$item->get_subs();
			}
		};
		$compTree = array_filter($compTree, $filter_tree);
	}
	*/

	/*
	if (!$compTree) {
		$content .= '<div><h4 style="text-align:center; padding: 40px;">'.block_exaport\trans(['de:Eine Kurse hat leider keine Kompetenzen für den Kurs aktiviert', "en:"]).'</h4></div>';
	} else {
		$content .= $print_tree($compTree);
	}
	*/
	$content .= $print_tree($compTree);

	if ($forresume) {
		$content .= '<input type="hidden" value="edit" name="action">';
		$content .= '<input type="hidden" value="'.$type.'" name="type">';
		$content .= '<input type="hidden" value="'.sesskey().'" name="sesskey">';
		$content .= '<input type="submit" id="id_submitbutton" type="submit" value="'.get_string('savechanges').'" name="submitbutton">';
		$content .= '<input type="submit" id="id_cancel" class="btn-cancel" onclick="skipClientValidation = true; return true;" value="'.get_string('cancel').'" name="cancel">';
	} else {
		$content .= '<input type="button" id="id_submitbutton2" value="'.get_string('savechanges').'" name="savecompetencesbutton" onClick="jQueryExaport.colorbox.close();">';
	}
	$content .= '</form>';

	return $content;
}

function block_exaport_assignmentversion() {
	global $DB;
	$modassign = new stdClass();
	if ($DB->record_exists('modules', array('name' => 'assign', 'visible' => 1))) {
		$modassign->new = 1;
		$modassign->title = "assign";
		$modassign->filearea = "submission_files";
		$modassign->component = "assignsubmission_file";
	} else {
		$modassign->new = 0;
		$modassign->title = "assignment";
		$modassign->filearea = "submission";
		$modassign->component = 'mod_assignment';
	};

	return $modassign;
}

function block_exaport_set_user_preferences($userid, $preferences = null) {
	global $DB;

	if (is_null($preferences) && (is_array($userid) || is_object($userid))) {
		global $USER;
		$preferences = $userid;
		$userid = $USER->id;
	}

	$newuserpreferences = new stdClass();

	if (is_object($preferences)) {
		$newuserpreferences = $preferences;
	} elseif (is_array($preferences)) {
		$newuserpreferences = (object)$preferences;
	} else {
		echo 'error #fjklfdsjkl';
	}

	if ($olduserpreferences = block_exaport_get_user_preferences_record($userid)) {
		$newuserpreferences->id = $olduserpreferences->id;
		$DB->update_record('block_exaportuser', $newuserpreferences);
	} else {
		$newuserpreferences->user_id = $userid;
		$DB->insert_record("block_exaportuser", $newuserpreferences);
	}
}

function block_exaport_get_item_where() {
	// extra where for epop
	return "(i.isoez=0 OR (i.isoez=1 AND (i.intro<>'' OR i.url<>'' OR i.attachment<>'')))";
}

function block_exaport_get_category($id) {
	global $USER, $DB;

	if ($id == 0) {
		return block_exaport_get_root_category();
	}

	return $DB->get_record("block_exaportcate", array(
		'id' => $id,
		'userid' => $USER->id,
	));
}

function block_exaport_get_root_category() {
	global $DB, $USER;

	return (object)array(
		'id' => 0,
		'pid' => 0,
		'name' => block_exaport_get_string('root_category'),
		'url' => g::$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.g::$COURSE->id,
		'item_cnt' => $DB->get_field_sql('
			SELECT COUNT(i.id) AS item_cnt
			FROM {block_exaportitem} i
			WHERE i.userid = ? AND i.categoryid = 0 AND '.block_exaport_get_item_where().'
		', array($USER->id)),

	);
}

function block_exaport_get_shareditems_category($name = null, $userid = null) {
	global $DB, $USER;

	return (object)array(
		'id' => -1,
		'pid' => -123, // not parent available
		'name' => $name != null ? $name : block_exaport_get_string('shareditems_category'),
		'item_cnt' => '',
		'url' => g::$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.g::$COURSE->id.'&type=shared&userid='.$userid,
		'userid' => $userid ? $userid : ''
		/* 		'item_cnt' => $DB->get_field_sql('
					SELECT COUNT(i.id) AS item_cnt
					FROM {block_exaportitem} i
					WHERE i.userid = ? AND i.categoryid = 0 AND '.block_exaport_get_item_where().'
				', array($USER->id))  */
	);
}

function block_exaport_badges_enabled() {
	global $CFG;

	if ($CFG->enablebadges) {
		require_once($CFG->libdir.'/badgeslib.php');
		require_once($CFG->dirroot.'/badges/lib/awardlib.php');
	}

	return $CFG->enablebadges;
}

function block_exaport_get_all_user_badges($userid = null) {
	global $USER;

	if ($userid == null) {
		$userid = $USER->id;
	}

	if (!block_exaport_badges_enabled()) {
		return [];
	} elseif (function_exists('block_exacomp_get_all_user_badges')) {
		return block_exacomp_get_all_user_badges($userid);
	} else {
		// for using badges without exacomp installation
		return badges_get_user_badges($userid);
	}
}

function block_exaport_get_user_category($title, $userid) {
	global $DB;

	return $DB->get_record('block_exaportcate', array('userid' => $userid, 'name' => $title));
}

function block_exaport_create_user_category($title, $userid, $parentid = 0) {
	global $DB;

	if (!$DB->record_exists('block_exaportcate', array('userid' => $userid, 'name' => $title, 'pid' => $parentid))) {
		$id = $DB->insert_record('block_exaportcate', array('userid' => $userid, 'name' => $title, 'pid' => $parentid));

		return $DB->get_record('block_exaportcate', array('id' => $id));
	}

	return false;
}

/**
 * Autofill the view with all existing artefacts
 * @param integer $viewid
 * @param string $existingartefacts
 * @return string Artefacts
 */
function fill_view_with_artefacts($viewid, $existingartefacts = '') {
	global $DB, $USER;

	$artefacts = block_exaport_get_portfolio_items(1);
	if ($existingartefacts <> '') {
		$existingartefactsarray = explode(',', $existingartefacts);
		$filledartefacts = $existingartefacts;
	} else {
		$existingartefactsarray = array();
		$filledartefacts = '';
	}
	if (count($artefacts) > 0) {
		$y = 1;
		foreach ($artefacts as $artefact) {
			if (!in_array($artefact->id, $existingartefactsarray)) {
				$block = new stdClass();
				$block->itemid = $artefact->id;
				$block->viewid = $viewid;
				$block->type = 'item';
				$block->positionx = 1;
				$block->positiony = $y;
				$block->id = $DB->insert_record('block_exaportviewblock', $block);
				$y++;
				$filledartefacts .= ','.$artefact->id;
			}
		}
		if ($existingartefacts == '') {
			$filledartefacts = substr($filledartefacts, 1);
		};
	}; /**/

	return $filledartefacts;
}

/**
 * Autoshare the view to teachers
 * @param integer $viewid
 * @return nothing
 */
function block_exaport_share_view_to_teachers($viewid) {
	global $DB;
	if ($viewid > 0) {
		$allteachers = block_exaport_get_course_teachers();
		$allsharedusers = block_exaport_get_shared_users($viewid);
		$diff = array_diff($allteachers, $allsharedusers);
		$view = $DB->get_record_sql('SELECT * FROM {block_exaportview} WHERE id = ?', array('id' => $viewid));
		if (!$view->shareall) {
			$view->shareall = 0;
		};
		if (!$view->externaccess) {
			$view->externaccess = 0;
		};
		if (!$view->externcomment) {
			$view->externcomment = 0;
		};
		$DB->update_record('block_exaportview', $view);
		// Add all teachers to shared users (if it is not there yet).
		if ((count($allteachers) > 0) && (count($diff) > 0)) {
			foreach ($diff as $userid) {
				// If course has a teacher.
				if ($userid > 0) {
					$shareItem = new stdClass();
					$shareItem->viewid = $view->id;
					$shareItem->userid = $userid;
					$DB->insert_record("block_exaportviewshar", $shareItem);
				};
			};
		};
	};
}

function block_exaport_get_view_blocks($view) {
	global $DB, $USER;

	$portfolioItems = block_exaport_get_portfolio_items();
	if (isset($view->userid))
		$userid = $view->userid;
	else
		$userid = $USER->id;
	$badges = block_exaport_get_all_user_badges($userid);

	$query = "select b.*".
		" from {block_exaportviewblock} b".
		" where b.viewid = ? ORDER BY b.positionx, b.positiony";

	$allBlocks = $DB->get_records_sql($query, array($view->id));
	$blocks = array();

	foreach ($allBlocks as $block) {
		if ($block->type == 'item') {
			if (!isset($portfolioItems[$block->itemid])) {
				// Could be shared sometime (because found in block_exaportviewblock with viewid)
				if (!$potentialitem = $DB->get_record("block_exaportitem", array('id' => $block->itemid))) {
					// item not found
					continue;
				} else {
					$items = block_exaport_get_portfolio_items(0, $block->itemid);
					$portfolioItems[$block->itemid] = $items[$block->itemid];
					$block->unshared = 1;
				}
			}
			if (!$block->width) {
				$block->width = 320;
			}
			if (!$block->height) {
				$block->height = 240;
			}
			$portfolioItems[$block->itemid]->intro = process_media_url($portfolioItems[$block->itemid]->intro, $block->width, $block->height);
			$block->item = $portfolioItems[$block->itemid];
		} elseif ($block->type == 'badge') {
			// find bage by id
			$badge = null;
			foreach ($badges as $tmp) {
				if ($tmp->id == $block->itemid) {
					$badge = $tmp;
					break;
				}
			}
			if (!$badge) {
				// badge not found
				continue;
			}

			if (!$badge->courseid) { // For badges with courseid = NULL
				$badge->imageUrl = (string)moodle_url::make_pluginfile_url(1, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
			} else {
				$context = context_course::instance($badge->courseid);
				$badge->imageUrl = (string)moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
			}


			$block->badge = $badge;
		} else {
			$block->print_text = file_rewrite_pluginfile_urls($block->text, 'draftfile.php', context_user::instance($USER->id)->id, 'user', 'draft', $view->draft_itemid);
			$block->itemid = null;
		}

		// clean html texts for output
		if (isset($block->print_text) && $block->print_text) {
			$block->print_text = format_text($block->print_text, FORMAT_HTML, array('filter' => false)); // TODO: $options['filter']=false - not very good solution
		}
		if (isset($block->intro) && $block->intro) {
			$block->intro = format_text($block->intro, FORMAT_HTML);
		}

		$blocks[$block->id] = $block;
	}

	return $blocks;
}

function block_exaport_get_portfolio_items($epopwhere = 0, $itemid = null) {
	global $DB, $USER;
	if ($epopwhere == 1) {
		$addwhere = " AND ".block_exaport_get_item_where();
	} else {
		$addwhere = "";
	};
	// only needed item by id
	if ($itemid) {
		$where = ' i.id = '.$itemid;
	} else {
		$where = " i.userid=? ".$addwhere;
	}
	$query = "select i.id, i.name, i.type, i.intro as intro, i.url AS link, ic.name AS cname, ic.id AS catid, ic2.name AS cname_parent, i.userid, COUNT(com.id) As comments".
		" from {block_exaportitem} i".
		" left join {block_exaportcate} ic on i.categoryid = ic.id".
		" left join {block_exaportcate} ic2 on ic.pid = ic2.id".
		" left join {block_exaportitemcomm} com on com.itemid = i.id".
		" where ".$where.
		" GROUP BY i.id, i.name, i.type, i.intro, i.url, ic.id, ic.name, ic2.name, i.userid".
		" ORDER BY i.name";
	//echo $query."<br><br>";
	$portfolioItems = $DB->get_records_sql($query, array($USER->id));
	if (!$portfolioItems) {
		$portfolioItems = array();
	}

	// add shared items
	$shared_items = block_exaport_get_items_shared_to_user($USER->id, true);
	$portfolioItems = $portfolioItems + $shared_items;

	foreach ($portfolioItems as $item) {
		if (null == $item->cname) {
			$item->category = format_string(block_exaport_get_root_category()->name);
			$item->catid = 0;
		} elseif (null == $item->cname_parent) {
			$item->category = format_string($item->cname);
		} else {
			$catid = $item->catid;
			$item->category = "";
			while ($catid) {
				$cat = $DB->get_record("block_exaportcate", ["userid" => $item->userid, "id" => $catid]);

				if ($cat) {
					if (!$item->category) {
						$item->category = format_string($cat->name);
					} else {
						$item->category = format_string($cat->name)." &rArr; ".$item->category;
					}

					$catid = $cat->pid;
				} else {
					break;
				}
			}
		}

		if ($item->intro) {
			$item->intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id, 'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);
			$item->intro = format_text($item->intro, FORMAT_HTML);
		}

		//get competences of the item
		$item->userid = $USER->id;

		$comp = block_exaport_check_competence_interaction();
		if ($comp) {
			$compids = block_exaport_get_active_compids_for_item($item);

			if ($compids) {
				$competences = "";
				foreach ($compids as $compid) {
					$conditions = array("id" => $compid);
					$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields = '*', $strictness = IGNORE_MISSING);

					if ($competencesdb != null) {
						$competences .= $competencesdb->title.'<br>';
					}
				}
				$competences = str_replace("\r", "", $competences);
				$competences = str_replace("\n", "", $competences);
				$competences = str_replace("\"", "&quot;", $competences);
				$competences = str_replace("'", "&prime;", $competences);

				$item->competences = $competences;
			}
		}

		unset($item->userid);

		unset($item->cname);
		unset($item->cname_parent);
	}

	//	print_r($portfolioItems);

	return $portfolioItems;
}

/**
 * Function gets teachers array of course
 * @return array
 */
function block_exaport_get_course_teachers($exceptMyself = true) {
	global $DB, $USER;

	$courseid = optional_param('courseid', 0, PARAM_INT);
	$context = context_course::instance($courseid);

	// Role id='3' - teachers. '4'- assistents.
	$query = "SELECT u.id as userid, u.id AS tmp
	FROM {user} u
	JOIN {role_assignments} ra ON ra.userid = u.id
	WHERE ra.contextid=? AND ra.roleid = '3'";
	$exastudTeachers = $DB->get_records_sql($query, [$context->id]);

	// if exacomp is not installed this function returns an emtpy array
	$exacompTeachers = get_enrolled_users($context, 'block/exacomp:teacher');

	$teachers = $exastudTeachers + $exacompTeachers;

	if ($exceptMyself) {
		unset($teachers[$USER->id]);
	}

	$teachers = array_keys($teachers);

	return $teachers;
}

/**
 * Function gets all shared users
 * @param $viewid
 * @return array
 */
function block_exaport_get_shared_users($viewid) {
	global $DB, $USER;
	$sharedusers = array();
	if ($viewid > 0) {
		$query = "SELECT userid FROM {block_exaportviewshar} s WHERE s.viewid=".$viewid;
		$users = $DB->get_records_sql($query);
		foreach ($users as $user) {
			$sharedusers[] = $user->userid;
		};
	};
	sort($sharedusers);

	return $sharedusers;
}

;

function block_exaport_file_userquotecheck($addingfiles = 0, $id = 0) {
	global $DB, $USER, $CFG;
	$result = $DB->get_record_sql("SELECT SUM(filesize) as allfilesize FROM {files} WHERE contextid = ? and component='block_exaport'", array(context_user::instance($USER->id)->id));
	if ($result->allfilesize + $addingfiles > $CFG->block_exaport_userquota) {
		$courseid = optional_param('courseid', 0, PARAM_INT);
		$categoryid = optional_param('categoryid', 0, PARAM_INT);
		$type = optional_param('type', 0, PARAM_RAW);
		print_error('userquotalimit', '', new moodle_url('/blocks/exaport/item.php',
			array('sesskey' => sesskey(),
				'courseid' => $courseid,
				'action' => 'edit',
				'type' => $type,
				'id' => $id,
				'categoryid' => $categoryid)), null);
		//throw new file_exception('userquotalimit');
	}

	return true;
}

function block_exaport_get_filesize_by_draftid($draftid = 0) {
	global $DB, $USER, $CFG;
	$result = $DB->get_record_sql("SELECT SUM(filesize) AS allfilesize FROM {files} WHERE contextid = ? AND component = 'user' AND filearea='draft' AND itemid = ?", array(context_user::instance($USER->id)->id, $draftid));
	if ($result) {
		return $result->allfilesize;
	} else {
		return 0;
	}
}

function block_exaport_get_maxfilesize_by_draftid_check($draftid = 0) {
	global $DB, $USER, $CFG;
	$result = $DB->get_record_sql("SELECT MAX(filesize) AS maxfilesize FROM {files} WHERE contextid = ? AND component = 'user' AND filearea='draft' AND itemid = ?", array(context_user::instance($USER->id)->id, $draftid));
	if (($CFG->block_exaport_max_uploadfile_size > 0) && ($result->maxfilesize > $CFG->block_exaport_max_uploadfile_size)) {
		print_error('maxbytes', 'exaport', 'blocks/exaport/view_items.php', null);
		//throw new file_exception('maxbytes');
	}

	return true;
}

function block_exaport_is_valid_media_by_filename($filename) {
	global $DB, $USER, $CFG;
	$path_parts = pathinfo($filename);
	switch ($path_parts['extension']) {
		case 'avi':
		case 'mp4':
		case 'flv':
		case 'swf':
		case 'mpg':
		case 'mpeg':
		case '3gp':
		case 'webm':
		case 'ogg':
			return true;
		default:
			return false;
	}
}

function block_exaport_item_is_editable($itemid) {
	global $CFG, $DB, $USER;

	if($CFG->block_exaport_app_alloweditdelete)
		return true;
	
	if (block_exaport_check_competence_interaction() && !block_exaport_item_is_resubmitable($itemid)) {
		return false;
	}

	if (block_exaport_check_competence_interaction()) {
		$itemExample = $DB->get_record ( BLOCK_EXACOMP_DB_ITEMEXAMPLE, array (
				"itemid" => $itemid
		) );

		// check item grading and teacher comment
		if ($itemExample) {
			if ($itemExample->teachervalue) {
				// lehrerbewertung da
				return false;
			} else {
				$itemcomments = $DB->get_records ( 'block_exaportitemcomm', array (
						'itemid' => $itemid
				), 'timemodified ASC', 'entry, userid', 0, 2 );
				foreach ( $itemcomments as $itemcomment ) {
					if ($USER->id != $itemcomment->userid) {
						// somebody commented on this item -> must be teacher
						return false;
					}
				}
			}
		}
	}
	
	return true;
}

/**
 * checks if exacomp is installed and the item can be resubmitted there
 * @param $itemid
 * @return bool
 */
function block_exaport_item_is_resubmitable($itemid) {
	global $CFG, $DB, $USER, $COURSE;

	if($CFG->block_exaport_app_alloweditdelete)
		return true;
	
	if (!block_exaport_check_competence_interaction()) {
		return false;
	}

	if ($itemExample = $DB->get_record(BLOCK_EXACOMP_DB_ITEMEXAMPLE, array("itemid" => $itemid))) {
		$item = $DB->get_record('block_exaportitem', array('id' => $itemid));
		if ($eval = $DB->get_record(BLOCK_EXACOMP_DB_EXAMPLEEVAL, array('exampleid' => $itemExample->exampleid, 'studentid' => $USER->id, 'courseid' => $item->courseid))) {
			if (!$eval->resubmission) {
				return false;
			}
		}
	}
	return true;
}

function block_exaport_example_is_submitable($exampleid) {
	global $DB, $USER, $COURSE;
	
	if ($eval = $DB->get_record(BLOCK_EXACOMP_DB_EXAMPLEEVAL, array('exampleid' => $exampleid, 'studentid' => $USER->id, 'courseid' => $COURSE->id))) {
		return $eval->resubmission;
	}
	return true;
	
}
function block_exaport_has_grading_permission($itemid) {
	global $DB;

	if (!block_exaport_check_competence_interaction()) {
		return false;
	}

	// check if item is a submission for an exacomp example
	$itemExample = $DB->get_record(BLOCK_EXACOMP_DB_ITEMEXAMPLE, array("itemid" => $itemid));
	if (!$itemExample) {
		return false;
	}

	$item = $DB->get_record('block_exaportitem', array('id' => $itemid));
	if (!$item || !$item->courseid) {
		return false;
	}

	$coursecontext = context_course::instance($item->courseid);

	return has_capability('block/exacomp:teacher', $coursecontext);
}

function block_exaport_get_item_tags($itemid, $orderBy = '') {
	global $DB, $CFG;
	$tags = array();
	if (is_array($itemid)) {
		// Tags for a few items.
		if (count($itemid) > 0) {
			list($whereItems, $paramItems) = $DB->get_in_or_equal($itemid, SQL_PARAMS_NAMED);
			$result = $DB->get_records_sql('SELECT DISTINCT rawname 			
									FROM {tag_instance} ti LEFT JOIN {tag} t ON t.id=ti.tagid 
									WHERE component=\'block_exaport\' AND itemtype=\'block_exaportitem\' AND itemid '.$whereItems.' '.
				($orderBy != '' ? ' ORDER BY '.$orderBy : ''),
				$paramItems);
		}
	} else {
		// Tags for one item.
		$result = $DB->get_records_sql('SELECT * 
									FROM {tag_instance} ti LEFT JOIN {tag} t ON t.id=ti.tagid 
									WHERE component=\'block_exaport\' AND itemtype=\'block_exaportitem\' AND itemid = ?'.
			($orderBy != '' ? ' ORDER BY '.$orderBy : ''),
			array($itemid));
	}
	if (!$result) {
		$result = array();
	}
	foreach ($result as &$tag) {
		$tags[] = $tag->rawname;
	}

	return $tags;
}

/**
 * Returns artefacts tagged with a specified tag.
 *
 * This is a callback used by the tag area block_exaport/block_exaportitem to search for artefacts
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function block_exaport_get_tagged_items($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
	global $OUTPUT;
	$perpage = $exclusivemode ? 20 : 5;

	// Build the SQL query.
	// $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
	$query = "SELECT i.id, i.name, i.type, i.userid, cat.name AS categoryname, i.categoryid
                FROM {block_exaportitem} i
                LEFT JOIN {block_exaportcate} cat ON i.categoryid = cat.id
                JOIN {tag_instance} tt ON i.id = tt.itemid
				WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component AND i.id %ITEMFILTER%";

	$params = array('itemtype' => 'block_exaportitem', 'tagid' => $tag->id, 'component' => 'block_exaport');

	$totalpages = $page + 1;

	// Use core_tag_index_builder to build and filter the list of items.
	$builder = new core_tag_index_builder('block_exaport', 'block_exaportitem', $query, $params, $page * $perpage, $perpage + 1);
	$items = $builder->get_items();
	if (count($items) > $perpage) {
		$totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
		array_pop($items);
	}

	// Build the display contents.
	if ($items) {
		$tagfeed = new core_tag\output\tagfeed();
		foreach ($items as $item) {
			$itemurl = new moodle_url('/blocks/exaport/shared_item.php', array('itemid' => $item->id, 'access' => 'portfolio/id/'.$item->userid));
			$itemname = format_string($item->name, true);
			$itemname = html_writer::link($itemurl, $itemname);
			$categoryname = $item->categoryname;
			$iconsrc = new moodle_url('/blocks/exaport/item_thumb.php', array('item_id' => $item->id));
			$icon = html_writer::link($itemurl, html_writer::empty_tag('img', array('src' => $iconsrc)));
			$tagfeed->add($icon, $itemname, $categoryname);
		}

		$content = $OUTPUT->render_from_template('core_tag/tagfeed',
			$tagfeed->export_for_template($OUTPUT));

		return new core_tag\output\tagindex($tag, 'block_exaport', 'block_exaportitem', $content,
			$exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
	}
}


function block_exaport_securephrase_viewemail(&$view, $email) {
	// secure phrase relates to the email
	return substr(sha1(rand().$view->id.$email.time().rand()), rand(0, 7), 32);
} 