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

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$confirm = optional_param('confirm', '', PARAM_BOOL);
$fileid = optional_param('fileid', '', PARAM_FILE);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

$modassign = block_exaport_assignmentversion();

if ($modassign->new) {
    $assignment = $DB->get_record_sql("SELECT s.id AS submissionid, a.id AS aid, s.assignment, s.timemodified, " .
        " a.name, a.course, c.fullname AS coursename " .
        " FROM {assignsubmission_file} sf " .
        " INNER JOIN {assign_submission} s ON sf.submission=s.id " .
        " INNER JOIN {assign} a ON s.assignment=a.id " .
        " LEFT JOIN {course} c on a.course = c.id " .
        " WHERE s.userid=? AND s.id=?", array($USER->id, $submissionid));
} else {
    $assignment = $DB->get_record_sql("SELECT s.id AS submissionid, a.id AS aid, s.assignment, s.timemodified, " .
        " a.name, a.course, a.assignmenttype, c.fullname AS coursename " .
        " FROM {assignment_submissions} s " .
        " JOIN {assignment} a ON s.assignment=a.id " .
        " LEFT JOIN {course} c on a.course = c.id " .
        " WHERE s.userid=? AND s.id=?", array($USER->id, $submissionid));
}

$cm = get_coursemodule_from_instance($modassign->title, $assignment->aid);

$post = new stdClass();
$checkedfile = null;
$action = 'add';

$context = context_system::instance();
$url = '/blocks/exaport/import_moodle_add_file.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

require_login($courseid);
require_capability('block/exaport:use', $context);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidcourseid", "block_exaport");
}

if (!block_exaport_has_categories($USER->id)) {
    print_error("nocategories", "block_exaport", "view.php?courseid=" . $courseid);
}

if ($submissionid == 0) {
    error("No assignment given!");
}

if (!($checkedfile = check_assignment_file($cm, $assignment, $fileid))) {
    print_error("invalidfileatthisassignment", "block_exaport");
}

if ($id) {
    $conditions = array("id" => $id, "userid" => $USER->id);
    if (!$existing = $DB->get_record('block_exaportitem', $conditions)) {
        print_error("wrongfileid", "block_exaport");
    }

    $returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . "&type=file";
} else {
    $existing = false;
    $returnurl = $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . "&type=file";
}

if ($action == 'delete') {
    require_sesskey();
    // TODO: is this still used?!?

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
        echo block_exaport_wrapperdivstart();
        // Ev. noch eintrag anzeigen!!!
        // ... blog _print _entry ($existing);.
        echo '<br />';
        notice_yesno(get_string("deletefileconfirm", "block_exaport"), 'add_file.php', 'view_items.php', $optionsyes, $optionsno,
            'post', 'get');
        print_footer();
        die;
    }
}

require_once("{$CFG->dirroot}/blocks/exaport/lib/item_edit_form.php");

if ($action == 'add') {
    if ($existing == false) {
        $existing = new stdClass();
    }
    $existing->action = $action;
    $existing->courseid = $courseid;
    $existing->type = 'file';
    $existing->dir = "";
    $existing->name = "";
    $existing->categoryid = "";
    $existing->intro = "";
    $existing->filename = $checkedfile->get_filename();
    $existing->submission = $submissionid;
    if (!empty($cm->id)) {
        $existing->activityid = $cm->id;
    } else {
        $existing->activityid = -1;
    }
}

$exteditform = new block_exaport_item_edit_form(null,
    array('existing' => $existing, 'type' => 'file', 'action' => 'assignment_import'));

if ($exteditform->is_cancelled()) {
    redirect($returnurl);
} else if ($exteditform->no_submit_button_pressed()) {
    die("nosubmitbutton");
} else if ($fromform = $exteditform->get_data()) {
    require_sesskey();

    switch ($action) {
        case 'add':
            do_add($cm, $fromform, $exteditform, $returnurl, $courseid, $checkedfile);
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

$straction = "";
// Gui setup.
switch ($action) {
    case 'add':
        $post->action = $action;
        $post->courseid = $courseid;
        $post->submissionid = $submissionid;
        $post->fileid = $fileid;
        $straction = get_string('new');
        break;
    default :
        print_error("unknownaction", "block_exaport");
}

block_exaport_print_header("bookmarksfiles");

if (!$cm = get_coursemodule_from_instance($modassign->title, $assignment->aid)) {
    print_error('invalidcoursemodule');
}
$filecontext = context_module::instance($cm->id);

echo "<div class='block_eportfolio_center'>\n";

echo $OUTPUT->box(block_exaport_print_file($checkedfile));
echo "</div>";

$exteditform->set_data($post);
$exteditform->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
exit;

/**
 * Update a file in the database
 */
function do_edit($post, $blogeditform, $returnurl, $courseid) {
    global $CFG, $USER;

    $post->timemodified = time();
    $post->intro = $post->intro['text'];

    if (update_record('block_exaportitem', $post)) {
        block_exaport_add_to_log(SITEID, 'bookmark', 'update', 'add_file.php?courseid=' . $courseid . '&id=' . $post->id . '&action=edit',
            $post->name);
    } else {
        print_error('updateposterror', 'block_exaport', $returnurl);
    }
}

/**
 * Write a new blog entry into database
 */
function do_add($cm, $post, $blogeditform, $returnurl, $courseid, $checkedfile) {
    global $CFG, $USER, $DB, $COURSE;

    $post->userid = $USER->id;
    $post->timemodified = time();
    $post->courseid = $courseid;
    $post->intro = '';
    $post->type = 'file';
    $post->attachment = $checkedfile->get_itemid();

    // Insert the new blog entry.
    $post->id = $DB->insert_record('block_exaportitem', $post);

    $textfieldoptions = array('trusttext' => true,
        'subdirs' => true,
        'maxfiles' => 99,
        'context' => context_user::instance($USER->id));
    $post->introformat = FORMAT_HTML;

    $post = file_postupdate_standard_editor($post, 'intro', $textfieldoptions, context_user::instance($USER->id), 'block_exaport',
        'item_content', $post->id);

    $filerecord = new stdClass();
    $context = context_user::instance($USER->id);
    $filerecord->contextid = $context->id;
    $filerecord->component = 'block_exaport';
    $filerecord->filearea = 'item_file';
    $filerecord->itemid = $post->id;

    $fs = get_file_storage();
    $fs->create_file_from_storedfile($filerecord, $checkedfile);

    // Insert the new blog entry.
    $DB->update_record('block_exaportitem', $post);

    if (block_exaport_check_competence_interaction()) {

        // Kompetenzen checken und erneut speichern.
        // TODO Test if missing activitytype = 1 has influence.
        $comps = $DB->get_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, array("activityid" => $cm->id));
        foreach ($comps as $comp) {
            $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                array("activityid" => $post->id, "eportfolioitem" => 1, "compid" => $comp->descrid,
                    "activitytitle" => $post->name, "coursetitle" => $COURSE->shortname));
        }
    }
}

/**
 * Delete blog post from database
 */
function do_delete($post, $returnurl, $courseid) {

    $status = $DB->delete_records('block_exaportitem', 'id', $post->id);

    block_exaport_add_to_log(SITEID, 'blog', 'delete',
        'add_file.php?courseid=' . $courseid . '&id=' . $post->id . '&action=delete&confirm=1', $post->name);

    if (!$status) {
        print_error('deleteposterror', 'block_exaport', $returnurl);
    }
}

function check_assignment_file($cm, $assignment, $fileid) {
    global $USER, $DB;

    $modassign = block_exaport_assignmentversion();
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, $modassign->component, $modassign->filearea, $assignment->submissionid, "filename",
        false);

    return isset($files[$fileid]) ? $files[$fileid] : null;
}
