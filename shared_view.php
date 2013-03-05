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
	overflow: hidden;
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
	overflow: hidden;
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
	clear: both;
}
#view .view-item:hover .view-item-link {
	text-decoration: underline;
}
/* layouts */
.layout1 td {
	width: 100%;
}
.layout2 td {
	width: 50%;
}
.layout3 .td1 {
	width: 67%;
}
.layout4 .td1 {
	width: 33%;
}
.layout5 td {
	width: 33%;
}
.layout6 .td1, .layout6 .td3 {
	width: 25%;
}
.layout7 .td1, .layout7 .td3 {
	width: 15%;
}
.layout8 td {
	width: 25%;
}
.layout9 .td1, .layout9 .td4 {
	width: 20%;
}
.layout9 .td2, .layout9 .td3 {
	width: 30%;
}
.layout10 td {
	width: 20%;
}
#view .view-text {
overflow: hidden;
}

#link_thumbnail {
    margin-bottom: -625px;
    margin-right: -830px;
    width: 1024px;
    height: 768px;
    border: solid 5px #cccccc;
    -moz-transform: scale(0.2);
    -moz-transform-origin: 0 0;
    -o-transform: scale(0.2);
    -o-transform-origin: 0 0;
    -webkit-transform: scale(0.2);
    -webkit-transform-origin: 0 0;
}
</style>
<!--[if IE]> <style> #link_thumbnail{ zoom: 0.2; } </style> <![endif]-->
';


$comp = block_exaport_check_competence_interaction();

$cols_layout = array (
	"1" => 1, 	"2" => 2,	"3" => 2,	"4" => 2,	"5" => 3,	"6" => 3,	"7" => 3,	"8" => 4,	"9" => 4,	"10" => 5
);
echo '<div id="view">';
echo '<table width="100%" class="layout'.$view->layout.'"><tr>';
for ($i = 1; $i<=$cols_layout[$view->layout]; $i++) {
	echo '<td class="view-column td'.$i.'" valign="top">';
	foreach ($columns[$i] as $block) {
		if ($block->type == 'item') {
			$item = $block->item; 
                        if($comp)
                            $has_competences = block_exaport_check_item_competences($item);
			if ($item->type=="link") {
				$href = s($item->url);
				$target = '_blank';
			}
			else 
				$href = s('shared_item.php?access=view/'.$access.'&itemid='.$item->id.'&att='.$item->attachment);			
			echo '<a class="view-item view-item-type-'.$item->type.'" href="'.$href.'" target="'.$target.'">';
			// thumbnail of item
			if ($item->type=="file") {
				if ($img = $DB->get_record('files', array('contextid'=>get_context_instance(CONTEXT_USER, $item->userid)->id, 'component'=>'block_exaport', 'filearea'=>'item_file', 'itemid'=>$item->id), 'id, filename, mimetype')) {
					if (strpos($img->mimetype, "image")!==false) {					
						$img_src = $CFG->wwwroot . "/pluginfile.php/" . get_context_instance(CONTEXT_USER, $item->userid)->id . "/" . 'block_exaport' . "/" . 'item_file' . "/view/".$access."/itemid/" . $item->id."/". $img->filename;
						echo '<span class="view-item-image" style="float:right; position: relative;"><img height=100 src="'.$img_src.'"></span>';							
					};
				};		
			}
			elseif ($item->type=="link") {
				//echo '<div class="picture" style="float:right; position: relative;"><iframe id="link_thumbnail" src="'.$item->url.'" scrolling="no"></iframe></div>';
				echo '<div class="picture" style="float:right; position: relative; height: 100px; width: 100px;"><img style="max-width: 100%; max-height: 100%;" src="'.$CFG->wwwroot.'/blocks/exaport/item_thumb.php?item_id='.$item->id.'"></div>';
			};
			echo '<span class="view-item-header" title="'.$item->type.'">'.$item->name;
                        // Falls Interaktion ePortfolio - competences aktiv und User ist Lehrer
                        if($comp && has_capability('block/exaport:competences', $context)) {
                            if($has_competences)
                                echo '<img align="right" src="'.$CFG->wwwroot.'/blocks/exaport/pix/application_view_tile.png" alt="competences">';
                        }
                        echo '</span>';
			$intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_content', 'view/'.$access.'/itemid/'.$item->id);
			echo '<span class="view-item-text">'.$item->url.'<br>'.$intro.'</span>';
			echo '<span class="view-item-link">'.block_exaport_get_string('show').'</span>';
			echo '</a>';
		} elseif ($block->type == 'personal_information') {
			echo '<div class="header">'.$block->block_title.'</div>';		
			echo '<div class="view-personal-information">';
			if(isset($block->picture)) 
				echo '<div class="picture" style="float:right; position: relative;"><img src="'.$block->picture.'"></div>';
			if(isset($block->firstname) or isset($block->lastname)) {
				echo '<div class="name">';
				if(isset($block->firstname)) 			
					echo $block->firstname;
				if(isset($block->lastname)) 			
					echo ' '.$block->lastname;
				echo '</div>';
			};
			if(isset($block->email)) 			
				echo '<div class="email">'.$block->email.'</div>';
			if(isset($block->text)) 			
				echo '<div class="body">'.$block->text.'</div>';/**/
/*			if(isset($portfolioUser->description)) {
				$description = file_rewrite_pluginfile_urls($portfolioUser->description, 'pluginfile.php', get_context_instance(CONTEXT_USER, $view->userid)->id, 'block_exaport', 'personal_information_view', $access);
				echo '<div class="view-personal-information">'.$description.'</div>';
			} /**/
			echo '</div>';
		} elseif ($block->type == 'headline') {
			echo '<div class="header view-header">'.nl2br($block->text).'</div>';
		} else {
			// text
			echo '<div class="header">'.$block->block_title.'</div>';
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
