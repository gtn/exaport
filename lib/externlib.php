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

function block_exaport_get_user_from_hash($hash)
{
	trigger_error('deprecated');
	$conditions = array("user_hash" => $hash);
	if (! $hashrecord = $DB->get_record("block_exaportuser", $conditions) )
		return false;
	else {
		$conditions = array("id" => $hashrecord->user_id);
		return $DB->get_record("user", $conditions);
	}
}

function block_exaport_print_extern_item($item, $access) {
	global $CFG, $OUTPUT;

	echo $OUTPUT->heading(format_string($item->name));

	$box_content = '';
	if ($file = block_exaport_get_item_file ( $item )) {
		$ffurl = s ( "{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=" . $access . "&itemid=" . $item->id );
		
		if ($file->is_valid_image ()) { // Image attachments don't get printed as links
			$box_content .= "<div class=\"item-detail-image\"><img src=\"$ffurl\" alt=\"" . s ( $item->name ) . "\" /></div>";
		} else {
			// echo $OUTPUT->action_link($ffurl, format_string($item->name), new popup_action ('click', $link));
			$icon = $OUTPUT->pix_icon(file_file_icon($file), '');
			$box_content .= "<p class=\"filelink\">" .$icon.' '.$OUTPUT->action_link ( $ffurl, format_string ( $item->name ), new popup_action ( 'click', $ffurl ) ) . "</p>";
			if (block_exaport_is_valid_media_by_filename ( $file->get_filename () )) {
				// Videoblock
				$box_content .= '
						<div id="video_block">
							<div id="video_content">
								<video id="video_file" class="video-js vjs-default-skin vjs-big-play-centered" 
											controls preload="auto" width="640" height="480"
											data-setup=\'{}\'>
									<source src="' . $ffurl . '" type="video/mp4" />
									<p class="vjs-no-js">To view this video please enable JavaScript, and consider upgrading to a web browser that <a href="http://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a></p>
								</video>
							</div>
							<div id="video_error" style="display: none;" class="incompatible_video">';
				$a = new stdClass ();
				$a->link = $OUTPUT->action_link ( $ffurl, format_string ( $item->name ), new popup_action ( 'click', $ffurl ) );
				$box_content .= get_string ( 'incompatible_video', 'block_exaport', $a );
				$box_content .= '</div>
						</div>';
				$box_content .= "
						<script src=\"" . $CFG->wwwroot . "/blocks/exaport/javascript/vedeo-js/exaport_video.js\"></script>";
			}
			;
		}
	}

	if (!$box_content && !$item->url) {
		$box_content = 'File not found';
	}

	$intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id, 'block_exaport', 'item_content', $access.'/itemid/'.$item->id);
	$intro = format_text($intro);
	if ($item->url) {
			$box_content .= '<p><a target="_blank" href="'.s($item->url).'">' . str_replace('http://', '', $item->url) . '</a></p>';
		}
	$box_content .= $intro;

	echo $OUTPUT->box($box_content);
}


function block_exaport_print_extcomments($itemid) {

	global $DB, $OUTPUT;
	
	$stredit = get_string('edit');
	$strdelete = get_string('delete');

	$conditions = array("itemid" => $itemid);
	$comments = $DB->get_records("block_exaportitemcomm", $conditions, 'timemodified DESC');
	if(!$comments)
		return;
	
	foreach ($comments as $comment) {
		$conditions = array("id" => $comment->userid);
		$user = $DB->get_record('user',$conditions);

		echo '<table cellspacing="0" class="forumpost blogpost blog" width="100%">';

		echo '<tr class="header"><td class="picture left">';
				echo $OUTPUT->user_picture($user);
		echo '</td>';

		echo '<td class="topic starter"><div class="author">';
		$fullname = fullname($user, $comment->userid);
		$by = new object();
		$by->name = $fullname;
		$by->date = userdate($comment->timemodified);
		print_string('bynameondate', 'forum', $by);

		echo '</div></td></tr>';

		echo '<tr><td class="left side">';

		echo '</td><td class="content">'."\n";

		echo format_text($comment->entry);

		echo '</td></tr></table>'."\n\n";
	}
}
