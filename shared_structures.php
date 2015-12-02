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

require_once __DIR__.'/inc.php';
require_once __DIR__.'/lib/sharelib.php';
require_once $CFG->libdir . '/formslib.php';

global $OUTPUT, $CFG;

$courseid = required_param('courseid', PARAM_INT);
$sort = optional_param('sort', 'user', PARAM_TEXT);
$action = optional_param('action', 'list', PARAM_TEXT);
$structureid = optional_param('id', 0, PARAM_INT);

$u = optional_param('u',0, PARAM_INT);
require_login($courseid);

$context = context_system::instance();
require_capability('block/exaport:use', $context);

$url = '/blocks/exabis_competences/shared_structures.php';
$PAGE->set_url($url);


$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
	error("That's an invalid course id");
}

$parsedsort = block_exaport_parse_sort($sort, array('course', 'user', 'structure'));
if ($parsedsort[0] == 'structure') {
	$sql_sort = " ORDER BY c.name, u.lastname, u.firstname";
	if(strcmp($CFG->dbtype, "sqlsrv")==0){
		$sql_sort = " ORDER BY cast(c.name AS varchar(max)), u.lastname, u.firstname";
	}
} else {
	$sql_sort = " ORDER BY u.lastname, u.firstname, c.name";
	if(strcmp($CFG->dbtype, "sqlsrv")==0){
		$sql_sort = " ORDER BY u.lastname, u.firstname, cast(c.name AS varchar(max))";
	}
}

// Copy action
if (optional_param('cancel', '', PARAM_TEXT)<>'')
	$action = 'list';
if ($action == 'copy' && is_sharablestructure($USER->id, $structureid) && optional_param('submitbutton', '', PARAM_TEXT)<>'') {	
	block_exaport_copystructure($USER->id, $structureid, required_param('categorytargetid', PARAM_INT));
	$action = 'list';
	$returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid='.$courseid;
	redirect($returnurl);
}

block_exaport_print_header("sharedstructures");

$strheader = get_string("sharedstructures", "block_exaport");


if ($u > 0) {
	$whre=" AND u.id=".$u;
}
else 
	$whre = "";

// Sections for user groups
$usergroups = $DB->get_records('groups_members', array('userid' => $USER->id), '', 'groupid');
if ((is_array($usergroups)) && (count($usergroups) > 0)) { 
	foreach ($usergroups as $id => $group) {
		$usergroups[$id] = $group->groupid;
	};
	$usergroups_list = implode(',', $usergroups);
	// print_r($usergroups_list);
	$userstructures = $DB->get_records_sql('SELECT * FROM {block_exaportcat_strgrshar} WHERE groupid IN ('.$usergroups_list.')');
	foreach ($userstructures as $id => $category) {
		 $userstructures_arr[$id] = $category->catid;
	};
	$userstructures_list = implode(',', $userstructures_arr);
}; 
// $DB->set_debug(true);
$structures = $DB->get_records_sql(
				"SELECT c.*, u.firstname, u.lastname, u.picture, COUNT(DISTINCT cshar_total.userid) AS cnt_shared_users, COUNT(DISTINCT cgshar.groupid) AS cnt_shared_groups  " .
				" FROM {user} u" .
				" JOIN {block_exaportcate} c ON (u.id=c.userid AND c.userid!=?)" .
				" LEFT JOIN {block_exaportcat_structshar} cshar ON c.id=cshar.catid AND cshar.userid = ?".
				" LEFT JOIN {block_exaportcat_strgrshar} cgshar ON c.id=cgshar.catid ".
				" LEFT JOIN {block_exaportcat_structshar} cshar_total ON c.id=cshar_total.catid " .
				" WHERE ".
					"(".(block_exaport_shareall_enabled() ? ' c.structure_shareall=1 OR ' : '')." cshar.userid IS NOT NULL) ". 
					// Shared for you group
					// ((is_array($usergroups)) ? " u.id IN (".$usergroups_list.") " : "").
					(isset($userstructures) && count($userstructures)>0 ? " OR c.id IN (".$userstructures_list.") ": "") . // Add group sharing views
					" ". // don't show my own views
				$whre .
				" GROUP BY c.id, c.userid, c.name, c.description, c.timemodified, c.structure_shareall, u.firstname, u.lastname, u.picture".
				" $sql_sort", array($USER->id, $USER->id));
// print_r($structures);
// $DB->set_debug(false); 

class block_exaport_copystructure_form extends moodleform {

	function definition() {
		global $CFG, $USER, $DB;

		$mform = & $this->_form;
		
		$mform->addElement('select', 'categorytargetid', get_string("copytocategory", "block_exaport"), array());
		$mform->addRule('categorytargetid', get_string("categorynotempty", "block_exaport"), 'required', null, 'client');
		$mform->setDefault('categorytargetid', 0);
		$this->category_select_setup();
		
		$this->add_action_buttons(true, get_string('copystructure', 'block_exaport'));
	}

	function category_select_setup() {
			global $CFG, $USER, $DB;
			$mform = & $this->_form;
			$categorysselect = & $mform->getElement('categorytargetid');
			$categorysselect->removeOptions();

			$conditions = array("userid" => $USER->id, "pid" => 0);
			$outercategories = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name asc");
			$categories = array(
					0 => block_exaport_get_root_category()->name
			);
			if ($outercategories) {
				$categories = $categories + rek_category_select_setup($outercategories, " ", $categories);
			}
			$categorysselect->loadArray($categories);
		}
}


function exaport_print_structures($structures, $parsedsort) {
	global $CFG, $courseid, $COURSE, $OUTPUT, $DB;

	$courses = exaport_get_shareable_courses_with_users('shared_views');
	$sort = $parsedsort[0];
	
	$mainStructureGroups = array(
		'thiscourse' => array(),
		'othercourses' => array()
	);
	
	if (isset($courses[$COURSE->id])) { 
		$userIdsInThisCourse = array_keys($courses[$COURSE->id]->users);
		
		foreach ($structures as $structure) {
			if (in_array($structure->userid, $userIdsInThisCourse)) {
				$mainStructureGroups['thiscourse'][] = $structure;
			} else {
				$mainStructureGroups['othercourses'][] = $structure;
			}
		}
	} else {
		$mainStructureGroups['othercourses'] = $structures;
	}
	
	if ($courses) {
		echo '<span style="padding-right: 20px;">'.get_string('course').': <select id="block-exaport-courses" url="shared_structures.php?courseid=%courseid%&sort='.$sort.'">';		
		// print empty line, if course is not in list
		if (!isset($courses[$COURSE->id])) echo '<option></option>';
		foreach ($courses as $c) {
			echo '<option value="'.$c->id.'"'.($c->id==$COURSE->id?' selected="selected"':'').'>'.$c->fullname.'</option>';
		}
		echo '</select></span>';
	}

	// print
	if ($structures) {
		echo get_string('sortby') . ': ';
		echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_structures.php?courseid=$courseid&amp;sort=user\"" .
		($sort == 'user' ? ' style="font-weight: bold;"' : '') . ">" . get_string('user') . "</a> | ";
		echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_structures.php?courseid=$courseid&amp;sort=structure\"" .
		($sort == 'structure' ? ' style="font-weight: bold;"' : '') . ">" . get_string('structure', 'block_exaport') . "</a> | ";
		// echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_views.php?courseid=$courseid&amp;sort=timemodified&amp;onlyexternal=".$onlyexternal."\"" .
		// ($sort == 'timemodified' ? ' style="font-weight: bold;"' : '') . ">" . get_string('date', 'block_exaport') . "</a> ";
		echo '</div>';
	}

	foreach ($mainStructureGroups as $mainStructureGroupId=>$mainStructureGroup) {
		if (empty($mainStructureGroup) && ($mainStructureGroupId != 'thiscourse')) {
			// don't print if no structures
			continue;
		}

		// header
		echo '<h2>'.get_string($mainStructureGroupId,'block_exaport').'</h2>';
		
		if (empty($mainStructureGroup)) {
			// print for this course only
			echo get_string("nothingstructureshared", "block_exaport");
			continue;
		}
	
		if ($sort == 'user') {
			// group by user
			$structuresByUser = array();
			foreach ($mainStructureGroup as $structure) {
				if (!isset($structuresByUser[$structure->userid])) {
					$structuresByUser[$structure->userid] = array(
						'user' => $DB->get_record('user', array("id" => $structure->userid)),
						'structures' => array()
					);
				}
				
				$structuresByUser[$structure->userid]['structures'][] = $structure;
			}
			
			foreach ($structuresByUser as $item) {
				$curuser = $item['user'];
				
				$table = new html_table();
				$table->width = "100%";
				$table->size = array('50%', '25%', '25%');
				$table->head = array(
					'structure' => block_exaport_get_string('structure'),
					'linktocopy' => '',
					'sharedwith' => block_exaport_get_string("sharedwith")
				);
				$table->data = array();

				foreach ($item['structures'] as $structure) {
					// print_r($structure);
					$structurecontent = '<div class="structure_head"><span class="structure_header">'.$structure->name.'</span></div>';
					$structurecontent .= '<div class="structure_content">'.block_exaport_get_structure_content($structure->id).'</div>';
					$linktocopy = '<a href="'.$CFG->wwwroot.'/blocks/exaport/shared_structures.php?courseid='.$courseid.'&id='.$structure->id.'&action=copy">
									<img src="pix/folder_new_32.png" /><br />'.get_string("copystructure", "block_exaport").'</a>';
					$table->data[] = array(
						$structurecontent,
						$linktocopy,
						block_exaport_get_shared_with_text($structure)
					);
				}

				echo '<div class="view-group">';
				echo '<div class="header view-group-header" style="align: right">';
				echo '<span class="view-group-pic">'.$OUTPUT->user_picture($curuser, array('link'=>false)).'</span>';
				echo '<span class="view-group-title">'.fullname($curuser).' ('.count($item['structures']).') </span>';
				echo '</div>';

				echo '<div class="view-group-content">';
				echo html_writer::table($table);
				echo '</div>';

				echo '</div>';
			}
		} else {
			$table = new html_table();
			$table->width = "100%";
			$table->size = array('1%', '25%', '25%', '25%', '24%');
			$table->head = array(
				'userpic' => '',
				'user' => get_string('user'),
				'structure' => block_exaport_get_string('structure'),
				'linktocopy' => '',
				'sharedwith' => block_exaport_get_string("sharedwith"),
			);
			$table->data = array();

			foreach ($mainStructureGroup as $structure) {
				$curuser = $DB->get_record('user', array("id" => $structure->userid));
				$structurecontent = '<div class="structure_head"><span class="structure_header">'.$structure->name.'</span></div>';
				$structurecontent .= '<div class="structure_content">'.block_exaport_get_structure_content($structure->id).'</div>';
				$linktocopy = '<a href="'.$CFG->wwwroot.'/blocks/exaport/shared_structures.php?courseid='.$courseid.'&id='.$structure->id.'&action=copy">
									<img src="pix/folder_new_32.png" /><br />'.get_string("copystructure", "block_exaport").'</a>';
				$table->data[] = array(
					$OUTPUT->user_picture($curuser, array("courseid" => $courseid)),
					fullname($structure),
					$structurecontent,
					$linktocopy,
					block_exaport_get_shared_with_text($structure)
				);
			}

			$sorticon = $parsedsort[1] . '.png';
			$table->head[$parsedsort[0]] .= " <img src=\"pix/$sorticon\" alt='" . get_string("updownarrow", "block_exaport") . "' />";

			echo html_writer::table($table);
		}
	}
}



// copy action Form
if ($action == 'copy' && is_sharablestructure($USER->id, $structureid)) {	
	echo get_string("copystructureconfirmation", "block_exaport");
	$curr_structure = $DB->get_record('block_exaportcate', array('id' => $structureid));
	echo '<div>';
	echo '<span class="structure_header">'.$curr_structure->name.'</span>';
	echo block_exaport_get_structure_content($structureid);
	
	$editform = new block_exaport_copystructure_form($CFG->wwwroot.'/blocks/exaport/shared_structures.php?courseid='.$courseid.'&id='.$structureid.'&action=copy');
	$editform->display();
	
	// echo '<input type="hidden" value="'.sesskey().'" name="sesskey">';
	// echo '<input type="submit" id="id_submitbutton" type="submit" value="'.get_string('copystructure', 'block_exaport').'" name="submitbutton">';
	// echo '<input type="submit" id="id_cancel" class="btn-cancel" onclick="skipClientValidation = true; return true;" value="'.get_string('cancel').'" name="cancel">';
	// echo '</form>'
	echo '</div>';
};
	
// list shared structures	
if ($action == 'list') {
	echo "<div class='block_eportfolio_center'>\n";
	echo '<div style="padding-bottom: 20px;">';
	if (!$structures) {
		echo get_string("nothingstructureshared", "block_exaport");
	} else {
		exaport_print_structures($structures, $parsedsort);
	}
}

echo "";

echo "</div>";
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);


function block_exaport_get_shared_with_text($structure) {
	$shared = "";
	if ($structure->structure_shareall == 1)
		$shared = block_exaport_get_string('sharedwith_shareall');
	elseif ($structure->cnt_shared_groups > 1)
		$shared .= block_exaport_get_string('sharedwith_groupand', $structure->cnt_shared_groups-1);
	elseif ($structure->cnt_shared_groups == 1) 
		$shared = block_exaport_get_string('sharedwith_group');
	elseif ($structure->cnt_shared_users > 1)
		$shared = block_exaport_get_string('sharedwith_meand', $structure->cnt_shared_users-1);
	elseif ($structure->cnt_shared_users == 1)
		$shared = block_exaport_get_string('sharedwith_onlyme'); /**/
	return $shared;
}

// shared structure as a tree
function block_exaport_get_structure_content($categoryid) {
	global $DB;
	$content = '<ul>';
	$curr_cat = $DB->get_records("block_exaportcate", array('pid' => $categoryid));
	foreach ($curr_cat as $id => $category) {
		$content .= '<li>'.$category->name.'</li>';
		$content .= block_exaport_get_structure_content($id);
	}
	$content .= '</ul>';
	return $content;
}

// copy shared structure tree to user
function block_exaport_copystructure($userid, $catid, $parentcatid = 0) {
	global $DB;
	$curr_cat = $DB->get_record("block_exaportcate", array('id' => $catid));
	$curr_cat->pid = $parentcatid;
	$curr_cat->userid = $userid;
	$curr_cat->shareall = 0;
	$curr_cat->internshare = 0;
	$curr_cat->structure_shareall = 0;
	$curr_cat->structure_share = 0;
	$new_catid = $DB->insert_record("block_exaportcate", $curr_cat, true);
	
	$children = $DB->get_records("block_exaportcate", array('pid' => $catid));
	foreach ($children as $id => $category) {
		block_exaport_copystructure($userid, $category->id, $new_catid);
	}
	return true;
}

function rek_category_select_setup($outercategories, $entryname, $categories){
	global $DB, $USER;
	foreach ($outercategories as $curcategory) {
		$categories[$curcategory->id] = $entryname.format_string($curcategory->name);
		$name = $entryname.format_string($curcategory->name);

		$conditions = array("userid" => $USER->id, "pid" => $curcategory->id);
		$inner_categories = $DB->get_records_select("block_exaportcate", "userid = ? AND pid = ?", $conditions, "name asc");
		if ($inner_categories) {
			$categories = rek_category_select_setup($inner_categories, $name.' &rArr; ', $categories);
		}
	}
	return $categories;
}