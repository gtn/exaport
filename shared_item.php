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

require_once dirname(__FILE__) . '/inc.php';
require_once dirname(__FILE__) . '/lib/sharelib.php';
require_once dirname(__FILE__) . '/lib/externlib.php';
require_once dirname(__FILE__).'/blockmediafunc.php';

global $DB, $SESSION;
$access = optional_param('access', 0, PARAM_TEXT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$commentid = optional_param('commentid', 0, PARAM_INT);
$deletecomment = optional_param('deletecomment', 0, PARAM_INT);
$backtype = optional_param('backtype', 0, PARAM_TEXT);

$context = context_system::instance();
$PAGE->set_context($context);
require_login(0, true);

$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/blocks/exaport/javascript/vedeo-js/video.js'), true);
$PAGE->requires->css('/blocks/exaport/javascript/vedeo-js/video-js.css');
$item = block_exaport_get_item($itemid, $access);
$item->intro = process_media_url($item->intro, 320, 240);

	if ($deletecomment == 1) {
		if (!confirm_sesskey()) {
			print_error("badsessionkey", "block_exaport");
		}
		$conditions = array("id" => $commentid, "userid" => $USER->id, "itemid" => $itemid);
		if ($DB->count_records("block_exaportitemcomm", $conditions) == 1) {
			$DB->delete_records("block_exaportitemcomm", $conditions);

			//parse_str($_SERVER['QUERY_STRING'], $params);
			//redirect($_SERVER['PHP_SELF'] . '?' . http_build_query(array('deletecomment' => null, 'commentid' => null, 'sesskey' => null) + (array) $params));
		} else {
				if(!isset($_POST['action'])){ //if deletecomment is set and form is submitted, comment was immediatly deleted and cant be deleted anymore, no error
			   
				print_error("commentnotfound", "block_exaport");
				//redirect($_SERVER['REQUEST_URI']);
			  }
		}
}
	
if (!$item) {
	print_error("bookmarknotfound", "block_exaport");
}

$conditions = array("id" => $item->userid);
if (!$user = $DB->get_record("user", $conditions)) {
	print_error("nouserforid", "block_exaport");
}

$url = '/blocks/exabis_competences/shared_item.php';
$PAGE->set_url($url);

if ($item->allowComments) {
	require_once("{$CFG->dirroot}/blocks/exaport/lib/item_edit_form.php");
	
	$itemExample = $DB->get_record('block_exacompitemexample', array('itemid' => $itemid));
	
	$commentseditform = new block_exaport_comment_edit_form($PAGE->url,array('gradingpermission' => block_exaport_has_grading_permission($itemid), 'itemgrade'=>($itemExample->teachervalue) ? $itemExample->teachervalue : 0));

	if ($commentseditform->is_cancelled()
		);
	else if ($commentseditform->no_submit_button_pressed()
		);
	else if ($fromform = $commentseditform->get_data()) {
		switch ($action) {
			case 'add':
				block_exaport_do_add_comment($item, $fromform, $commentseditform);
				
				//redirect(str_replace("&deletecomment=1","",$_SERVER['REQUEST_URI']));
				$prms='access='.$access.'&itemid='.$itemid;
				if (!empty($backtype)) $prms.='backtype='.$backtype;
				redirect($CFG->wwwroot.'/blocks/exaport/shared_item.php?'.$prms);
				break;
		}
	}
}

if ($item->access->page == 'view') {
	if ($item->access->request == 'intern') {
		block_exaport_print_header("views");
	} else { 
		block_exaport_print_header("sharedbookmarks");
		// print_header(get_string("externaccess", "block_exaport"), get_string("externaccess", "block_exaport") . " " . fullname($user, $user->id));
		echo block_exaport_wrapperdivstart();
	}
} elseif ($item->access->page == 'portfolio') {
	if ($item->access->request == 'intern') {
		if ($backtype && ($item->userid == $USER->id)) {
			block_exaport_print_header("bookmarks" . block_exaport_get_plural_item_type($backtype));
		} else {
			block_exaport_print_header("sharedbookmarks");
		}
	} else {
		block_exaport_print_header("sharedbookmarks");
		// print_header(get_string("externaccess", "block_exaport"), get_string("externaccess", "block_exaport") . " " . fullname($user, $user->id));
		echo block_exaport_wrapperdivstart();
	}
}

//IF FORM DATA -> INSERT
if(isset($_POST['data'])) {
	foreach ($_POST['data'] as $key => $desc) {
		if (!empty($_POST['data'][$key])) {
				// Die Einträge in ein Array speichern
				$values[] = $key;
		}
	}
	block_exaport_set_competences($values, $item, $USER->id);
}
echo "<div>\n";




block_exaport_print_extern_item($item, $access);

if ($item->allowComments) {
	$newcomment = new stdClass();
	$newcomment->action = 'add';
	$newcomment->courseid = $COURSE->id;
	$newcomment->timemodified = time();
	$newcomment->itemid = $itemid;
	$newcomment->userid = $USER->id;
	$newcomment->access = $access;
	$newcomment->backtype = $backtype;

	block_exaport_show_comments($item);

	$commentseditform->set_data($newcomment);
	//$commentseditform->_form->_attributes['action'] = $_SERVER['REQUEST_URI'];
	$commentseditform->display();
} elseif ($item->showComments) {
	block_exaport_print_extcomments($item->id);
}

if ($item->access->page == 'view') {
	$backlink = 'shared_view.php?access=' . $item->access->parentAccess;
} else {
	// intern
	if ($item->userid == $USER->id) {
		$backlink = '';
	}
	$backlink = '';
	// extern.php?id=$id
}


if (block_exaport_check_competence_interaction ()) {
//begin
	$has_competences = block_exaport_check_item_competences($item);
	//if ($has_competences && has_capability('block/exaport:competences', $context)) {
	if($has_competences){
		//für alle rollen? Keine interaktion?
		//echo get_string("teachercomps","block_exaport");
		block_exaport_build_comp_table($item);
	}
	//end
} else
	$has_competences = false;
if ($backlink) {
	echo "<br /><a href=\"{$CFG->wwwroot}/blocks/exaport/" . $backlink . "\">" . get_string("back", "block_exaport") . "</a><br /><br />";
}

echo "</div>";
echo block_exaport_wrapperdivend();

echo $OUTPUT->footer();

function block_exaport_show_comments($item) {
	global $CFG, $USER, $COURSE, $DB, $OUTPUT;
	$conditions = array("itemid" => $item->id);
	$comments = $DB->get_records("block_exaportitemcomm", $conditions, 'timemodified DESC');

	if ($comments) {
		foreach ($comments as $comment) {
			$stredit = get_string('edit');
			$strdelete = get_string('delete');

			$conditions = array("id" => $comment->userid);
			$user = $DB->get_record('user', $conditions);

			echo '<table cellspacing="0" class="forumpost blogpost blog" width="100%">';

			echo '<tr class="header"><td class="picture left">';
			echo $OUTPUT->user_picture($user);
			echo '</td>';

			echo '<td class="topic starter"><div class="author">';
			$fullname = fullname($user, $comment->userid);
			$by = new object();
			$by->name = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' .
					$user->id . '&amp;course=' . $COURSE->id . '">' . $fullname . '</a>';
			$by->date = userdate($comment->timemodified);
			print_string('bynameondate', 'forum', $by);

			if ($comment->userid == $USER->id) {
				echo ' - <a href="' . s($_SERVER['REQUEST_URI'] . '&commentid=' . $comment->id . '&deletecomment=1&sesskey=' . sesskey()) . '">' . get_string('delete') . '</a>';
			}
			echo '</div></td></tr>';

			echo '<tr><td class="left side">';

			echo '</td><td class="content">' . "\n";

			echo format_text($comment->entry);

			echo '</td></tr></table>' . "\n\n";
		}
	}
}

function block_exaport_do_add_comment($item, $post, $blogeditform) {
	global $CFG, $USER, $COURSE, $DB;

	$post->userid = $USER->id;
	$post->timemodified = time();
	$post->course = $COURSE->id;
	$post->entry=$post->entry["text"];
   
	if(block_exaport_has_grading_permission($item->id) && isset($post->itemgrade)) {
		$itemExample = $DB->get_record('block_exacompitemexample',array('itemid'=>$item->id));
		$itemExample->teachervalue = $post->itemgrade;
		$DB->update_record('block_exacompitemexample', $itemExample);
		
		// check for example additional info and set it
		$exampleEval = $DB->get_record('block_exacompexameval', array('courseid'=>$item->courseid,'exampleid'=>$itemExample->exampleid,'studentid'=>$item->userid));
		$exampleEval->additionalinfo = $post->itemgrade;
		$DB->update_record('block_exacompexameval', $exampleEval);
	}
		
	// Insert the new blog entry.
	if ($DB->insert_record('block_exaportitemcomm', $post)) {
		block_exaport_add_to_log(SITEID, 'exaport', 'add', 'view_item.php?type=' . $item->type, $post->entry);
	} else {
		error('There was an error adding this post in the database');
	}
}
