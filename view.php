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

$context = get_context_instance(CONTEXT_SYSTEM);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidinstance", "block_exaport");
}

block_exaport_setup_default_categories();

$url = '/blocks/exaport/view.php';
$PAGE->set_url($url);
block_exaport_print_header("personal");

if (block_exaport_feature_enabled('share_item')) {
    if (has_capability('block/exaport:shareextern', $context)) {
        $extern_link = get_extern_access($USER->id);
        //print_simple_box( get_string("externaccess", "block_exaport") . ': <a  onclick="this.target=\'extlink\'; return openpopup(\'/blocks/exaport/'.$extern_link.'\',\'extlink\',\'resizable=1,scrollbars=1,directories=1,location=1,menubar=1,toolbar=1,status=1,width=620,height=450\');" href="'.$CFG->wwwroot.'/blocks/exaport/'.$extern_link.'">'.$CFG->wwwroot.'/blocks/exaport/'.$extern_link.'</a>','center');
        global $OUTPUT; // If necessary.
        echo $OUTPUT->box(get_string("externaccess", "block_exaport") . ': <a  onclick="this.target=\'extlink\'; return openpopup(\'/blocks/exaport/' . $extern_link . '\',\'extlink\',\'resizable=1,scrollbars=1,directories=1,location=1,menubar=1,toolbar=1,status=1,width=620,height=450\');" href="' . $CFG->wwwroot . '/blocks/exaport/' . $extern_link . '">' . $CFG->wwwroot . '/blocks/exaport/' . $extern_link . '</a>', 'center');
    }
}

echo "<br />";

$description = '';
$show_information = true;

$userpreferences = block_exaport_get_user_preferences();
$description = $userpreferences->description;

echo "<div class='block_eportfolio_center'>";

echo $OUTPUT->box(text_to_html(get_string("explainpersonal", "block_exaport")), 'center');

echo "</div>";

if ($edit) {
    if (!confirm_sesskey()) {
        print_error("badsessionkey", "block_exaport");
    }

    $informationform = new block_exaport_personal_information_form();

    if ($informationform->is_cancelled()) {
        
    } else if ($fromform = $informationform->get_data()) {
        //trusttext_after_edit($newentry->description, $context);

        $messagetext = $fromform->description['text'];
        // format of content
        $messageformat = $fromform->description['format'];

        block_exaport_set_user_preferences(array('description' => $messagetext, 'persinfo_timemodified' => time()));

        // read new data from the database
        $userpreferences = block_exaport_get_user_preferences();
        $description = $userpreferences->description;

        echo $OUTPUT->box(get_string("descriptionsaved", "block_exaport"), 'center', '40%', '#ccffbb');
    } else {
        $show_information = false;
//        $informationform->set_data(array('courseid' => $courseid,
//            'description' => $description,
//            'cataction' => 'save',
//            'edit' => 1));
        $data = new stdClass();
        $data->courseid = $courseid;
        $data->description['text'] = $description;
        $data->cataction = 'save';
        $data->edit = 1;
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

    echo $description;

    echo '</td></tr></table>' . "\n\n";

    echo '<div class="block_eportfolio_center">';

    echo '<form method="post" action="' . $CFG->wwwroot . '/blocks/exaport/view.php?courseid=' . $courseid . '">';
    echo '<fieldset class="hidden">';
    echo '<input type="hidden" name="edit" value="1" />';
    echo '<input type="submit" value="' . get_string("edit") . '" />';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';

    /*
      if (has_capability('block/exaport:shareextern', $context)) {
      echo ' <a href="'.$CFG->wwwroot.'/blocks/exaport/share_persinfo.php?courseid='.$courseid.'">';
      if ($userpreferences->persinfo_externaccess)
      echo get_string("strunshare", "block_exaport");
      else
      echo get_string("strshare", "block_exaport");
      echo '</a>';
      }
     */

    echo '</fieldset>';
    echo '</form>';
    echo '</div>';
}

echo "<span class=\"left\">project supported by<br/><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/bmukk.png\" width=\"63\" height=\"24\" alt=\"bmukk\" /></span>";
echo "<span class=\"right\">programmed by<br/><a href=\"http://www.exabis.at/\"><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/exabis.png\" width=\"89\" height=\"40\" alt=\"exabis\"/></a></span>";
echo "<div class=\"block_eportfolio_clear\" />";
echo "</div>";

echo $OUTPUT->footer($course);
