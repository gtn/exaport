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
$confirm = optional_param("confirm", "", PARAM_BOOL);
$edit = optional_param('edit', 0, PARAM_RAW);
$delete = optional_param('delete', 0, PARAM_RAW);
$sortchange = optional_param('sortchange', 0, PARAM_RAW);
$id = optional_param('id', 0, PARAM_INT);
$opened = optional_param('opened', '', PARAM_RAW); // Which block will be open
$xmleuropass = optional_param('xmleuropass', 0, PARAM_INT);

$resume = block_exaport_get_resume_params();
// Create new resume if there isn't
if (!$resume) {
	$newresumeparams = new stdClass();
	$newresumeparams->user_id = $USER->id;
	$newresumeparams->courseid = $courseid;
	$newresumeparams->cover = get_string("resume_template_newresume", "block_exaport");
    $DB->insert_record("block_exaportresume", $newresumeparams);
	$resume = block_exaport_get_resume_params();
};

block_exaport_require_login($courseid);

$context = context_system::instance();

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidinstance", "block_exaport");
}

// get XML for europass
if ($xmleuropass == 1 && $id > 0) {
	$doexport = optional_param('doexport', '', PARAM_RAW);
	if ($doexport <> '') {
		header('Content-disposition: attachment; filename=europass.xml');
		header("Content-type: application/xml");
		$xml = europassXML($id);
		echo $xml;
		exit;
	}
}


$url = '/blocks/exaport/resume.php';
$PAGE->set_url($url);
$PAGE->requires->css('/blocks/exaport/css/resume.css');
$PAGE->requires->css('/blocks/exaport/javascript/simpletree.css');
$PAGE->requires->js('/blocks/exaport/javascript/simpletreemenu.js', true);

block_exaport_print_header("personal", "resume");

$PAGE->requires->js('/blocks/exaport/javascript/resume.js', true);


echo "<br />";

$show_information = true;
$redirect = false;

$userpreferences = block_exaport_get_user_preferences();
$description = $userpreferences->description;

if ($xmleuropass <> 1 && $edit == '0') {
	echo '<div class="services"><a href="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&xmleuropass=1&id='.$resume->id.'">'.
		'<img src="'.$CFG->wwwroot.'/blocks/exaport/pix/europass.png" height="35"><br/>'.
		get_string("resume_exportto_europass", "block_exaport").'</a></div>';
};
echo "<div class='block_eportfolio_center'><h2>";
echo $OUTPUT->box(text_to_html(get_string("resume_my", "block_exaport")), 'center');
echo "</h2></div>"; /**/

if ($xmleuropass == 1 && $id > 0) {
	echo '<img src="'.$CFG->wwwroot.'/blocks/exaport/pix/europass.png" height="50"><br/>';
	echo get_string("resume_exportto_europass_intro", "block_exaport");
	echo '<form action="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$courseid.'&xmleuropass=1&id='.$resume->id.'" method="post">';
	echo '<input type="submit" name="doexport" value="'.get_string("resume_exportto_europass_getXML", "block_exaport").'">';
	echo '</form>';
	$show_information = false;
}

// delete item
if ($delete) {
	if (data_submitted() && $confirm && confirm_sesskey()) {
		$conditions = array('id' => $id, 'resume_id' => $resume->id, 'user_id' => $USER->id);
		block_exaport_resume_mm_delete($delete, $conditions);
		echo "<div class='block_eportfolio_center'>".$OUTPUT->box(text_to_html(get_string("resume_".$delete."deleted", "block_exaport")), 'center')."</div>";
		$redirect = true;
	} else {
		$optionsyes = array('id' => $id, 'delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey(), 'courseid' => $courseid);
		$optionsno = array('courseid' => $courseid);

		echo '<br />';
		echo $OUTPUT->confirm(get_string("resume_delete".$delete."confirm", "block_exaport"), new moodle_url('resume.php', $optionsyes), new moodle_url('resume.php', $optionsno));
		echo block_exaport_wrapperdivend();
		echo $OUTPUT->footer();
		die;
	}
}

$textfieldoptions = array('trusttext'=>true, 'subdirs'=>true, 'maxfiles'=>99, 'context'=>context_user::instance($USER->id));

// Editing form.
if ($edit) {
	$withfiles = false;
	$show_information = false;
    if (!confirm_sesskey()) {
        print_error("blobadsessionkey", "block_exaport");
    };
	$data = new stdClass();
	$data->courseid = $courseid;
	$data->edit = $edit;
//	$data->resume_id = $resume->id;
	// Header of form.
	$formheader = get_string('edit', "block_exaport").': '.get_string('resume_'.$edit, "block_exaport");
	
	switch ($edit) {		
		case 'goalspersonal':			
		case 'goalsacademic':			
		case 'goalscareers':
		case 'skillspersonal':			
		case 'skillsacademic':			
		case 'skillscareers':
			$withfiles = true;
		case 'cover':
		case 'interests':	
			$data->{$edit} = $resume->{$edit};
			$data->{$edit.'format'} = FORMAT_HTML;				
			$workform = new block_exaport_resume_editor_form($_SERVER['REQUEST_URI'].'#'.$edit, array('formheader' => $formheader, 'field'=>$edit, 'withfiles' => $withfiles));
			$data = file_prepare_standard_editor($data, $edit, $textfieldoptions, context_user::instance($USER->id),			
												'block_exaport', 'resume_editor_'.$edit, $resume->id); 												
			// files
			if ($withfiles) {
				$draftitemid = file_get_submitted_draft_itemid('attachments');
				file_prepare_draft_area($draftitemid, context_user::instance($USER->id)->id, 'block_exaport', 'resume_'.$edit, $id,
								array('subdirs' => false, 'maxfiles' => 5, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size)); 
				$data->attachments = $draftitemid;
			};
			$workform->set_data($data);
			if ($workform->is_cancelled()) {
				$show_information = true;
			} else if ($fromform = $workform->get_data()) { 
				$fromform = file_postupdate_standard_editor($fromform, $edit, $textfieldoptions, context_user::instance($USER->id),
															'block_exaport', 'resume_editor_'.$edit, $resume->id);
				// files
				if ($withfiles) {
					// checking userquoata
					$upload_filesizes = block_exaport_get_filesize_by_draftid($fromform->attachments);
					if (block_exaport_file_userquotecheck($upload_filesizes) && block_exaport_get_maxfilesize_by_draftid_check($fromform->attachments)) {
						file_save_draft_area_files($fromform->attachments, context_user::instance($USER->id)->id, 'block_exaport', 'resume_'.$edit, $id, array('subdirs' => false, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size, 'maxfiles' => 5));
					};
				};
				block_exaport_set_resume_params(array($edit => $fromform->{$edit}, 'courseid' => $fromform->courseid));
				echo "<div class='block_eportfolio_center'>".$OUTPUT->box(get_string('resume_'.$edit."saved", "block_exaport"), 'center')."</div>";
				$show_information = true;
				$redirect = true;
			} else {
				$workform->display();
			};
			break;
		case 'edu':
			$display_inputs = array (
				'startdate' => 'text:required',
				'enddate' => 'text',
				'institution' => 'text:required',
				'institutionaddress' => 'text',
				'qualtype' => 'text',
				'qualname' => 'text',
				'qualdescription' => 'textarea',
				'files' => 'filearea'
			);
			if ($show_information = block_exaport_resume_prepare_block_mm_data($resume, $id, $edit, $display_inputs, $data)) {
				$redirect = true;
			};
			break;
		case 'employ':
			$display_inputs = array (
				'startdate' => 'text:required',
				'enddate' => 'text',
				'employer' => 'text:required',
				'employeraddress' => 'text',
				'jobtitle' => 'text:required',
				'positiondescription' => 'textarea',
				'files' => 'filearea'
			);
			if ($show_information = block_exaport_resume_prepare_block_mm_data($resume, $id, $edit, $display_inputs, $data)) {
				$redirect = true;
			};
			break;
		case 'certif':
			$display_inputs = array (
				'date' => 'text:required',
				'title' => 'text:required',
				'description' => 'textarea',
				'files' => 'filearea'
			);
			if ($show_information = block_exaport_resume_prepare_block_mm_data($resume, $id, $edit, $display_inputs, $data)) {
				$redirect = true;
			};
			break;
		case 'public':
			$display_inputs = array (
				'date' => 'text:required',
				'title' => 'text:required',
				'contribution' => 'text:required',
				'contributiondetails' => 'textarea',
				'url' => 'text',
				'files' => 'filearea'
			);
			if ($show_information = block_exaport_resume_prepare_block_mm_data($resume, $id, $edit, $display_inputs, $data)) {
				$redirect = true;
			};		
			break;
		case 'mbrship':
			$display_inputs = array (
				'startdate' => 'text:required',
				'enddate' => 'text',
				'title' => 'text:required',
				'description' => 'textarea',
				'files' => 'filearea'
			);
			if ($show_information = block_exaport_resume_prepare_block_mm_data($resume, $id, $edit, $display_inputs, $data)) {
				$redirect = true;
			};		
			break;
		case 'goalscomp':
		case 'skillscomp':			
			if ($show_information = block_exaport_resume_competences_form($resume, $id, $edit)) {
				$redirect = true;
			};		
			break;
		case 'badges':			
			if ($show_information = block_exaport_resume_checkboxeslist_form($resume, $edit, $data)) {
				$redirect = true;
			};		
			break;
		default:
			$show_information = true;
			$redirect = true;
	}
};

// Sort changing
if ($sortchange) {
	if (!confirm_sesskey()) {
        print_error("blobadsessionkey", "block_exaport");
    };
	$id1 = optional_param('id1', 0, PARAM_INT);
	$id2 = optional_param('id2', 0, PARAM_INT);
	if ($id1 && $id2) {
		$data1 = $DB->get_record("block_exaportresume_".$sortchange, array('id' => $id1, 'user_id' => $USER->id));
		$data2 = $DB->get_record("block_exaportresume_".$sortchange, array('id' => $id2, 'user_id' => $USER->id));
		// change sorting
		$newdata1 = new stdClass();
		$newdata1->id = $data1->id;
		$newdata1->sorting = $data2->sorting;
		$upd1 = $DB->update_record("block_exaportresume_".$sortchange, $newdata1);
		$newdata2 = new stdClass();
		$newdata2->id = $data2->id;
		$newdata2->sorting = $data1->sorting;
		$upd1 = $DB->update_record("block_exaportresume_".$sortchange, $newdata2);
		$redirect = true;
	}
}

// Resume blocks after saving
unset($resume);
$resume = block_exaport_get_resume_params();

// Redirect after doings
if ($redirect) {
	$opened_block = ($edit?$edit:($delete?$delete:($sortchange?$sortchange:'')));
	if (strpos($opened_block, 'goals') !== false) {
		$opened_block = 'goals';
	};
	if (strpos($opened_block, 'skills') !== false) {
		$opened_block = 'skills';
	};
	if ($opened_block) {
		$opened_block = '#'.$opened_block;
	};
	$returnurl = $CFG->wwwroot . '/blocks/exaport/resume.php?courseid='.$courseid.'&id='.$resume->id.$opened_block;
	// redirecting. Uncomment next line if you need this function
	// redirect($returnurl);
};

if ($show_information) {
	echo '<div class="collapsible-actions"><a href="#" class="expandall">'.get_string('resume_expand', 'block_exaport').'</a>';
	echo '<a href="#" class="collapsall hidden">'.get_string('resume_collaps', 'block_exaport').'</a></div>';

	// Cover.
	$cover = file_rewrite_pluginfile_urls($resume->cover, 'pluginfile.php', context_user::instance($USER->id)->id, 'block_exaport', 'resume_cover', $resume->id);
	echo block_exaport_form_resume_part($courseid, 'cover', get_string('resume_cover', 'block_exaport'), $cover, 'edit', $opened);

	// Education history.
	$conditions = array('user_id' => $USER->id, 'resume_id' => $resume->id);
	$educations = block_exaport_resume_get_mm_records('edu', $conditions);
	$educationhistory = block_exaport_resume_templating_mm_records($courseid, 'edu', 'qualification', $educations);
	echo block_exaport_form_resume_part($courseid, 'edu', get_string('resume_eduhistory', 'block_exaport'), $educationhistory, 'add', $opened);
	
	// Employment history.
	$conditions = array('user_id' => $USER->id, 'resume_id' => $resume->id);
	$employments = block_exaport_resume_get_mm_records('employ', $conditions);
	$employmenthistory = block_exaport_resume_templating_mm_records($courseid, 'employ', 'position', $employments);
	echo block_exaport_form_resume_part($courseid, 'employ', get_string('resume_employhistory', 'block_exaport'), $employmenthistory, 'add', $opened);

	// Certifications, accreditations and awards .
	$conditions = array('user_id' => $USER->id, 'resume_id' => $resume->id);
	$certifications = block_exaport_resume_get_mm_records('certif', $conditions);
	$certificationhistory = block_exaport_resume_templating_mm_records($courseid, 'certif', 'title', $certifications);
	echo block_exaport_form_resume_part($courseid, 'certif', get_string('resume_certif', 'block_exaport'), $certificationhistory, 'add', $opened);
	
	// Badges
	if ($CFG->enablebadges && block_exaport_badges_enabled()) {
		$conditions = array('resumeid' => $resume->id);
		$badges = block_exaport_resume_get_mm_records('badges', $conditions);
		$badgesrecords = block_exaport_resume_templating_mm_records($courseid, 'badges', 'title', $badges, 0, 0, 0);
		echo block_exaport_form_resume_part($courseid, 'badges', get_string('resume_badges', 'block_exaport'), $badgesrecords, 'edit', $opened);
	};

	// Books and publications
	$conditions = array('user_id' => $USER->id, 'resume_id' => $resume->id);
	$publications = block_exaport_resume_get_mm_records('public', $conditions);
	$publicationhistory = block_exaport_resume_templating_mm_records($courseid, 'public', 'title', $publications);
	echo block_exaport_form_resume_part($courseid, 'public', get_string('resume_public', 'block_exaport'), $publicationhistory, 'add', $opened);
	
	// Professional memberships
	$conditions = array('user_id' => $USER->id, 'resume_id' => $resume->id);
	$memberships = block_exaport_resume_get_mm_records('mbrship', $conditions);
	$membershiphistory = block_exaport_resume_templating_mm_records($courseid, 'mbrship', 'title', $memberships);
	echo block_exaport_form_resume_part($courseid, 'mbrship', get_string('resume_mbrship', 'block_exaport'), $membershiphistory, 'add', $opened);
	
	// My Goals
	$goals = block_exaport_resume_templating_list_goals_skills($courseid, $resume, 'goals', get_string('resume_goals', 'block_exaport'));
	echo block_exaport_form_resume_part($courseid, 'goals', get_string('resume_mygoals', 'block_exaport'), $goals, '', $opened);

	// My Skills
	$skills = block_exaport_resume_templating_list_goals_skills($courseid, $resume, 'skills', get_string('resume_skills', 'block_exaport'));
	echo block_exaport_form_resume_part($courseid, 'skills', get_string('resume_myskills', 'block_exaport'), $skills, '', $opened);

	// Interests.
	$interests = file_rewrite_pluginfile_urls($resume->interests, 'pluginfile.php', context_user::instance($USER->id)->id, 'block_exaport', 'resume_interests', $resume->id);
	echo block_exaport_form_resume_part($courseid, 'interests', get_string('resume_interests', 'block_exaport'), $interests, 'edit', $opened);
	
};

function block_exaport_form_resume_part($courseid = 0, $edit = '', $header = '', $content = '', $buttons = '', $opened=false) {
	global $CFG;
	$resume_part = '';
	//$resume_part .= print_collapsible_region('TEXT', '', 'id'.$edit, 'header', '', false, true) ;
	$resume_part .= '<form class="mform resumeform" method="post" action="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid . '">';
	$resume_part .= '<input type="hidden" name="edit" value="'.$edit.'" />';
	$resume_part .= '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
	$resume_part .= '<fieldset class="clearfix view-group'.($opened == $edit ? '-open':'').'">';
	//$resume_part .= '<fieldset class="clearfix collapsible" id="id_resume_'.$edit.'">';
	$resume_part .= '<legend class="view-group-header">'.$header.'</legend>';
	//$resume_part .= '<legend class="ftoggler"><a class="fheader" href="#" role="button" aria-controls="id_resume_'.$edit.'" aria-expanded="false">'.$header.'</a></legend>';	
	$resume_part .= '<a name="'.$edit.'"></a>';
	$resume_part .= '<div class="view-group-content clearfix">';
	//$resume_part .= '<div class="fcontainer clearfix">';	
	$resume_part .= '<div>'.$content.'</div>';
	switch ($buttons) {
		case 'edit':
				$resume_part .= '<input type="submit" value="' . get_string("edit") . '" />';
				break;
		case 'add':
				$resume_part .= '<input type="submit" value="' . get_string("add") . '" />';
				break;
		default :
				$resume_part .= '';
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
