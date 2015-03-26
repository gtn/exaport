<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2015 exabis internet solutions <info@exabis.at>
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
require_once dirname(__FILE__) . '/lib/resumelib.php';

$courseid = optional_param('courseid', 0, PARAM_INT);
//$userid = optional_param('userid', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_RAW);

$resume = block_exaport_get_resume_params();
// Create new resume if there isn't
if (!$resume) {
	$newresumeparams->user_id = $USER->id;
	$newresumeparams->courseid = $courseid;
	$newresumeparams->cover = get_string("resume_template_newresume", "block_exaport");
    $DB->insert_record("block_exaportresume", $newresumeparams);
};

block_exaport_require_login($courseid);

$context = context_system::instance();

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidinstance", "block_exaport");
}

$url = '/blocks/exaport/resume.php';
$PAGE->set_url($url);
block_exaport_print_header("personal", "resume");

echo "<br />";

$show_information = true;

$userpreferences = block_exaport_get_user_preferences();
$description = $userpreferences->description;

echo "<div class='block_eportfolio_center'>";

echo $OUTPUT->box(text_to_html(get_string("resume_my", "block_exaport")), 'center');

echo "</div>";

$textfieldoptions = array('trusttext'=>true, 'subdirs'=>true, 'maxfiles'=>99, 'context'=>context_user::instance($USER->id));

if ($edit) {
	$show_information = false;
    if (!confirm_sesskey()) {
        print_error("badsessionkey", "block_exaport");
    };
	$data = new stdClass();
	$data->courseid = $courseid;
	$data->edit = $edit;

	switch ($edit) {
		case 'cover':
		case 'interests':			
			$workform = new block_exaport_resume_editor_form($_SERVER['REQUEST_URI'], array('field'=>$edit));
			$data->cover = $resume->cover;
			$data->coverformat = FORMAT_HTML;
			$data->interests = $resume->interests;
			$data->interestsformat = FORMAT_HTML;
			$data = file_prepare_standard_editor($data, $edit, $textfieldoptions, context_user::instance($USER->id),
												'block_exaport', 'resume_'.$edit, $USER->id);
			$workform->set_data($data);
			if ($workform->is_cancelled()) {
				$show_information = true;
			} else if ($fromform = $workform->get_data()) { 
				$fromform = file_postupdate_standard_editor($fromform, $edit, $textfieldoptions, context_user::instance($USER->id),
															'block_exaport', 'resume_'.$edit, $USER->id);
				block_exaport_set_resume_params(array($edit => $fromform->{$edit}, 'courseid' => $fromform->courseid));
				echo $OUTPUT->box(get_string($edit."saved", "block_exaport"), 'center');
				$show_information = true;
			} else {
				$workform->display();
			};
			break;
		case 'education':
			$display_inputs = array (
				'startdate' => 'text:required',
				'enddate' => 'text',
				'institution' => 'text:required',
				'institutionaddress' => 'text',
				'qualtype' => 'text',
				'qualname' => 'text',
				'qualdescription' => 'textarea'
			);
			$workform = new block_exaport_resume_multifields_form($_SERVER['REQUEST_URI'], array('inputs'=>$display_inputs));
			$data->resume_id = $resume->id;
			$workform->set_data($data);
			if ($workform->is_cancelled()) {
				$show_information = true;
			} else if ($fromform = $workform->get_data()) { 

				print_r($fromform);
				// --------------- block_exaport_set_resume_mm(array($edit => $fromform->{$edit}, 'user_id' => $USER->id));
				echo $OUTPUT->box(get_string($edit."saved", "block_exaport"), 'center');
				$show_information = true;
			} else {
				$workform->display();
			};
			break;
		default:
			$show_information = true;
	}
};

// Resume blocks
$resume = block_exaport_get_resume_params();

if ($show_information) {

	// Cover.
	echo block_exaport_form_resume_part($courseid, 'cover', get_string('resume_cover', 'block_exaport'), $resume->cover, 'edit');

	// Education history.
	$educationhistory = '!!! EDUCATION HISTORY !!!';
	echo block_exaport_form_resume_part($courseid, 'education', get_string('resume_educationhistory', 'block_exaport'), $educationhistory, 'add');
	
	// Employment history.
	$employmenthistory = '!!! EMPLOYMENT HISTORY !!!';
	echo block_exaport_form_resume_part($courseid, 'employment', get_string('resume_employmenthistory', 'block_exaport'), $employmenthistory, 'add');
	
	// Interests.
	echo block_exaport_form_resume_part($courseid, 'interests', get_string('resume_interests', 'block_exaport'), $resume->interests, 'edit');
	
};

function block_exaport_form_resume_part($courseid = 0, $edit = '', $header = '', $content = '', $buttons = '') {
	global $CFG;
	$resume_part = '';
	$resume_part .= '<form class="mform" method="post" action="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid . '">';
	$resume_part .= '<input type="hidden" name="edit" value="'.$edit.'" />';
	$resume_part .= '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
	$resume_part .= '<fieldset class="clearfix collapsible">';
	$resume_part .= '<legend class="ftoggler">'.$header.'</legend>';
	$resume_part .= '<div class="fcontainer clearfix">';
	$resume_part .= '<div>'.$content.'</div>';
	switch ($buttons) {
		case 'edit':
				$resume_part .= '<input type="submit" value="' . get_string("edit") . '" />';
				break;
		case 'add':
				$resume_part .= '<input type="submit" value="' . get_string("add") . '" />';
				break;
	};
	$resume_part .= '</div>';
	$resume_part .= '</fieldset>';
	$resume_part .= '</form>';
	return $resume_part;
}


echo "<span class=\"left\">".get_string("supported", "block_exaport")."<br/><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/bmukk.png\" width=\"63\" height=\"24\" alt=\"bmukk\" /></span>";
echo "<span class=\"right\">".get_string("developed", "block_exaport")."<br/><a href=\"http://www.gtn-solutions.com/\"><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/gtn.png\" width=\"89\" height=\"40\" alt=\"gtn-solutions\"/></a></span>";
echo "<div class=\"block_eportfolio_clear\" />";
echo "</div>";
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
