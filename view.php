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
require_once dirname(__FILE__) . '/lib/information_edit_form.php';

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_BOOL);

block_exaport_require_login($courseid);

$context = context_system::instance();

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidinstance", "block_exaport");
}

block_exaport_setup_default_categories();

$url = '/blocks/exaport/view.php';
$PAGE->set_url($url);
block_exaport_print_header("personal");

echo "<br />";

$show_information = true;

$userpreferences = block_exaport_get_user_preferences();
$description = $userpreferences->description;

echo "<div class='block_eportfolio_center'>";

echo $OUTPUT->box(text_to_html(get_string("explainpersonal", "block_exaport")), 'center');

echo "</div>";

$textfieldoptions = array('trusttext'=>true, 'subdirs'=>true, 'maxfiles'=>99, 'context'=>context_user::instance($USER->id));

if ($edit) {
    if (!confirm_sesskey()) {
        print_error("badsessionkey", "block_exaport");
    }

    $informationform = new block_exaport_personal_information_form();

    if ($informationform->is_cancelled()) {
        
    } else if ($fromform = $informationform->get_data()) { 
		$fromform = file_postupdate_standard_editor($fromform, 'description', $textfieldoptions, context_user::instance($USER->id), 'block_exaport', 'personal_information', $USER->id);
        block_exaport_set_user_preferences(array('description' => $fromform->description, 'persinfo_timemodified' => time()));

        // read new data from the database
        $userpreferences = block_exaport_get_user_preferences();
        $description = $userpreferences->description;

        echo $OUTPUT->box(get_string("descriptionsaved", "block_exaport"), 'center');
    } else {                                               
        $show_information = false;

        $data = new stdClass();
        $data->courseid = $courseid;
        $data->description = $description;
		$data->descriptionformat = FORMAT_HTML;
        $data->cataction = 'save';
        $data->edit = 1;
		
		$data = file_prepare_standard_editor($data, 'description', $textfieldoptions, context_user::instance($USER->id), 'block_exaport', 'personal_information', $USER->id);
        $informationform->set_data($data);
        $informationform->display();
    }
}

if ($show_information) {

    echo '<table cellspacing="0" class="forumpost blogpost blog" width="100%">';

    echo '<tr class="header"><td class="picture left">';
    $user = $DB->get_record('user', array("id" => $USER->id));
    echo $OUTPUT->user_picture($user, array("courseid" => $courseid));
    echo '</td>';

    echo '<td class="topic starter"><div class="author">';
    $by = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' .
            $USER->id . '&amp;course=' . $courseid . '">' . fullname($USER, $USER->id) . '</a>';
    print_string('byname', 'moodle', $by);
    echo '</div></td></tr>';

    echo '<tr><td class="left side">';

    echo '</td><td class="content">' . "\n";

	$description = file_rewrite_pluginfile_urls($description, 'pluginfile.php', context_user::instance($USER->id)->id, 'block_exaport', 'personal_information_self', null);
    echo $description;

    echo '</td></tr></table>' . "\n\n";

    echo '<div class="block_eportfolio_center">';

    echo '<form method="post" action="' . $CFG->wwwroot . '/blocks/exaport/view.php?courseid=' . $courseid . '">';
    echo '<fieldset class="hidden">';
    echo '<input type="hidden" name="edit" value="1" />';
    echo '<input type="submit" value="' . get_string("edit") . '" />';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';

    echo '</fieldset>';
    echo '</form>';
    echo '</div>';
}

echo "<span class=\"left\">".get_string("supported", "block_exaport")."<br/><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/bmukk.png\" width=\"63\" height=\"24\" alt=\"bmukk\" /></span>";
echo "<span class=\"right\">".get_string("developed", "block_exaport")."<br/><a href=\"http://www.gtn-solutions.com/\"><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/gtn.png\" width=\"89\" height=\"40\" alt=\"gtn-solutions\"/></a></span>";
echo "<div class=\"block_eportfolio_clear\" />";
echo "</div>";
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
