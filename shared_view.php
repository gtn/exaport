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

global $CFG, $USER, $DB, $PAGE;

$access = optional_param('access', 0, PARAM_TEXT);

require_login(0, true);

$url = '/blocks/exabis_competences/shared_view.php';
$PAGE->set_url($url);
$context = context_system::instance();
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



if ($view->access->request == 'intern') {
	block_exaport_print_header("sharedbookmarks");
}else {
	$PAGE->requires->css('/blocks/exaport/css/shared_view.css');
	$PAGE->set_title(get_string("externaccess", "block_exaport"));
	$PAGE->set_heading( get_string("externaccess", "block_exaport") . " " . fullname($user, $user->id));
	
	echo $OUTPUT->header();
	echo block_exaport_wrapperdivstart();
}

?>
<script type="text/javascript">
//<![CDATA[
	jQueryExaport(function($){
		$('.view-item').click(function(event){
			if ($(event.target).is('a')) {
				// ignore if link was clicked
				return;
			}
			
			var link = $(this).find('.view-item-link a');
			if (link.length)
				document.location.href = link.attr('href');
		});
	});
//]]>
</script>
<?php

$comp = block_exaport_check_competence_interaction();

$cols_layout = array (
	"1" => 1, 	"2" => 2,	"3" => 2,	"4" => 2,	"5" => 3,	"6" => 3,	"7" => 3,	"8" => 4,	"9" => 4,	"10" => 5
);
if (!isset($view->layout) || $view->layout==0) 
	$view->layout = 2;
echo '<div id="view">';
echo '<table class="table_layout layout'.$view->layout.'"><tr>';
for ($i = 1; $i<=$cols_layout[$view->layout]; $i++) {
	echo '<td class="view-column td'.$i.'">';
	if (isset($columns[$i]))
	foreach ($columns[$i] as $block) {
		if ($block->text)
			$block->text = file_rewrite_pluginfile_urls($block->text, 'pluginfile.php', context_user::instance($USER->id)->id, 'block_exaport', 'view_content', $access);

		if ($block->type == 'item') {
			$item = $block->item; 
			$has_competences = false;

			if($comp){
				$has_competences = block_exaport_check_item_competences($item);
			
				if($has_competences){
					$array = block_exaport_get_competences($item, 0);
	
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
			
			$href = 'shared_item.php?access=view/'.$access.'&itemid='.$item->id.'&att='.$item->attachment;
			
			echo '<div class="view-item view-item-type-'.$item->type.'">';
			// thumbnail of item
			if ($item->type=="file") {
				$select = "contextid='".context_user::instance($item->userid)->id."' AND component='block_exaport' AND filearea='item_file' AND itemid='".$item->id."' AND filesize>0 ";	
//				if ($img = $DB->get_record('files', array('contextid'=>get_context_instance(CONTEXT_USER, $item->userid)->id, 'component'=>'block_exaport', 'filearea'=>'item_file', 'itemid'=>$item->id, 'filesize'=>'>0'), 'id, filename, mimetype')) {
				if ($img = $DB->get_record_select('files', $select, null, 'id, filename, mimetype')) {
					if (strpos($img->mimetype, "image")!==false) {					
						$img_src = $CFG->wwwroot . "/pluginfile.php/" . context_user::instance($item->userid)->id . "/" . 'block_exaport' . "/" . 'item_file' . "/view/".$access."/itemid/" . $item->id."/". $img->filename;
						echo '<div class="view-item-image"><img height="100" src="'.$img_src.'" alt=""/></div>';
					};
				};		
			}
			elseif ($item->type=="link") {				
				echo '<div class="picture" style="float:right; position: relative; height: 100px; width: 100px;"><a href="'.$href.'"><img style="max-width: 100%; max-height: 100%;" src="'.$CFG->wwwroot.'/blocks/exaport/item_thumb.php?item_id='.$item->id.'" alt=""/></a></div>';
			};			
			echo '<div class="view-item-header" title="'.$item->type.'">'.$item->name;
                        // Falls Interaktion ePortfolio - competences aktiv und User ist Lehrer
                        if($comp && has_capability('block/exaport:competences', $context)) {
                            if($has_competences)
                                echo '<img align="right" src="'.$CFG->wwwroot.'/blocks/exaport/pix/application_view_tile.png" alt="competences"/>';
                        }
                        echo '</div>';
			$intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id, 'block_exaport', 'item_content', 'view/'.$access.'/itemid/'.$item->id);
			echo '<div class="view-item-text">';
			if ($item->url) {
				// link
				echo '<a href="'.s($item->url).'" target="_blank">'.str_replace('http://', '', $item->url).'</a><br />';
			}
			echo $intro.'</div>';
			if($has_competences) echo '<div class="view-item-competences"><script type="text/javascript" src="javascript/wz_tooltip.js"></script><a onmouseover="Tip(\''.$item->competences.'\')" onmouseout="UnTip()"><img src="'.$CFG->wwwroot.'/blocks/exaport/pix/comp.png" class="iconsmall" alt="'.'competences'.'" /></a></div>';
			echo '<div class="view-item-link"><a href="'.s($href).'">'.block_exaport_get_string('show').'</a></div>';
			echo '</div>';
		} elseif ($block->type == 'personal_information') {
			echo '<div class="header">'.$block->block_title.'</div>';		
			echo '<div class="view-personal-information">';
			if(isset($block->picture)) 
				echo '<div class="picture" style="float:right; position: relative;"><img src="'.$block->picture.'" alt=""/></div>';
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
		} elseif ($block->type == 'media') {
			echo '<div class="header view-header">'.nl2br($block->block_title).'</div>';		
			echo '<div class="view-media">';
			if (!empty($block->contentmedia))
				echo $block->contentmedia;
			echo '</div>';

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
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
