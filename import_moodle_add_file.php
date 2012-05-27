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
global $DB;

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$confirm = optional_param('confirm', '', PARAM_BOOL);
$filename = optional_param('filename', '', PARAM_FILE);
$assignmentid = optional_param('submissionid', 0, PARAM_INT);
$aid = optional_param('assignmentid', 0, PARAM_INT);
$cm = get_coursemodule_from_instance('assignment', $aid);

$post = new stdClass();
$checked_file = new stdClass();
$action = 'add';

if (!confirm_sesskey()) {
    print_error("badsessionkey", "block_exaport");
}

$context = get_context_instance(CONTEXT_SYSTEM);
$url = '/blocks/exabis_competences/import_moodle_add_file.php';
$PAGE->set_url($url);

require_login($courseid);
require_capability('block/exaport:use', $context);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidcourseid", "block_exaport");
}

if (!block_exaport_has_categories($USER->id)) {
    print_error("nocategories", "block_exaport", "view.php?courseid=" . $courseid);
}

if ($assignmentid == 0) {
    error("No assignment given!");
}

if (!($checked_file = check_assignment_file($assignmentid, $filename))) {
    print_error("invalidfileatthisassignment", "block_exaport");
}


if ($id) {
    $conditions = array("id" => $id, "userid" => $USER->id);
    if (!$existing = $DB->get_record('block_exaportitem', $conditions)) {
        print_error("wrongfileid", "block_exaport");
    }

    $returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . "&type=file"; //?userid='.$existing->userid;
} else {
    $existing = false;
    $returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . "&type=file"; //'index.php?userid='.$USER->id;
}

if ($action == 'delete') {
    if (!$existing) {
        print_error("wrongfilepostid", "block_exaport");
    }
    if (data_submitted() and $confirm and confirm_sesskey()) {
        do_delete($existing, $returnurl, $courseid);
        redirect($returnurl);
    } else {
        $optionsyes = array('id' => $id, 'action' => 'delete', 'confirm' => 1, 'sesskey' => sesskey(), 'courseid' => $courseid);
        $optionsno = array('userid' => $existing->userid, 'courseid' => $courseid);
        print_header("$SITE->shortname", $SITE->fullname);
        // ev. noch eintrag anzeigen!!!
        //blog_print_entry($existing);
        echo '<br />';
        notice_yesno(get_string("deletefileconfirm", "block_exaport"), 'add_file.php', 'view_items.php', $optionsyes, $optionsno, 'post', 'get');
        print_footer();
        die;
    }
}

require_once("{$CFG->dirroot}/blocks/exaport/lib/item_edit_form.php");

if (($checked_file != '') && ($action == 'add')) {
    $existing->action = $action;
    $existing->courseid = $courseid;
    $existing->type = 'file';
    $existing->dir = "";
    $existing->name = "";
    $existing->categoryid = "";
    $existing->intro = "";
    //$existing->fullpath     = $checked_file->fullpath;
    $existing->filename = $checked_file->filename;
    $existing->submission = $assignmentid;
    $existing->activityid = $cm->id;
}

$exteditform = new block_exaport_item_edit_form(null, Array('existing' => $existing, 'type' => 'file', 'action' => 'edit'));


if ($exteditform->is_cancelled()) {
    redirect($returnurl);
} else if ($exteditform->no_submit_button_pressed()) {
    die("nosubmitbutton");
    //no_submit_button_actions($exteditform, $sitecontext);
} else if ($fromform = $exteditform->get_data()) {
    switch ($action) {
        case 'add':
            do_add($fromform, $exteditform, $returnurl, $courseid, $checked_file);
            break;

        case 'edit':
            if (!$existing) {
                print_error("wrongfileid", "block_exaport");
            }
            do_edit($fromform, $exteditform, $returnurl, $courseid);
            break;
        default :
            print_error("unknownaction", "block_exaport");
    }
    redirect($returnurl);
}

$strAction = "";
// gui setup
switch ($action) {
    case 'add':
        $post->action = $action;
        $post->courseid = $courseid;
        $post->submissionid = $assignmentid;
        $post->filename = $checked_file->filename;
        $strAction = get_string('new');
        $post->activityid = $cm->id;
        break;
    default :
        print_error("unknownaction", "block_exaport");
}

block_exaport_print_header("bookmarksfiles");

if (!$cm = get_coursemodule_from_instance('assignment', $aid)) {
    print_error('invalidcoursemodule');
}
$filecontext = get_context_instance(CONTEXT_MODULE, $cm->id);

echo "<div class='block_eportfolio_center'>\n";
echo $OUTPUT->box(block_exaport_print_file(file_encode_url($CFG->wwwroot . '/pluginfile.php', '/' . $filecontext->id . '/mod_assignment/submission/' . $assignmentid . '/' . $filename), $checked_file->filename, $checked_file->filename));
echo "</div>";

$exteditform->set_data($post);
$exteditform->display();

echo $OUTPUT->footer($course);
die;

/**
 * Update a file in the database
 */
function do_edit($post, $blogeditform, $returnurl, $courseid) {
    global $CFG, $USER;

    $post->timemodified = time();
    $post->intro = $post->intro['text'];
    
    if (update_record('block_exaportitem', $post)) {
        add_to_log(SITEID, 'bookmark', 'update', 'add_file.php?courseid=' . $courseid . '&id=' . $post->id . '&action=edit', $post->name);
    } else {
        print_error('updateposterror', 'block_exaport', $returnurl);
    }
}

/**
 * Write a new blog entry into database
 */
function do_add($post, $blogeditform, $returnurl, $courseid, $checked_file) {
    global $CFG, $USER, $DB, $COURSE;

    $post->userid = $USER->id;
    $post->timemodified = time();
    $post->course = $courseid;
    $post->type = 'file';
    $post->attachment = $checked_file->itemid;
    $post->intro = $post->intro['text'];

    // Insert the new blog entry.
    $post->id = $DB->insert_record('block_exaportitem', $post);

    if(block_exaport_check_competence_interaction()) {    

        //Kompetenzen checken und erneut speichern
        $comps = $DB->get_records('block_exacompdescractiv_mm',array("activityid"=>$post->activityid,"activitytype"=>1));
        foreach($comps as $comp) {
            $DB->insert_record('block_exacompdescractiv_mm',array("activityid"=>$post->id, "activitytype"=>2000,"descrid"=>$comp->descrid,"activitytitle"=>$post->name,"coursetitle"=>$COURSE->shortname));
        }
    }
}

/**
 * Delete blog post from database
 */
function do_delete($post, $returnurl, $courseid) {


    $status = $DB->delete_records('block_exaportitem', 'id', $post->id);

    add_to_log(SITEID, 'blog', 'delete', 'add_file.php?courseid=' . $courseid . '&id=' . $post->id . '&action=delete&confirm=1', $post->name);

    if (!$status) {
        print_error('deleteposterror', 'block_exaport', $returnurl);
    }
}

function check_assignment_file($assignmentid, $file) {
    global $CFG, $USER, $DB;
    $fileinfo = new stdClass();

    if (!$assignment = $DB->get_record_sql('SELECT s.id, a.id AS aid, s.assignment, a.course AS courseid
                                    FROM {assignment_submissions} s
                                    JOIN {assignment} a ON s.assignment=a.id
                                    WHERE s.userid=\'' . $USER->id . '\' AND s.id=\'' . $assignmentid . '\'')) {

        print_error("invalidassignmentid", "block_exaport");
    }

    if (!$cm = get_coursemodule_from_instance('assignment', $assignment->aid)) {
        print_error('invalidcoursemodule');
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_assignment', 'submission', $assignment->id);
    if ($files) {
        foreach ($files as $actFile) {

            if ($actFile->get_filename() == $file) {
                $fileinfo->filename = $file;
                $fileinfo->itemid = $actFile->get_itemid();

                return $fileinfo;
            }
        }
    }
    else
        echo'nope';
    return false;
    $basedir = block_exaport_moodleimport_file_area_name($USER->id, $assignment->assignment, $assignment->courseid);

    if ($files = get_directory_list($CFG->dataroot . '/' . $basedir)) {
        foreach ($files as $key => $actFile) {
            if ($actFile == $file) {
                $fileinfo->filename = $file;
                $fileinfo->basedir = $basedir;
                $fileinfo->fullpath = $basedir . '/' . $actFile;
                return $fileinfo;
            }
        }
    }
    return false;
}
