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

// deprecated file?
// "Personal information" block was removed from CV and used 'About me' instead

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/information_edit_form.php');

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

block_exaport_require_login($courseid);

block_exaport_setup_default_categories();

$url = '/blocks/exaport/personal_information.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

$userpreferences = block_exaport_get_user_preferences();
$description = $userpreferences->description;

$textfieldoptions = array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99, 'context' => context_user::instance($USER->id));

require_sesskey();

$informationform = new block_exaport_personal_information_form();

if ($informationform->is_cancelled()) {
    redirect('resume.php?courseid=' . $courseid);
    exit;
} else if ($fromform = $informationform->get_data()) {
    $fromform = file_postupdate_standard_editor($fromform,
        'description',
        $textfieldoptions,
        context_user::instance($USER->id),
        'block_exaport',
        'personal_information',
        $USER->id);
    block_exaport_set_user_preferences(array('description' => $fromform->description, 'persinfo_timemodified' => time()));

    redirect('resume.php?courseid=' . $courseid);
    exit;
}
$data = new stdClass();
$data->courseid = $courseid;
$data->description = $description;
$data->descriptionformat = FORMAT_HTML;
$data->cataction = 'save';
$data->edit = 1;

$data = file_prepare_standard_editor($data,
    'description',
    $textfieldoptions,
    context_user::instance($USER->id),
    'block_exaport',
    'personal_information',
    $USER->id);
$informationform->set_data($data);

block_exaport_print_header("resume_my");

echo "<div class='block_eportfolio_center'><h2>";
echo $OUTPUT->box(text_to_html(get_string("explainpersonal", "block_exaport")), 'center');
echo "</h2></div>";

$informationform->display();

echo block_exaport_print_footer();
