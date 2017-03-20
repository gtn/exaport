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

require_once __DIR__.'/inc.php';

use block_exaport\globals as g;

$courseid = required_param('courseid', PARAM_INT);
$sort = optional_param('sort', 'user', PARAM_TEXT);
$action = optional_param('action', 'list', PARAM_TEXT);

require_login($courseid);

$context = context_system::instance();
require_capability('block/exaport:use', $context);

$url = '/blocks/exaport/shared_categories.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

$parsedsort = block_exaport_parse_sort($sort, array('user', 'category'));
if ($parsedsort[0] == 'category') {
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

// Categories for user groups
$usercats = block_exaport_get_group_share_categories($USER->id);

$category_columns = g::$DB->get_column_names_prefixed('block_exaportcate', 'c');
$categories = $DB->get_records_sql("
	SELECT
		{$category_columns}, u.firstname, u.lastname, u.picture
		, COUNT(DISTINCT cshar_total.userid) AS cnt_shared_users, COUNT(DISTINCT cgshar.groupid) AS cnt_shared_groups
	FROM {user} u
	JOIN {block_exaportcate} c ON (u.id=c.userid AND c.userid!=?)
	LEFT JOIN {block_exaportcatshar} cshar ON c.id=cshar.catid AND cshar.userid=?
	LEFT JOIN {block_exaportviewgroupshar} cgshar ON c.id=cgshar.groupid
	LEFT JOIN {block_exaportcatshar} cshar_total ON c.id=cshar_total.catid
	WHERE (
		(".(block_exaport_shareall_enabled() ? 'c.shareall=1 OR ' : '')." cshar.userid IS NOT NULL) -- only show shared all, if enabled
		-- Shared for you group
		".($usercats ? " OR c.id IN (".join(',', array_keys($usercats)).") ": "")."
		)
		AND internshare = 1
	GROUP BY
		{$category_columns}, u.firstname, u.lastname, u.picture
	$sql_sort", array($USER->id, $USER->id));

if ($action == 'copy') {
	$categoryid = optional_param('categoryid', 0, PARAM_INT);

	// check if category can be accessed
	if (!isset($categories[$categoryid])) {
		throw new moodle_exception('category not found');
	}

	$category = \block_exaport\copy_category_to_myself($categoryid);
	$returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid='.$courseid.'&categoryid='.$category->id;
	redirect($returnurl);
	exit;
}

block_exaport_print_header("shared_categories");

function exaport_print_structures($categories, $parsedsort) {
	global $CFG, $courseid, $COURSE, $OUTPUT, $DB;

	$courses = exaport_get_shareable_courses_with_users('shared_views');
	$sort = $parsedsort[0];
	
	$mainStructureGroups = array(
		'thiscourse' => array(),
		'othercourses' => array()
	);
	
	if (isset($courses[$COURSE->id])) { 
		$userIdsInThisCourse = array_keys($courses[$COURSE->id]->users);
		$userIdsInThisCourse = array_keys($courses[$COURSE->id]->users);

		foreach ($categories as $structure) {
			if (in_array($structure->userid, $userIdsInThisCourse)) {
				$mainStructureGroups['thiscourse'][] = $structure;
			} else {
				$mainStructureGroups['othercourses'][] = $structure;
			}
		}
	} else {
		$mainStructureGroups['othercourses'] = $categories;
	}
	
	if ($courses) {
		echo '<span style="padding-right: 20px;">'.get_string('course').': <select id="block-exaport-courses" url="shared_categories.php?courseid=%courseid%&sort='.$sort.'">';
		// print empty line, if course is not in list
		if (!isset($courses[$COURSE->id])) echo '<option></option>';
		foreach ($courses as $c) {
			echo '<option value="'.$c->id.'"'.($c->id==$COURSE->id?' selected="selected"':'').'>'.$c->fullname.'</option>';
		}
		echo '</select></span>';
	}

	// print
	if ($categories) {
		echo get_string('sortby') . ': ';
		echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_categories.php?courseid=$courseid&amp;sort=user\"" .
		($sort == 'user' ? ' style="font-weight: bold;"' : '') . ">" . get_string('user') . "</a> | ";
		echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/shared_categories.php?courseid=$courseid&amp;sort=category\"" .
		($sort == 'category' ? ' style="font-weight: bold;"' : '') . ">" . get_string('category', 'block_exaport') . "</a>";
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
			$categoriesByUser = array();
			foreach ($mainStructureGroup as $structure) {
				if (!isset($categoriesByUser[$structure->userid])) {
					$categoriesByUser[$structure->userid] = array(
						'user' => $DB->get_record('user', array("id" => $structure->userid)),
						'structures' => array()
					);
				}
				
				$categoriesByUser[$structure->userid]['structures'][] = $structure;
			}
			
			foreach ($categoriesByUser as $item) {
				$curuser = $item['user'];
				
				$table = new html_table();
				$table->width = "100%";
				$table->size = array('40%', '20%', '20%', '20%');
				$table->head = array(
					block_exaport_get_string('category'),
					block_exaport_get_string("sharedwith"),
					'',
					'',
				);
				$table->data = array();

				foreach ($item['structures'] as $structure) {
					// print_r($structure);
					$structurecontent = '<div class="structure_head"><span class="structure_header">'.$structure->name.'</span></div>';
					$structurecontent .= '<div class="structure_content">'.block_exaport_get_structure_content($structure->id).'</div>';
					$link = '<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&type=shared&userid='.$structure->userid.'&categoryid='.$structure->id.'">
										<img src="pix/folder_32.png" /><br />'.get_string("browsecategory", "block_exaport").'</a>';
					$link2 = '<a href="shared_categories.php?courseid='.$courseid.'&action=copy&categoryid='.$structure->id.'">
										<img src="pix/folder_new_32.png" /><br />'.get_string("copycategory", "block_exaport").'</a>';
					$table->data[] = array(
						$structurecontent,
						block_exaport_get_shared_with_text($structure),
						$link,
						$link2,
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
			$table->size = array('1%', '20%', '20%', '20%', '20%', '20%');
			$table->head = array(
				'',
				get_string('user'),
				block_exaport_get_string('category'),
				block_exaport_get_string("sharedwith"),
				'',
				'',
			);
			$table->data = array();

			foreach ($mainStructureGroup as $structure) {
				$curuser = $DB->get_record('user', array("id" => $structure->userid));
				$structurecontent = '<div class="structure_head"><span class="structure_header">'.$structure->name.'</span></div>';
				$structurecontent .= '<div class="structure_content">'.block_exaport_get_structure_content($structure->id).'</div>';
				$link = '<a href="'.$CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$courseid.'&type=shared&userid='.$structure->userid.'&categoryid='.$structure->id.'">
									<img src="pix/folder_32.png" /><br />'.get_string("browsecategory", "block_exaport").'</a>';
				$link2 = '<a href="shared_categories.php?courseid='.$courseid.'&action=copy&categoryid='.$structure->id.'">
									<img src="pix/folder_new_32.png" /><br />'.get_string("copycategory", "block_exaport").'</a>';
				$table->data[] = array(
					$OUTPUT->user_picture($curuser, array("courseid" => $courseid)),
					fullname($curuser),
					$structurecontent,
					block_exaport_get_shared_with_text($structure),
					$link,
					$link2,
				);
			}

			/*
			$sorticon = $parsedsort[1] . '.png';
			$table->head[$parsedsort[0]] .= " <img src=\"pix/$sorticon\" alt='" . get_string("updownarrow", "block_exaport") . "' />";
			*/

			echo html_writer::table($table);
		}
	}
}



// list shared structures
echo "<div class='block_eportfolio_center'>\n";
echo '<div style="padding-bottom: 20px;">';
if (!$categories) {
	echo get_string("nothingstructureshared", "block_exaport");
} else {
	exaport_print_structures($categories, $parsedsort);
}

echo "</div>";
echo block_exaport_wrapperdivend();
echo block_exaport_print_footer();


function block_exaport_get_shared_with_text($structure) {
	$shared = "";
	if ($structure->shareall)
		$shared = block_exaport_get_string('sharedwith_shareall');
	elseif ($structure->cnt_shared_groups)
		$shared = block_exaport_get_string('sharedwith_group');
	elseif ($structure->cnt_shared_users > 1)
		$shared = block_exaport_get_string('sharedwith_user_cnt', $structure->cnt_shared_users);
	elseif ($structure->cnt_shared_users)
		$shared = block_exaport_get_string('sharedwith_onlyme');
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
