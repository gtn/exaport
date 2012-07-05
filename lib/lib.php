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
global $DB;

/*** FILE FUNCTIONS **********************************************************************/

function block_exaport_get_item_file($item) {
	$fs = get_file_storage();
	return reset($fs->get_area_files(get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_file', $item->id, null, false));
}

function block_exaport_file_remove($item) {
	$fs = get_file_storage();
	// associated file (if it's a file item)
	$fs->delete_area_files(get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_file', $item->id);
	// item content (intro) inside the html editor
	$fs->delete_area_files(get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_content', $item->id);
}

/*** GENERAL FUNCTIONS **********************************************************************/

function block_exaport_require_login($courseid) {
	global $CFG;

	require_login($courseid);
	require_capability('block/exaport:use', get_context_instance(CONTEXT_SYSTEM));

	if (empty($CFG->block_exaport_allow_loginas)) {
		// login as not allowed => check
		global $USER;
		if (!empty($USER->realuser)) {
			print_error("loginasmode", "block_exaport");
		}
	}
}

function block_exaport_setup_default_categories() {
	global $DB, $USER;
	
	if (block_exaport_course_has_desp() && !$DB->record_exists('block_exaportcate', array('userid'=>$USER->id))) {
		$categories = array(
			'Erzählungen',
			'Lebenslauf',
			'Berichte, Ausstellungen',
			'Audio- u. Videoclips',
			'Begegnungen in anderen Ländern',
			'Reflexionen',
			'Zeugnisse',
			'Teilnahmebestätigungen',
			'weitere Dokumente'
		);

		$newentry = new stdClass();
		$newentry->timemodified = time();
		$newentry->userid = $USER->id;
		$newentry->pid = 0;

		foreach ($categories as $category) {
			$newentry->name = $category;
			$DB->insert_record("block_exaportcate", $newentry);
		}
	}
}

function block_exaport_get_active_version() {
    global $CFG, $DB;
    return empty($CFG->block_exaport_active_version) ? 3 : $CFG->block_exaport_active_version;
}

function block_exaport_feature_enabled($feature) {
    global $CFG;
    if ($feature == 'views')
        return block_exaport_get_active_version() >= 3;
    if ($feature == 'share_item')
        return block_exaport_get_active_version() < 3;
    if ($feature == 'copy_to_course')
        return!empty($CFG->block_exaport_feature_copy_to_course);
    die('wrong feature');
}

/*** OTHER FUNCTIONS **********************************************************************/

function block_exaport_has_categories($userid) {
    global $CFG, $DB;
    if ($DB->count_records_sql("SELECT COUNT(*) FROM {block_exaportcate} WHERE userid='$userid' AND pid=0") > 0) {
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
	
	$context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
	
	return $COURSE->has_desp = $DB->record_exists('block_instances', array('blockname'=>'desp', 'parentcontextid'=>$context->id));
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

    global $CFG, $COURSE;

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
    $tabs[] = new tabobject('categories', $CFG->wwwroot . '/blocks/exaport/view_categories.php?courseid=' . $COURSE->id, get_string("categories", "block_exaport"), '', true);
    $tabs[] = new tabobject('bookmarks', $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id, block_exaport_get_string("bookmarks"), '', true);
    if (block_exaport_feature_enabled('views')) {
        $tabs[] = new tabobject('views', $CFG->wwwroot . '/blocks/exaport/views_list.php?courseid=' . $COURSE->id, get_string("views", "block_exaport"), '', true);
    }
    $tabs[] = new tabobject('exportimport', $CFG->wwwroot . '/blocks/exaport/exportimport.php?courseid=' . $COURSE->id, get_string("exportimport", "block_exaport"), '', true);
    $tabs[] = new tabobject('sharedbookmarks', $CFG->wwwroot . '/blocks/exaport/shared_people.php?courseid=' . $COURSE->id, block_exaport_get_string("sharedbookmarks"), '', true);

    // tabs f�r das untermen�
    $tabs_sub = array();
    // ausgew�hlte tabs f�r untermen�s
    $activetabsubs = Array();

    if (strpos($item_identifier, 'bookmarks') === 0) {
        $activetabsubs[] = $item_identifier;
        $currenttab = 'bookmarks';

        // untermen� tabs hinzuf�gen
        $tabs_sub['bookmarksall'] = new tabobject('bookmarksall', s($CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id),
                        get_string("bookmarksall", "block_exaport"), '', true);
        $tabs_sub['bookmarkslinks'] = new tabobject('bookmarkslinks', s($CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id . '&type=link'),
                        get_string("bookmarkslinks", "block_exaport"), '', true);
        $tabs_sub['bookmarksfiles'] = new tabobject('bookmarksfiles', s($CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id . '&type=file'),
                        get_string("bookmarksfiles", "block_exaport"), '', true);
        $tabs_sub['bookmarksnotes'] = new tabobject('bookmarksnotes', s($CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id . '&type=note'),
                        get_string("bookmarksnotes", "block_exaport"), '', true);

        if ($sub_item_identifier) {
            $navlinks[] = array('name' => get_string($item_identifier, "block_exaport"), 'link' => $tabs_sub[$item_identifier]->link, 'type' => 'misc');

            $nav_item_identifier = $sub_item_identifier;
        }
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

    $navigation = build_navigation($navlinks);
    print_header_simple($item_name, get_string(block_exaport_course_has_desp()?"desp_pluginname":'pluginname', "block_exaport"), $navigation, "", "", true);
	
	if (block_exaport_course_has_desp()) {
		// include the desp css
		echo '<link href="'.$CFG->wwwroot.'/blocks/desp/styles.css" rel="stylesheet" type="text/css" />';
	}
	
    // header
    global $OUTPUT;
    $OUTPUT->heading("<img src=\"{$CFG->wwwroot}/blocks/exaport/pix/" . $icon . ".png\" width=\"16\" height=\"16\" alt='icon-$item_identifier' /> " . $strbookmarks . ': ' . $item_name);

    print_tabs(array($tabs, $tabs_sub), $currenttab, null, $activetabsubs);

	if (block_exaport_course_has_desp() && (strpos($currenttab,'bookmarks') === 0) ) {
		?>
		   <div id="messageboxses1" style="background: url('pix/message_ses1.gif') no-repeat left top; ">
				<div id="messagetxtses1">
					Hallo, hier ist nun meine Sammelmappe, mein Dossier. Hier kann ich anderen zeigen, was ich alles gemacht habe. Das finde ich sehr spannend. Ich kann genau sehen, wie viel ich schon gelernt habe.
				</div>
			</div>
			<div id="messageboxslp3" style="background: url('pix/message_lp.gif') no-repeat left top;margin-left: 20px;">
			
				<div id="messagetxtslp3">
					Du kannst das auch tun.
				</div>
			</div>
			<br /><br />
		<?php
	}
	
}

function block_exaport_get_string($string) {
	$manager = get_string_manager();
	
	if (block_exaport_course_has_desp() && $manager->string_exists('desp_'.$string, 'block_exaport'))
		return $manager->get_string('desp_'.$string, 'block_exaport');

	if ($manager->string_exists($string, "block_exaport"))
		return $manager->get_string($string, 'block_exaport');

	return $manager->get_string($string);
	
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

function block_exaport_parse_item_sort($sort) {
    return block_exaport_parse_sort($sort, array('date', 'name', 'category', 'type', 'sortorder'), array('date', 'desc'));
}

function block_exaport_item_sort_to_sql($sort) {
    $sort = block_exaport_parse_item_sort($sort);

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
    $sort = block_exaport_parse_view_sort($sort);

    $column = $sort[0];
    $order = $sort[1];

    $sql_sort = "v." . $column . " " . $order . ", v.timemodified DESC";

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

    $competences = $DB->get_records('block_exacompdescractiv_mm', array("activityid" => $item->id, "activitytype" => 2000));
    if ($competences)
        return true;
    else
        return false;
}

function block_exaport_build_comp_table($item, $role="teacher") {
    global $DB;

    $sql = "SELECT d.title, d.id FROM {block_exacompdescriptors} d, {block_exacompdescractiv_mm} da WHERE d.id=da.descrid AND da.activitytype=2000 AND da.activityid=" . $item->id;
    $descriptors = $DB->get_records_sql($sql);
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

    $DB->delete_records('block_exacompdescuser_mm',array("activityid"=>$item->id, "activitytype"=>2000, "role"=>$role, "userid"=>$item->userid));
    
    foreach ($values as $value) {
        $data = array(
            "activityid" => $item->id,
            "activitytype" => 2000,
            "descid" => $value,
            "userid" => $item->userid,
            "reviewerid" => $reviewerid,
            "role" => $role
        );
        print_r($data);
        $DB->insert_record('block_exacompdescuser_mm', $data);
    }
}
function block_exaport_get_competences($item, $role=1) {
    global $DB;

    return $DB->get_records('block_exacompdescuser_mm',array("userid"=>$item->userid,"role"=>$role,"activitytype"=>2000,"activityid"=>$item->id));
}
function block_exaport_build_comp_tree() {
    global $DB;
    $sql = "SELECT d.id, d.title, t.title as topic, s.title as subject FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t, {block_exacompsubjects} s, {block_exacompschooltypes} ty, {block_exacompdescrtopic_mm} dt WHERE mt.typeid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND dt.descrid=d.id";
    $descriptors = $DB->get_records_sql($sql);
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
            $tree.='<li>' . $subject;
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
        $tree.='<li><input type="checkbox" name="desc" value="' . $descriptor->id . '" alt="' . $descriptor->title . '">' . $descriptor->title . '</li>';

        $index++;
    }
    $tree.='</ul></form>';

    return $tree;
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
        foreach ($preferences as $key => $value) {
            $newuserpreferences->$key = $value;
        }
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
