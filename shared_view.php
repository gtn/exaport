<?php
/***************************************************************
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
***************************************************************/

require_once dirname(__FILE__).'/inc.php';
require_once dirname(__FILE__).'/lib/sharelib.php';

global $CFG, $USER, $DB;

$access = optional_param('access', 0, PARAM_TEXT);

require_login(0, true);

$url = '/blocks/exabis_competences/shared_view.php';
$PAGE->set_url($url);
$context = get_context_instance(CONTEXT_SYSTEM);
$PAGE->set_context($context);

if (!$view = block_exaport_get_view_from_access($access)) {
	print_error("viewnotfound", "block_exaport");
}

$conditions = array("id" => $view->userid);
if (!$user = $DB->get_record("user", $conditions)) {
	print_error("nouserforid", "block_exaport");
}

$portfolioUser = block_exaport_get_user_preferences($user->id);

// read blocks
$query = "select b.*". // , i.*, i.id as itemid".
	 " FROM {block_exaportviewblock} b".
	 // " LEFT JOIN {$CFG->prefix}block_exaportitem i ON b.type='item' AND b.itemid=i.id".
	 " WHERE b.viewid = ? ORDER BY b.positionx, b.positiony";

$blocks = $DB->get_records_sql($query, array($view->id));

// read columns
$columns = array();
foreach ($blocks as $block) {
	if (!isset($columns[$block->positionx]))
		$columns[$block->positionx] = array();

	if ($block->type == 'item') {
		$conditions = array("id" => $block->itemid);
		if ($item = $DB->get_record("block_exaportitem", $conditions)) {
			$block->item = $item;
		} else {
			$block->type = 'text';
		}
	}
	$columns[$block->positionx][] = $block;
}




$CFG->stylesheets[] = dirname($_SERVER['PHP_SELF']).'/css/shared_view.css';

if ($view->access->request == 'intern') {
	block_exaport_print_header("sharedbookmarks");
} else {
	print_header(get_string("externaccess", "block_exaport"), get_string("externaccess", "block_exaport") . " " . fullname($user, $user->id));
}

echo '
<style type="text/css">


#view .view-column {
	padding-left: 10px;
}
#view .view-column-1 {
	padding-left: 0;
}

#view .view-personal-information {
	border: 1px solid #ddd;
	padding: 4px 8px;
	margin-bottom: 10px;
}
#view .view-header {
	padding: 14px 8px 3px 8px;
	margin: 0 0 10px 0;
}
#view .view-text {
	border: 1px solid #ddd;
	padding: 4px 8px;
	margin-bottom: 10px;
}

#view .view-item {
	border: 1px solid #ddd;
	padding: 4px 8px;
	margin-bottom: 10px;
	display: block;
	cursor: pointer;
}
#view .view-item:hover {
	background: #eee;
}
#view .view-item, #view .view-item * {
	color: #000;
	text-decoration: none;
}
#view .view-item-header {
	background: url(pix/bookmarksnotes.png) 0 2px no-repeat;
	padding-left: 20px;
	display: block;
}
#view .view-item-type-file .view-item-header {
	background-image: url(pix/bookmarksfiles.png);
}
#view .view-item-type-link .view-item-header {
	background-image: url(pix/bookmarkslinks.png);
}
#view .view-item-text {
	padding: 4px 0 0 0;
	display: block;
}
#view .view-item-link {
	display: block;
	padding: 4px 8px;
	text-align: right;
	font-size: 12px;
}
#view .view-item:hover .view-item-link {
	text-decoration: underline;
}
</style>';


$comp = block_exaport_check_competence_interaction();

echo '<div id="view">';
echo '<table width="100%"><tr>';
$column_num = 0;
for ($column_i = 1; $column_i<=2; $column_i++) {
	if (!isset($columns[$column_i]))
		continue;
	$column_num++;

	echo '<td class="view-column view-column-'.$column_num.'" style="width: '.floor(100/count($columns)).'%" valign="top">';
	foreach ($columns[$column_i] as $block) {
		if ($block->type == 'item') {
			$item = $block->item;

                        if($comp)
                            $has_competences = block_exaport_check_item_competences($item);

			echo '<a class="view-item view-item-type-'.$item->type.'" href="'.s('shared_item.php?access=view/'.$access.'&itemid='.$item->id.'&att='.$item->attachment).'">';
			echo '<span class="view-item-header" title="'.$item->type.'">'.$item->name;

                        // Falls Interaktion ePortfolio - competences aktiv und User ist Lehrer
                        if($comp && has_capability('block/exaport:competences', $context)) {
                            if($has_competences)
                                echo '<img align="right" src="'.$CFG->wwwroot.'/blocks/exaport/pix/application_view_tile.png" alt="competences">';
                        }
                        echo '</span>';
			$intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_content', 'view/'.$access.'/itemid/'.$item->id);
			echo '<span class="view-item-text">'.$intro.'</span>';
			echo '<span class="view-item-link">'.block_exaport_get_string('show').'</span>';
			echo '</a>';
		} elseif ($block->type == 'personal_information') {
			if(isset($portfolioUser->description)) {
				$description = file_rewrite_pluginfile_urls($portfolioUser->description, 'pluginfile.php', get_context_instance(CONTEXT_USER, $view->userid)->id, 'block_exaport', 'personal_information_view', $access);
				echo '<div class="view-personal-information">'.$description.'</div>';
			}
		} elseif ($block->type == 'headline') {
			echo '<div class="header view-header">'.nl2br($block->text).'</div>';
		} else {
			// text
			echo '<div class="view-text">';
			echo $block->text;
			echo '</div>';
		}
	}
	echo '</td>';
}
echo '</tr></table>';
echo '</div>';

echo "<br />";

echo "<div class='block_eportfolio_center'>\n";

echo "</div>\n";

echo $OUTPUT->footer();
