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
require_once __DIR__.'/lib/externlib.php';
require_once __DIR__.'/blockmediafunc.php';

$access = optional_param('access', 0, PARAM_TEXT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$commentid = optional_param('commentid', 0, PARAM_INT);
$comment_delete = optional_param('comment_delete', 0, PARAM_INT);
$backtype = optional_param('backtype', 0, PARAM_TEXT);

$context = context_system::instance();
$PAGE->set_context($context);
require_login(0, true);

$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/blocks/exaport/javascript/vedeo-js/video.js'), true);
$PAGE->requires->css('/blocks/exaport/javascript/vedeo-js/video-js.css');
$item = block_exaport_get_item($itemid, $access);

if (!$item) {
	print_error("bookmarknotfound", "block_exaport");
}

$item->intro = process_media_url($item->intro, 320, 240);

if ($comment_delete) {
	require_sesskey();

	$conditions = array("id" => $commentid, "userid" => $USER->id, "itemid" => $itemid);
	if ($DB->count_records("block_exaportitemcomm", $conditions) == 1) {
		$DB->delete_records("block_exaportitemcomm", $conditions);

		$fs = get_file_storage();
		if ($file = block_exaport_get_item_comment_file($commentid)) {
			// this deletes the file and the directory entry
			$fs->delete_area_files($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid());
		}

		//parse_str($_SERVER['QUERY_STRING'], $params);
		//redirect($_SERVER['PHP_SELF'] . '?' . http_build_query(array('comment_delete' => null, 'commentid' => null, 'sesskey' => null) + (array) $params));
	} else {
		if(!isset($_POST['action'])){ //if comment_delete is set and form is submitted, comment was immediatly deleted and cant be deleted anymore, no error
			print_error("commentnotfound", "block_exaport");
			//redirect($_SERVER['REQUEST_URI']);
		}
	}
}
	
$conditions = array("id" => $item->userid);
if (!$user = $DB->get_record("user", $conditions)) {
	print_error("nouserforid", "block_exaport");
}

$url = '/blocks/exaport/shared_item.php';
$PAGE->set_url($url);

if ($item->allowComments) {
	require_once("{$CFG->dirroot}/blocks/exaport/lib/item_edit_form.php");

	if (block_exaport_check_competence_interaction()) {
		$itemExample = $DB->get_record('block_exacompitemexample', array('itemid' => $itemid));
		$teacherValue = $itemExample ? $itemExample->teachervalue : 0;
	} else {
		$teacherValue = 0;
	}
	
	$commentseditform = new block_exaport_comment_edit_form($PAGE->url,array('gradingpermission' => block_exaport_has_grading_permission($itemid), 'itemgrade'=>$teacherValue));

	if ($commentseditform->is_cancelled()
		);
	else if ($commentseditform->no_submit_button_pressed()
		);
	else if ($fromform = $commentseditform->get_data()) {
		switch ($action) {
			case 'add':
				block_exaport_do_add_comment($item, $fromform);

				//redirect(str_replace("&comment_delete=1","",$_SERVER['REQUEST_URI']));
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
		block_exaport_print_header("shared_views");
		// print_header(get_string("externaccess", "block_exaport"), get_string("externaccess", "block_exaport") . " " . fullname($user, $user->id));
	}
} elseif ($item->access->page == 'portfolio') {
	if ($item->userid == $USER->id) {
		block_exaport_print_header("myportfolio");
	} else {
		block_exaport_print_header("shared_categories");
	}
}

echo block_exaport_wrapperdivstart();

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

	block_exaport_show_comments($item, $access);

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

function block_exaport_show_comments($item, $access) {
	global $CFG, $USER, $COURSE, $DB, $OUTPUT;
	$conditions = array("itemid" => $item->id);
	$comments = $DB->get_records("block_exaportitemcomm", $conditions, 'timemodified DESC');

	if ($comments) {
		foreach ($comments as $comment) {
			$conditions = array("id" => $comment->userid);
			$user = $DB->get_record('user', $conditions);

			echo '<table cellspacing="0" class="forumpost blogpost blog" width="100%">';

			echo '<tr class="header"><td class="picture left">';
			echo $OUTPUT->user_picture($user);
			echo '</td>';

			echo '<td class="topic starter"><div class="author">';
			$fullname = fullname($user, $comment->userid);
			$by = new stdClass();
			$by->name = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' .
					$user->id . '&amp;course=' . $COURSE->id . '">' . $fullname . '</a>';
			$by->date = userdate($comment->timemodified);
			print_string('bynameondate', 'forum', $by);

			if ($comment->userid == $USER->id) {
				echo ' - <a href="' . s($_SERVER['REQUEST_URI'] . '&commentid=' . $comment->id . '&comment_delete=1&sesskey=' . sesskey()) . '" onclick="'.s('return confirm('.json_encode(block_exaport\get_string('comment_delete_confirmation')).')').'">' . get_string('delete') . '</a>';
			}
			echo '</div></td></tr>';

			echo '<tr><td class="left side">';

			echo '</td><td class="content">' . "\n";

			echo format_text($comment->entry);

			if ($file = block_exaport_get_item_comment_file($comment->id)) {
				$fileurl = $CFG->wwwroot."/blocks/exaport/portfoliofile.php?access={$access}&itemid={$item->id}&commentid={$comment->id}";
				echo '</td></tr><tr><td class="left side">';

				echo '</td><td class="content">' . "\n";
				echo get_string('file', 'block_exaport').': <a href="'.s($fileurl).'" target="_blank">'.$file->get_filename().'</a> ('.display_size($file->get_filesize()).')';
			}

			echo '</td></tr></table>' . "\n\n";
		}
	}
}

function block_exaport_do_add_comment($item, $post) {
	global $USER, $COURSE, $DB;

	$post->userid = $USER->id;
	$post->timemodified = time();
	$post->course = $COURSE->id;
	$post->entry=$post->entry["text"];
   
	if(block_exaport_has_grading_permission($item->id) && isset($post->itemgrade)) {
		$itemExample = $DB->get_record('block_exacompitemexample',array('itemid'=>$item->id));
		if ($itemExample) {
			$itemExample->teachervalue = $post->itemgrade;
			$DB->update_record('block_exacompitemexample', $itemExample);

			// check for example additional info and set it
			$exampleEval = $DB->get_record('block_exacompexameval', array('courseid' => $item->courseid, 'exampleid' => $itemExample->exampleid, 'studentid' => $item->userid));
			if ($exampleEval) {
				$exampleEval->additionalinfo = $post->itemgrade;
				$DB->update_record('block_exacompexameval', $exampleEval);
			}
		}
	}
		
	// Insert the new comment
	$post->id = $DB->insert_record('block_exaportitemcomm', $post);

	file_save_draft_area_files($post->file, context_system::instance()->id, 'block_exaport', 'item_comment_file', $post->id, array('subdirs' => 0, 'maxfiles' => 1));

	block_exaport_add_to_log(SITEID, 'exaport', 'add', 'view_item.php?type=' . $item->type, $post->entry);
}
