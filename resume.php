<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/resumelib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$confirm = optional_param("confirm", "", PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$type = optional_param('type', 0, PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);

$resume = block_exaport_get_resume_params();
// Create new resume if there isn't.
if (!$resume) {
    $newresumeparams = new stdClass();
    $newresumeparams->user_id = $USER->id;
    $newresumeparams->courseid = $courseid;
    $newresumeparams->cover = get_string("resume_template_newresume", "block_exaport");
    $DB->insert_record("block_exaportresume", $newresumeparams);
    $resume = block_exaport_get_resume_params();
}

block_exaport_require_login($courseid);

$context = context_system::instance();

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidinstance", "block_exaport");
}

// Get XML for europass.
if ($action == 'xmleuropass_export') {
    $xml = europass_xml($resume->id);

    header('Content-disposition: attachment; filename=europass.xml');
    header("Content-type: application/xml");
    echo $xml;
    exit;
}

$url = '/blocks/exaport/resume.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
$PAGE->requires->css('/blocks/exaport/css/resume.css');
$PAGE->requires->css('/blocks/exaport/javascript/simpletree.css');
$PAGE->requires->js('/blocks/exaport/javascript/simpletreemenu.js', true);

block_exaport_print_header("resume_my");

$PAGE->requires->js('/blocks/exaport/javascript/resume.js', true);

if ($action != 'edit') {
    // a conflict with Moodle fontawesome inside 'editor'
    // TODO: check how to solve it
    block_exaport_add_iconpack();
}

echo "<br />";

$showinformation = true;
$redirect = false;

$userpreferences = block_exaport_get_user_preferences();

if ($action == 'xmleuropass') {
    echo block_exaport_resume_header();
    echo '<img src="' . $CFG->wwwroot . '/blocks/exaport/pix/europass.png" height="50"><br/>';
    echo get_string("resume_exportto_europass_intro", "block_exaport");
    echo '<form action="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid .
        '&action=xmleuropass_export" method="post">';
    echo '<input type="submit" value="' . get_string("resume_exportto_europass_getXML", "block_exaport") . '" class="btn btn-primary">';
    echo '</form><br>';
    $showinformation = false;
}

// Delete item.
if ($action == 'delete' && in_array($type, ['certif', 'edu', 'employ', 'mbrship', 'public'])) {
    if (data_submitted() && $confirm) {
        require_sesskey();

        $conditions = array('id' => $id, 'resume_id' => $resume->id);
        block_exaport_resume_mm_delete($type, $conditions);
        echo "<div class='block_eportfolio_center'>" .
            $OUTPUT->box(text_to_html(get_string("resume_" . $type . "deleted", "block_exaport")), 'center') . "</div>";
        $redirect = true;
    } else {
        echo block_exaport_resume_header();
        $optionsyes = array('id' => $id, 'action' => 'delete', 'type' => $type, 'confirm' => 1, 'sesskey' => sesskey(),
            'courseid' => $courseid);
        $optionsno = array('courseid' => $courseid);

        echo '<br />';
        echo $OUTPUT->confirm(get_string("resume_delete" . $type . "confirm", "block_exaport"),
            new moodle_url('resume.php', $optionsyes), new moodle_url('resume.php', $optionsno));
        echo block_exaport_wrapperdivend();
        echo $OUTPUT->footer();
        die;
    }
}

$textfieldoptions = array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99, 'context' => context_user::instance($USER->id));

// Editing form.
if ($action == 'edit') {
    $withfiles = false;
    $showinformation = false;

    require_sesskey();

    $data = new stdClass();
    $data->courseid = $courseid;
    $data->action = 'edit';
    $data->type = $type;
    // Header of form.
    $formheader = get_string('edit', "block_exaport") . ': ' . get_string('resume_' . $type, "block_exaport");

    switch ($type) {
        case 'goalspersonal':
        case 'goalsacademic':
        case 'goalscareers':
        case 'skillspersonal':
        case 'skillsacademic':
        case 'skillscareers':
            $withfiles = true;
        case 'cover':
        case 'interests':
            $data->{$type} = $resume->{$type};
            $data->{$type . 'format'} = FORMAT_HTML;
            $workform = new block_exaport_resume_editor_form($_SERVER['REQUEST_URI'] . '#' . $type,
                array('formheader' => $formheader, 'field' => $type, 'withfiles' => $withfiles));
            $data = file_prepare_standard_editor($data, $type, $textfieldoptions, context_user::instance($USER->id),
                'block_exaport', 'resume_editor_' . $type, $resume->id);
            // Files.
            if ($withfiles) {
                $draftitemid = file_get_submitted_draft_itemid('attachments');
                file_prepare_draft_area($draftitemid, context_user::instance($USER->id)->id, 'block_exaport', 'resume_' . $type, $id,
                    array('subdirs' => false, 'maxfiles' => 5, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
                $data->attachments = $draftitemid;
            };
            $workform->set_data($data);
            if ($workform->is_cancelled()) {
                $showinformation = true;
            } else if ($fromform = $workform->get_data()) {
                $fromform = file_postupdate_standard_editor($fromform, $type, $textfieldoptions, context_user::instance($USER->id),
                    'block_exaport', 'resume_editor_' . $type, $resume->id);
                // Files.
                if ($withfiles) {
                    // Checking userquoata.
                    $uploadfilesizes = block_exaport_get_filessize_by_draftid($fromform->attachments);
                    if (block_exaport_file_userquotecheck($uploadfilesizes) &&
                        block_exaport_get_maxfilesize_by_draftid_check($fromform->attachments)
                    ) {
                        file_save_draft_area_files($fromform->attachments, context_user::instance($USER->id)->id, 'block_exaport',
                            'resume_' . $type, $id,
                            array('subdirs' => false, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size, 'maxfiles' => 5));
                    };
                };
                block_exaport_set_resume_params(array($type => $fromform->{$type}, 'courseid' => $fromform->courseid));
                echo "<div class='block_eportfolio_center'>" .
                    $OUTPUT->box(get_string('resume_' . $type . "saved", "block_exaport"), 'center') . "</div>";
                $showinformation = true;
                $redirect = true;
            } else {
                echo block_exaport_resume_header();
                $workform->display();
            };
            break;
        case 'edu':
            $displayinputs = array(
                'startdate' => 'text:required',
                'enddate' => 'text',
                'institution' => 'text:required',
                'institutionaddress' => 'text',
                'qualtype' => 'text',
                'qualname' => 'text',
                'qualdescription' => 'textarea',
                'files' => 'filearea',
            );
            if ($showinformation = block_exaport_resume_prepare_block_mm_data($resume, $id, $type, $displayinputs, $data)) {
                $redirect = true;
            };
            break;
        case 'employ':
            $displayinputs = array(
                'startdate' => 'text:required',
                'enddate' => 'text',
                'employer' => 'text:required',
                'employeraddress' => 'text',
                'jobtitle' => 'text:required',
                'positiondescription' => 'textarea',
                'files' => 'filearea',
            );
            if ($showinformation = block_exaport_resume_prepare_block_mm_data($resume, $id, $type, $displayinputs, $data)) {
                $redirect = true;
            };
            break;
        case 'certif':
            $displayinputs = array(
                'date' => 'text:required',
                'title' => 'text:required',
                'description' => 'textarea',
                'files' => 'filearea',
            );
            if ($showinformation = block_exaport_resume_prepare_block_mm_data($resume, $id, $type, $displayinputs, $data)) {
                $redirect = true;
            };
            break;
        case 'public':
            $displayinputs = array(
                'date' => 'text:required',
                'title' => 'text:required',
                'contribution' => 'text:required',
                'contributiondetails' => 'textarea',
                'url' => 'text',
                'files' => 'filearea',
            );
            if ($showinformation = block_exaport_resume_prepare_block_mm_data($resume, $id, $type, $displayinputs, $data)) {
                $redirect = true;
            };
            break;
        case 'mbrship':
            $displayinputs = array(
                'startdate' => 'text:required',
                'enddate' => 'text',
                'title' => 'text:required',
                'description' => 'textarea',
                'files' => 'filearea',
            );
            if ($showinformation = block_exaport_resume_prepare_block_mm_data($resume, $id, $type, $displayinputs, $data)) {
                $redirect = true;
            };
            break;
        case 'goalscomp':
        case 'skillscomp':
            if ($showinformation = block_exaport_resume_competences_form($resume, $id, $type)) {
                $redirect = true;
            };
            break;
        case 'badges':
            if ($showinformation = block_exaport_resume_checkboxeslist_form($resume, $type, $data)) {
                $redirect = true;
            };
            break;
        default:
            $showinformation = true;
            $redirect = true;
    }
}

// Sort changing.
if ($action == 'sortchange' && in_array($type, ['certif', 'edu', 'employ', 'mbrship', 'public'])) {
    require_sesskey();

    $id1 = optional_param('id1', 0, PARAM_INT);
    $id2 = optional_param('id2', 0, PARAM_INT);
    if ($id1 && $id2) {
        $data1 = $DB->get_record("block_exaportresume_" . $type, array('id' => $id1, 'resume_id' => $resume->id));
        $data2 = $DB->get_record("block_exaportresume_" . $type, array('id' => $id2, 'resume_id' => $resume->id));
        // Change sorting.
        $newdata1 = new stdClass();
        $newdata1->id = $data1->id;
        $newdata1->sorting = $data2->sorting;
        $upd1 = $DB->update_record("block_exaportresume_" . $type, $newdata1);
        $newdata2 = new stdClass();
        $newdata2->id = $data2->id;
        $newdata2->sorting = $data1->sorting;
        $upd1 = $DB->update_record("block_exaportresume_" . $type, $newdata2);
        $redirect = true;
    }
}

// Resume blocks after saving.
$resume = block_exaport_get_resume_params();

// Redirect after doings.
if ($redirect) {
    $openedblock = $type;
    if (strpos($openedblock, 'goals') !== false) {
        $openedblock = 'goals';
    };
    if (strpos($openedblock, 'skills') !== false) {
        $openedblock = 'skills';
    };
    if ($openedblock) {
        $openedblock = '#' . $openedblock;
    };
    $returnurl = $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid . '&id=' . $resume->id . $openedblock;
    // Redirecting. Uncomment next line if you need this function
    // Redirect($returnurl);.
};

if ($action != 'xmleuropass' && $showinformation) {
    echo '<div class="services"><a href="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $courseid . '&action=xmleuropass">' .
        '<img src="' . $CFG->wwwroot . '/blocks/exaport/pix/europass.png" height="35"><br/>' .
        get_string("resume_exportto_europass", "block_exaport") . '</a></div>';
};

if ($showinformation) {
    $user = $DB->get_record('user', array("id" => $USER->id));

    echo '<br />';

    echo block_exaport_resume_header();
    echo '<div class="collapsible-actions"><a href="#" class="expandall">' . get_string('resume_expand', 'block_exaport') . '</a>';
    echo '<a href="#" class="collapsall hidden">' . get_string('resume_collaps', 'block_exaport') . '</a></div>';

    // Cover.
    $cover = file_rewrite_pluginfile_urls($resume->cover, 'pluginfile.php',
        context_user::instance($USER->id)->id, 'block_exaport', 'resume_editor_cover', $resume->id);
    $cover = block_exaport_add_view_access_parameter_to_url($cover, 'resume/' . $resume->id . '/' . $USER->id, ['src']);
    $cover = block_exaport_html_secure($cover);
    echo block_exaport_form_resume_part($courseid, 'cover',
        get_string('resume_cover', 'block_exaport'), $cover, 'edit', $type);
    // Education history.
    $educations = block_exaport_resume_get_educations($resume->id);
    $educationhistory = block_exaport_resume_templating_mm_records($courseid, 'edu', 'qualification', $educations);
    echo block_exaport_form_resume_part($courseid, 'edu',
        get_string('resume_eduhistory', 'block_exaport'), $educationhistory, 'add', $type);

    // Employment history.
    $employments = block_exaport_resume_get_employments($resume->id);
    $executive_summary = block_exaport_resume_templating_mm_records($courseid, 'employ', 'position', $employments);
    echo block_exaport_form_resume_part($courseid, 'employ',
        get_string('resume_employhistory', 'block_exaport'), $executive_summary, 'add', $type);

    // certifications, accreditations and awards.
    $certifications = block_exaport_resume_get_certificates($resume->id);
    $certificationhistory = block_exaport_resume_templating_mm_records($courseid, 'certif', 'title', $certifications);
    echo block_exaport_form_resume_part($courseid, 'certif',
        get_string('resume_certif', 'block_exaport'), $certificationhistory, 'add', $type);

    // Badges.
    if (block_exaport_badges_enabled()) {
        $badges = block_exaport_resume_get_badges($resume->id);
        $badgesrecords = block_exaport_resume_templating_mm_records($courseid, 'badges', 'title', $badges, 0, 0, 0);
        echo block_exaport_form_resume_part($courseid, 'badges',
            get_string('resume_badges', 'block_exaport'), $badgesrecords, 'edit', $type);
    };

    // Books and publications.
    $publications = block_exaport_resume_get_publications($resume->id);
    $publicationhistory = block_exaport_resume_templating_mm_records($courseid, 'public', 'title', $publications);
    echo block_exaport_form_resume_part($courseid, 'public',
        get_string('resume_public', 'block_exaport'), $publicationhistory, 'add', $type);

    // Professional memberships.
    $memberships = block_exaport_resume_get_profmembershipments($resume->id);
    $membershiphistory = block_exaport_resume_templating_mm_records($courseid, 'mbrship', 'title', $memberships);
    echo block_exaport_form_resume_part($courseid, 'mbrship',
        get_string('resume_mbrship', 'block_exaport'), $membershiphistory, 'add', $type);

    // My Goals.
    $goals = block_exaport_resume_templating_list_goals_skills($courseid, $resume, 'goals',
        get_string('resume_goals', 'block_exaport'));
    echo block_exaport_form_resume_part($courseid, 'goals', get_string('resume_mygoals', 'block_exaport'), $goals, '', $type);

    // My Skills.
    $skills = block_exaport_resume_templating_list_goals_skills($courseid, $resume, 'skills',
        get_string('resume_skills', 'block_exaport'));
    echo block_exaport_form_resume_part($courseid, 'skills', get_string('resume_myskills', 'block_exaport'), $skills, '', $type);

    // Interests.
    $interests = file_rewrite_pluginfile_urls($resume->interests, 'pluginfile.php', context_user::instance($USER->id)->id,
        'block_exaport', 'resume_editor_interests', $resume->id);
    // Add special access parameter only for edit resume view
    $interests = block_exaport_add_view_access_parameter_to_url($interests, 'resume/' . $resume->id . '/' . $USER->id, ['src']);
    //    $interests = file_rewrite_pluginfile_urls($resume->interests, 'pluginfile.php', context_user::instance($USER->id)->id,
    //            'block_exaport', 'resume_interests', $resume->id); // For old records compatible.
    echo block_exaport_form_resume_part($courseid, 'interests',
        get_string('resume_interests', 'block_exaport'), $interests, 'edit', $type);

};

function block_exaport_form_resume_part($courseid = 0, $type = '', $header = '', $content = '', $buttons = '', $opened = false) {
    global $CFG;
    $resumepart = '';
    $resumepart .= '<form class="mform resumeform" method="post" action="' . $CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' .
        $courseid . '">';
    $resumepart .= '<input type="hidden" name="action" value="edit" />';
    $resumepart .= '<input type="hidden" name="type" value="' . $type . '" />';
    $resumepart .= '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
    $resumepart .= '<fieldset class="clearfix view-group' . ('' . $opened == '' . $type ? '-open' : '') . '">';
    $resumepart .= '<legend class="view-group-header">' . $header . '</legend>';
    $resumepart .= '<a name="' . $type . '"></a>';
    $resumepart .= '<div class="view-group-content clearfix">';
    $resumepart .= '<div>' . $content . '</div>';
    switch ($buttons) {
        case 'edit':
            $resumepart .= '<input type="submit" value="' . get_string("edit") . '" class="btn btn-secondary" />';
            break;
        case 'add':
            $resumepart .= '<input type="submit" value="' . get_string("add") . '" class="btn btn-primary" />';
            break;
        default :
            $resumepart .= '';
            break;
    };
    $resumepart .= '</div>';
    $resumepart .= '</fieldset>';
    $resumepart .= '</form>';
    return $resumepart;
}

function block_exaport_resume_header() {
    global $OUTPUT;
    $content = "<div class='block_eportfolio_center'><h2>";
    $content .= $OUTPUT->box(text_to_html(get_string("resume_my", "block_exaport")), 'center');
    $content .= "</h2></div>";
    return $content;
}

/*echo "<span class=\"left\">".get_string("supported", "block_exaport")."<br/>";
echo "<img src=\"{$CFG->wwwroot}/blocks/exaport/pix/bmukk.png\" width=\"63\" height=\"24\" alt=\"bmukk\" /></span>";*/
echo "<span class=\"right\">" . get_string("developed", "block_exaport");
echo "<br/><a href=\"http://www.gtn-solutions.com/\">";
echo "<img src=\"{$CFG->wwwroot}/blocks/exaport/pix/gtn.png\" height=\"25\" alt=\"gtn-solutions\"/></a></span>";
echo "<div class=\"block_eportfolio_clear\" />";
echo "</div>";
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
