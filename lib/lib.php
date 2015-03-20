<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2006 exabis internet solutions <info@exabis.at>
 *  All rights reserved
 *
 *  You can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This module is based on the Collaborative Moodle Modules from
 *  NCSA Education Division (http://www.ncsa.uiuc.edu)
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

require_once $CFG->libdir . '/filelib.php';

if (block_exaport_check_competence_interaction()){
	if(file_exists($CFG->dirroot . '/blocks/exacomp/lib/lib.php'))
		require_once $CFG->dirroot . '/blocks/exacomp/lib/lib.php';
	else
		require_once $CFG->dirroot . '/blocks/exacomp/lib/div.php';
}

global $DB;

/*** FILE FUNCTIONS **********************************************************************/

function block_exaport_get_item_file($item) {
	$fs = get_file_storage();
	
	// list all files, excluding directories!
	$areafiles = $fs->get_area_files(context_user::instance($item->userid)->id, 'block_exaport', 'item_file', $item->id, 'itemid', false);
	
	// file found?
	if (empty($areafiles))
		return null;
	else
		// return first file (there should be only one file anyway)
		return reset($areafiles);
}

function block_exaport_add_to_log($courseid, $module, $action, $url='', $info='', $cm=0, $user=0) {
	if (!function_exists('get_log_manager')) {
		// old style
		return add_to_log($courseid, $module, $action, $url='', $info='', $cm=0, $user=0);
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
	global $DB, $USER,$CFG;
	if (block_exaport_course_has_desp() && !$DB->record_exists('block_exaportcate', array('userid'=>$USER->id))
		&& !empty($CFG->block_exaport_create_desp_categories)) {
		block_exaport_import_categories("desp_categories");
	}
}
function block_exaport_import_categories($categoriesSTR){
	global $DB, $USER;
	$categories = trim(get_string($categoriesSTR, "block_exaport"));
	
	if (!$categories) return;
	
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
			if(!$DB->record_exists('block_exaportcate', array("name"=>trim($category,'-'))))
				$DB->insert_record("block_exaportcate", $newentry);
		} else {
			$newentry->name = $category;
			$newentry->pid = 0;
			//$categoryDB = $DB->get_records('block_exaportcate', array("name"=>$category));
			if(!$DB->record_exists('block_exaportcate', array("name"=>$category)))
				$lastMainId = $DB->insert_record("block_exaportcate", $newentry);
			else {
				$lastMainId = $DB->get_field('block_exaportcate', 'id', array("name"=>$category));
			}
		}
	}
}
function block_exaport_feature_enabled($feature) {
    global $CFG;
    if ($feature == 'views')
        return true;
    if ($feature == 'copy_to_course')
        return !empty($CFG->block_exaport_feature_copy_to_course);
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

    return $courseid . '/' . $CFG->moddata . '/assignment/' . $assignmentid . '/' . $userid;
}

function block_exaport_print_file($url, $filename, $alttext) {
    global $CFG, $OUTPUT;
    $icon = new pix_icon(file_mimetype_icon($filename), '');
    $type = mimeinfo('type', $filename);
    if (in_array($type, array('image/gif', 'image/jpeg', 'image/png'))) {    // Image attachments don't get printed as links
        return "<img src=\"$url\" alt=\"" . format_string($alttext) . "\" />";
    } else {
        return '<p><img src="' . $CFG->wwwroot . '/pix/' . $icon->pix . '.gif" class="icon" alt="' . $icon->pix . '" />&nbsp;' . $OUTPUT->action_link($url, $filename) . "</p>";
    }
}

function block_exaport_course_has_desp() {
	global $COURSE, $DB;
	
	if (isset($COURSE->has_desp))
		return $COURSE->has_desp;
	
	// desp block installed?
	if (!is_dir(dirname(__FILE__).'/../../desp'))
		return $COURSE->has_desp = false;

	$context = context_course::instance($COURSE->id);
	
	return $COURSE->has_desp = $DB->record_exists('block_instances', array('blockname'=>'desp', 'parentcontextid'=>$context->id));
}
function block_exaport_wrapperdivstart(){
	return html_writer::start_tag('div',array('id'=>'exaport'));
}
function block_exaport_wrapperdivend(){
	return html_writer::end_tag('div');
}
/**
 * Print moodle header
 * @param string $item_identifier translation-id for this page
 * @param string $sub_item_identifier translation-id for second level if needed
 */

function block_exaport_print_header($item_identifier, $sub_item_identifier = null) {

    if (!is_string($item_identifier)) {
        echo 'noch nicht unterst�tzt';
    }

    global $CFG, $COURSE, $PAGE;

	// $PAGE->requires->css('/blocks/exaport/css/jquery-ui.css');
	$PAGE->requires->js('/blocks/exaport/javascript/jquery.js', true);
	$PAGE->requires->js('/blocks/exaport/javascript/jquery.json.js', true);
	$PAGE->requires->js('/blocks/exaport/javascript/jquery-ui.js', true);

	$PAGE->requires->js('/blocks/exaport/javascript/jquery.colorbox.js', true);
	$PAGE->requires->css('/blocks/exaport/css/colorbox.css');

	$PAGE->requires->js('/blocks/exaport/javascript/exaport.js', true);



	$scriptName = preg_replace('!\.[^\.]+$!', '', basename($_SERVER['PHP_SELF']));
	if (file_exists($CFG->dirroot.'/blocks/exaport/css/'.$scriptName.'.css'))
		$PAGE->requires->css('/blocks/exaport/css/'.$scriptName.'.css');
	if (file_exists($CFG->dirroot.'/blocks/exaport/javascript/'.$scriptName.'.js'))
		$PAGE->requires->js('/blocks/exaport/javascript/'.$scriptName.'.js', true);

	$strbookmarks = block_exaport_get_string("mybookmarks");

    // navigationspfad
    $navlinks = array();
    $navlinks[] = array('name' => $strbookmarks, 'link' => "view.php?courseid=" . $COURSE->id, 'type' => 'title');
    $nav_item_identifier = $item_identifier;

    $icon = $item_identifier;
    $currenttab = $item_identifier;

    // haupttabs
    $tabs = array();

	if (block_exaport_course_has_desp()) {
		$tabs[] = new tabobject('back', $CFG->wwwroot . '/blocks/desp/index.php?courseid=' . $COURSE->id, get_string("back_to_desp", "block_exaport"), '', true);
	}

    $tabs[] = new tabobject('personal', $CFG->wwwroot . '/blocks/exaport/view.php?courseid=' . $COURSE->id, get_string("personal", "block_exaport"), '', true);
    // $tabs[] = new tabobject('categories', $CFG->wwwroot . '/blocks/exaport/view_categories.php?courseid=' . $COURSE->id, get_string("categories", "block_exaport"), '', true);
    $tabs[] = new tabobject('bookmarks', $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id, block_exaport_get_string("bookmarks"), '', true);
	$tabs[] = new tabobject('views', $CFG->wwwroot . '/blocks/exaport/views_list.php?courseid=' . $COURSE->id, get_string("views", "block_exaport"), '', true);
    $tabs[] = new tabobject('exportimport', $CFG->wwwroot . '/blocks/exaport/exportimport.php?courseid=' . $COURSE->id, get_string("exportimport", "block_exaport"), '', true);
    $tabs[] = new tabobject('sharedbookmarks', $CFG->wwwroot . '/blocks/exaport/shared_views.php?courseid=' . $COURSE->id, block_exaport_get_string("sharedbookmarks"), '', true);

    // tabs f�r das untermen�
    $tabs_sub = array();
    // ausgew�hlte tabs f�r untermen�s
    $activetabsubs = Array();

    if (strpos($item_identifier, 'views') === 0) {
		$id = optional_param('id', 0, PARAM_INT);
		if ($id>0) {
			$activetabsubs[] = $sub_item_identifier;
			
			$tabs_sub[] = new tabobject('title', s($CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=title&action=edit'),get_string("viewtitle", "block_exaport"), '', true);		
			$tabs_sub[] = new tabobject('layout', s($CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=layout&action=edit'),get_string("viewlayout", "block_exaport"), '', true);
			$tabs_sub[] = new tabobject('content', s($CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&action=edit'),get_string("viewcontent", "block_exaport"), '', true);
			if (has_capability('block/exaport:shareextern', context_system::instance()) || has_capability('block/exaport:shareintern', context_system::instance())) {
				$tabs_sub[] = new tabobject('share', s($CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=share&action=edit'),get_string("viewshare", "block_exaport"), '', true);			
			}
		}
	} elseif (strpos($item_identifier, 'bookmarks') === 0) {
        $activetabsubs[] = $item_identifier;
        $currenttab = 'bookmarks';
    } elseif (strpos($item_identifier, 'exportimport') === 0) {
        $currenttab = 'exportimport';

        // unterpunkt?
        if ($tmp = substr($item_identifier, strlen($currenttab))) {
            $nav_item_identifier = $tmp;
        }

        if (strpos($nav_item_identifier, 'export') !== false)
            $icon = 'export';
        else
            $icon = 'import';
    }


    $item_name = get_string($nav_item_identifier, "block_exaport");
    if ($item_name[0] == '[')
        $item_name = get_string($nav_item_identifier);
    $navlinks[] = array('name' => $item_name, 'link' => null, 'type' => 'misc');

    //$navigation = build_navigation($navlinks);
	foreach($navlinks as $navlink){
		$PAGE->navbar->add($navlink["name"], $navlink["link"]);
	}
	
	$PAGE->set_title($item_name);
   	$PAGE->set_heading(get_string(block_exaport_course_has_desp()?"desp_pluginname":'pluginname', "block_exaport"));
 
     // header
    global $OUTPUT;
    
  	echo $OUTPUT->header();
    echo block_exaport_wrapperdivstart();
	if (block_exaport_course_has_desp()) {
		// include the desp css
		echo '<link href="'.$CFG->wwwroot.'/blocks/desp/styles.css" rel="stylesheet" type="text/css" />';
	}

    $OUTPUT->heading("<img src=\"{$CFG->wwwroot}/blocks/exaport/pix/" . $icon . ".png\" width=\"16\" height=\"16\" alt='icon-$item_identifier' /> " . $strbookmarks . ': ' . $item_name);

    print_tabs(array($tabs, $tabs_sub), $currenttab, null, $activetabsubs);

	if (block_exaport_course_has_desp() && (strpos($currenttab,'bookmarks') === 0) ) {
		
		
			
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

function block_exaport_get_string($string, $param=null) {
	$manager = get_string_manager();
	
	if (block_exaport_course_has_desp() && $manager->string_exists('desp_'.$string, 'block_exaport'))
		return $manager->get_string('desp_'.$string, 'block_exaport', $param);

	if ($manager->string_exists($string, "block_exaport"))
		return $manager->get_string($string, 'block_exaport', $param);

	return $manager->get_string($string, '', $param);	
}
function todo_string($string) {
	$manager = get_string_manager();
	
	if ($manager->string_exists($string, "block_exaport"))
		return $manager->get_string($string, 'block_exaport');

	return '[['.$string.']]';	
}

function block_exaport_print_footer() {
    global $COURSE;
    print_footer($COURSE);
}

/**
 * Parse user submitted item_type and return a correct type
 * @param string $type
 * @param boolean $all_allowd Is the type 'all' allowed? E.g. for Item-List
 * @return string correct type
 */
function block_exaport_check_item_type($type, $all_allowed) {
    if (in_array($type, Array('link', 'file', 'note')))
        return $type;
    else
        return $all_allowed ? 'all' : false;
}

/**
 * Convert item type to plural
 * @param string $type
 * @return string Plural. E.g. file->files, note->notes, all->all (has no plural)
 */
function block_exaport_get_plural_item_type($type) {
    return $type == 'all' ? $type : $type . 's';
}

/**
 * Parse user submitted item sorting and check if allowed/available!
 * @param $sort the sorting in a format like "category.desc"
 * @return Array(sortcolumn, asc|desc)
 */
function block_exaport_parse_sort($sort, array $allowedSorts, array $defaultSort = null) {
    if (!is_array($sort))
        $sort = explode('.', $sort);

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
    if ($column == 'sortorder')
        return array($column, 'asc');

    if ($order != "desc")
        $order = "asc";

    return array($column, $order);
}

function block_exaport_parse_item_sort($sort, $catsortallowed) {
	if($catsortallowed)
		return block_exaport_parse_sort($sort, array('date', 'name', 'category', 'type'), array('date', 'desc'));

	return block_exaport_parse_sort($sort, array('date', 'name', 'type'), array('date', 'desc'));
}

function block_exaport_item_sort_to_sql($sort, $catsortallowed) {
    $sort = block_exaport_parse_item_sort($sort, $catsortallowed);

    $column = $sort[0];
    $order = $sort[1];

    if ($column == "date") {
        $sql_sort = "i.timemodified " . $order;
    } elseif ($column == "category") {
        $sql_sort = "cname " . $order . ", i.timemodified";
    } else {
        $sql_sort = "i." . $column . " " . $order . ", i.timemodified";
    }

    return ' order by ' . $sql_sort;
}

function block_exaport_parse_view_sort($sort, $for_shared = false) {
    return block_exaport_parse_sort($sort, array('name', 'timemodified'));
}

function block_exaport_view_sort_to_sql($sort) {
   	global $CFG;
	$sort = block_exaport_parse_view_sort($sort);

    $column = $sort[0];
    $order = $sort[1];
    
    if((strcmp($column, "name")==0) && (strcmp($CFG->dbtype, "sqlsrv")==0)){
		$sql_sort = "cast(v.".$column." AS varchar(max)) ".$order.", v.timemodified DESC";
	}
	else if((strcmp($column, "timemodified")==0) && (strcmp($CFG->dbtype, "sqlsrv")==0)){
		$sql_sort = "v.timemodified DESC";
	}
	else{
		$sql_sort = "v." . $column . " " . $order . ", v.timemodified DESC";
	}

    return ' order by ' . $sql_sort;
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
    global $DB, $CFG;
	$dbman = $DB->get_manager();

    try {
        return (!empty($CFG->block_exaport_enable_interaction_competences) && $dbman->table_exists('block_exacompdescriptors'));
    } catch(dml_read_exception $e) {
        return false;
    }
}

function block_exaport_check_item_competences($item) {
    global $DB;

    $competences = $DB->get_records('block_exacompcompactiv_mm', array("activityid" => $item->id, "eportfolioitem" => 1));
    if ($competences)
        return true;
    else
        return false;
}

function block_exaport_build_comp_table($item, $role="teacher") {
    global $DB;

    $sql = "SELECT CONCAT(CONCAT(da.id,'_'),d.id) as uniquid,d.title, d.id FROM {block_exacompdescriptors} d, {block_exacompcompactiv_mm} da WHERE d.id=da.compid AND da.eportfolioitem=1 AND da.activityid=?";
    $descriptors = $DB->get_records_sql($sql, array($item->id));
    $content = "<table class='compstable flexible boxaligncenter generaltable'>
                <tr><td><h2>" . $item->name . "</h2></td></tr>";
    
    if($role == "teacher") {
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
        $content .= '<tr '. $bgcolor .'><td>' . $descriptor->title . '</td></tr>';
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
function block_exaport_set_competences($values, $item, $reviewerid, $role=1 ) {
    global $DB;

    $DB->delete_records('block_exacompcompuser_mm',array("activityid"=>$item->id, "eportfolioitem"=>1, "role"=>$role, "userid"=>$item->userid));
    
    foreach ($values as $value) {
        $data = array(
            "activityid" => $item->id,
            "eportfolioitem" => 1,
            "compid" => $value,
            "userid" => $item->userid,
            "reviewerid" => $reviewerid,
            "role" => $role
        );
        print_r($data);
        $DB->insert_record('block_exacompcompuser_mm', $data);
    }
}
function block_exaport_get_competences($item, $role=1) {
    global $DB;
	return $DB->get_records('block_exacompcompactiv_mm', array("eportfolioitem"=>1, "activityid"=>$item->id));
}
function block_exaport_build_comp_tree() {
    global $DB, $USER;
	
	$courses = $DB->get_records('course', array());
	
	$descriptors = array();
	foreach($courses as $course){
		$context = context_course::instance($course->id);
		if(is_enrolled($context, $USER)){
			$alldescr = block_exacomp_get_descritors_list($course->id);
			foreach($alldescr as $descr){
				if(!in_array($descr, $descriptors)){
					$descriptors[] = $descr;
				}
			}
		}
	}
	
    $tree = '<form name="treeform"><ul id="comptree" class="treeview">';
    $subject = "";
    $topic = "";
    $newsub = true;
    $newtop = true;
    $index = 0;

    foreach ($descriptors as $descriptor) {
        if ($descriptor->subject != $subject) {
            $subject = $descriptor->subject;
            if (!$newsub
                )$tree.='</ul></li></ul></li>';
            $tree.='<li id="gegenst'.$descriptor->subjectid.'" alt="'.$subject.'">' . $subject;
            $tree.='<ul>';

            $newsub = false;
            $newtop = true;
        }
        if ($descriptor->topic != $topic) {
            $topic = $descriptor->topic;
            if (!$newtop)
                $tree.='</ul></li>';
            $tree.='<li>' . $topic;
            $tree.='<ul>';
            $newtop = false;
        }
        $tree.='<li><input class="'.$descriptor->subjectid.'" type="checkbox" name="desc" value="' . $descriptor->id . '" alt="' . $descriptor->title . '">' . $descriptor->title . '</li>';

        $index++;
    }
    $tree .= '</ul></li></ul></li></ul>
			<input type="button" id="id_submitbutton2" value="'.get_string('savechanges').'" name="savecompetencesbutton" onClick="jQueryExaport(function($){$.colorbox.close()});">
			</form>';

    return $tree;
}
function block_exaport_assignmentversion(){
	global $DB;
	$modassign=new stdClass();
	if($DB->record_exists('modules', array('name'=>'assign', 'visible'=>1))){
		$modassign->new=1;
		$modassign->title="assign";
		$modassign->filearea="submission_files";
		$modassign->component="assignsubmission_file";
	}else{
		$modassign->new=0;
		$modassign->title="assignment";
		$modassign->filearea="submission";
		$modassign->component='mod_assignment';
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
		$newuserpreferences = (object) $preferences;
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
	
	if ($id == 0)
		return block_exaport_get_root_category();
		
	return $DB->get_record("block_exaportcate", array(
		'id' => $id,
		'userid' => $USER->id
	));
}

function block_exaport_get_root_category() {
	global $DB, $USER;
	return (object) array(
		'id' => 0,
		'pid' => -999,
		'name' => block_exaport_get_string('root_category'),
		'item_cnt' => $DB->get_field_sql('
			SELECT COUNT(i.id) AS item_cnt
			FROM {block_exaportitem} i
			WHERE i.userid = ? AND i.categoryid = 0 AND '.block_exaport_get_item_where().'
		', array($USER->id))

	);
}

function block_exaport_badges_enabled() {
	return (block_exaport_check_competence_interaction() && block_exacomp_moodle_badges_enabled());
}

function block_exaport_get_all_user_badges() {
	if (block_exaport_badges_enabled()) {
		if (!function_exists('block_exacomp_get_all_user_badges')){
			print_error("please update exabis competencies to latest version");
			exit;
		}else
			return block_exacomp_get_all_user_badges();
	} else
		return null;
}
function block_exaport_get_user_category($title, $userid) {
    global $DB;
    
    return $DB->get_record('block_exaportcate', array('userid'=>$userid,'name'=>$title));
}
function block_exaport_create_user_category($title, $userid, $parentid = 0) {
    global $DB;
    
    if(!$DB->record_exists('block_exaportcate', array('userid'=>$userid,'name'=>$title,'pid'=>$parentid))) {
        $id = $DB->insert_record('block_exaportcate',array('userid'=>$userid,'name'=>$title,'pid'=>$parentid));
        return $DB->get_record('block_exaportcate',array('id'=>$id));
    }
    return false;
}

/**
 * Autofill the view with all existing artefacts
 * @param integer $viewid
 * @param string $existingartefacts
 * @return string Artefacts
 */
function fill_view_with_artefacts($viewid, $existingartefacts='') {
    global $DB, $USER;

    $artefacts = block_exaport_get_portfolio_items(1);
    if ($existingartefacts<>'') {
        $existingartefactsarray = explode(',', $existingartefacts);
        $filledartefacts = $existingartefacts;
    } else {
        $existingartefactsarray = array();
        $filledartefacts = '';
    }
    if (count($artefacts)>0) {
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
function share_view_to_teachers($viewid) {
    global $DB, $USER;
    if ($viewid > 0) {
        $allteachers = block_exaport_get_course_teachers();
        $allsharedusers = block_exaport_get_shared_users($viewid);
        $diff = array_diff($allteachers, $allsharedusers);
        $view = $DB->get_record_sql('SELECT * FROM {block_exaportview} WHERE id = ?', array('id'=>$viewid));
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
    $badges = block_exaport_get_all_user_badges();

    $query = "select b.*".
            " from {block_exaportviewblock} b".
            " where b.viewid = ? ORDER BY b.positionx, b.positiony";

    $allBlocks = $DB->get_records_sql($query, array($view->id));
    $blocks = array();

    foreach ($allBlocks as $block) {
        if ($block->type == 'item') {
            if (!isset($portfolioItems[$block->itemid])) {
                // item not found
                continue;
            }
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
            	
            $context = context_course::instance($badge->courseid);
            $badge->imageUrl = (string)moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);

            $block->badge = $badge;
        } else {
            $block->print_text = file_rewrite_pluginfile_urls($block->text, 'draftfile.php', context_user::instance($USER->id)->id, 'user', 'draft', $view->draft_itemid);
            $block->itemid = null;
        }

        // clean html texts for output
        if (isset($block->print_text) && $block->print_text) {
            $block->print_text = clean_text($block->print_text, FORMAT_HTML);
        }
        if (isset($block->intro) && $block->intro) {
            $block->intro = clean_text($block->intro, FORMAT_HTML);
        }

        $blocks[$block->id] = $block;
    }

    return $blocks;
}

function block_exaport_get_portfolio_items($epopwhere = 0) {
    global $DB, $USER;
    if ($epopwhere == 1) {
        $addwhere = " AND ".block_exaport_get_item_where();
    } else {
        $addwhere = "";
    };
    $query = "select i.id, i.name, i.type, i.intro as intro, i.url AS link, ic.name AS cname, ic.id AS catid, ic2.name AS cname_parent, i.userid, COUNT(com.id) As comments".
            " from {block_exaportitem} i".
            " left join {block_exaportcate} ic on i.categoryid = ic.id".
            " left join {block_exaportcate} ic2 on ic.pid = ic2.id".
            " left join {block_exaportitemcomm} com on com.itemid = i.id".
            " where i.userid=? ".$addwhere.
            " GROUP BY i.id, i.name, i.type, i.intro, i.url, ic.id, ic.name, ic2.name, i.userid".
            " ORDER BY i.name";
    //echo $query."<br><br>";
    $portfolioItems = $DB->get_records_sql($query, array($USER->id));
    if (!$portfolioItems) {
        $portfolioItems = array();
    }

    // add shared items
    $shared_items = exaport_get_shared_items_for_user($USER->id, true);
    $portfolioItems = $portfolioItems + $shared_items;

    foreach ($portfolioItems as &$item) {
        if (null == $item->cname) {
            $item->category = format_string(block_exaport_get_root_category()->name);
            $item->catid = 0;
        } elseif (null == $item->cname_parent) {
            $item->category = format_string($item->cname);
        } else {
            //$item->category = format_string($item->cname_parent) . " &rArr; " . format_string($item->cname);
            $catid= $item->catid;
            $catname = $item->cname;
            $item->category = "";
            do{
                $conditions = array("userid" => $USER->id, "id" => $catid);
                $cats=$DB->get_records_select("block_exaportcate", "userid = ? AND id = ?",$conditions, "name ASC");
                foreach($cats as $cat){
                    if($item->category == "")
                        $item->category =format_string($cat->name);
                    else
                        $item->category =format_string($cat->name)." &rArr; ".$item->category;
                    $catid = $cat->pid;
                }

            }while ($cat->pid != 0);
        }

        if ($item->intro) {
            $item->intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id, 'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);
            $item->intro = clean_text($item->intro, FORMAT_HTML);
        }

        //get competences of the item
        $item->userid = $USER->id;

        $comp = block_exaport_check_competence_interaction();
        if($comp){
            $array = block_exaport_get_competences($item, 0);

            if(count($array)>0){
                $competences = "";
                foreach($array as $element){
                    $conditions = array("id" => $element->compid);
                    $competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING);

                    if($competencesdb != null){
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
function block_exaport_get_course_teachers() {
    global $DB, $USER;
    $courseid = optional_param('courseid', 0, PARAM_INT);
    // Role id='3' - teachers. '4'- assistents.
    $query = "SELECT u.id as userid, c.id, c.shortname, u.username
    FROM {course} c
    LEFT OUTER JOIN {context} cx ON c.id = cx.instanceid
    LEFT OUTER JOIN {role_assignments} ra ON cx.id = ra.contextid AND ra.roleid = '3'
    LEFT OUTER JOIN {user} u ON ra.userid = u.id
    WHERE cx.contextlevel = '50' AND u.id>0 AND c.id = ".$courseid;
    $courseteachers = $DB->get_records_sql($query);
    $teacherarray = array();
    foreach($courseteachers as $teacher) {
        if ($teacher->userid <> $USER->id) { // Except himself.
            $teacherarray[] = $teacher->userid;
        };
    };
    sort($teacherarray);
    return $teacherarray;
}

/**
 * Function gets all shared users
 * @param $viewid
 * @return array
 */
function block_exaport_get_shared_users($viewid) {
    global $DB, $USER;
    $sharedusers = array ();
    if ($viewid > 0) {
        $query = "SELECT userid FROM {block_exaportviewshar} s WHERE s.viewid=".$viewid;
        $users = $DB->get_records_sql($query);
        foreach($users as $user) {
            $sharedusers[] = $user->userid;
        };
    };
    sort($sharedusers);
    return $sharedusers;
};
