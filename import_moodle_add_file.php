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

global $PAGE, $DB, $OUTPUT, $SITE;

use function block_exaport\common\print_error;

require_once(__DIR__ . '/inc.php');

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$confirm = optional_param('confirm', '', PARAM_BOOL);
$fileid = optional_param('fileid', '', PARAM_FILE);
$submissionid = optional_param('submissionid', 0, PARAM_INT);
$gradeid = optional_param('gradeid', 0, PARAM_INT);  // NEW
$aid = optional_param('aid', 0, PARAM_INT); // Assignment ID for no-submission case
$nosubmission = optional_param('nosubmission', 0, PARAM_INT); // Flag for no submission
$onlinetext = optional_param('onlinetext', 0, PARAM_INT); // Flag for online text submission

$modassign = block_exaport_assignmentversion();

// Get assignment data
if ($modassign->new) {
    // Build base query
    $sql = "SELECT s.id AS submissionid, a.id AS aid, s.assignment, s.timemodified, " .
        " a.name, a.course, c.fullname AS coursename ";
    $params = array($USER->id);
    
    if ($nosubmission && $gradeid > 0) {
        // Feedback-only case: join via grades
        $sql .= " FROM {assign} a " .
            " LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = ? " .
            " INNER JOIN {assign_grades} ag ON ag.assignment = a.id AND ag.userid = ? AND ag.id = ? " .
            " LEFT JOIN {course} c on a.course = c.id " .
            " WHERE a.id = ?";
        $params[] = $USER->id;
        $params[] = $gradeid;
        $params[] = $aid;
    } else if ($submissionid > 0) {
        // Submission case
        $sql .= " FROM {assign_submission} s " .
            " INNER JOIN {assign} a ON s.assignment=a.id " .
            " LEFT JOIN {course} c on a.course = c.id " .
            " WHERE s.userid=? AND s.id=?";
        $params[] = $submissionid;
    } else if ($aid > 0) {
        // Assignment case without specific submission or grade ID
        // This handles cases like online text submissions or any other submission type
        $sql .= " FROM {assign} a " .
            " LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = ? " .
            " LEFT JOIN {course} c on a.course = c.id " .
            " WHERE a.id = ?";
        $params[] = $aid;
    } else {
        \block_exaport\common\print_error('invalidparameters', 'block_exaport');
    }
    
    $assignment = $DB->get_record_sql($sql, $params);
} else {
    // Legacy code unchanged
    $assignment = $DB->get_record_sql("SELECT s.id AS submissionid, a.id AS aid, s.assignment, s.timemodified, " .
        " a.name, a.course, a.assignmenttype, c.fullname AS coursename " .
        " FROM {assignment_submissions} s " .
        " JOIN {assignment} a ON s.assignment = a.id " .
        " LEFT JOIN {course} c on a.course = c.id " .
        " WHERE s.userid=? AND s.id=?", array($USER->id, $submissionid));
}

if (!$assignment) {
    print_error("invalidassignment", "block_exaport");
}

$cm = get_coursemodule_from_instance($modassign->title, $assignment->aid);
if (!$cm) {
    print_error('invalidcoursemodule');
}

// Security validations for course module and assignment
$modulecontext = context_module::instance($cm->id);

// Validate course module modname is 'assign'
if ($cm->modname !== 'assign') {
    print_error('invalidmodule', 'block_exaport');
}

// Verify course module belongs to the expected course
if ($cm->course != $assignment->course) {
    print_error('invalidcoursemodule');
}

// Check if assignment is visible to student
if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $modulecontext)) {
    print_error('assignmentnotvisible', 'block_exaport');
}

// Check if module is being deleted
if (isset($cm->deletioninprogress) && $cm->deletioninprogress) {
    print_error('modulebeingdeleted', 'block_exaport');
}

// Verify user is enrolled in the course
if (!is_enrolled($modulecontext, $USER->id, '', true)) {
    print_error('notenrolled', 'block_exaport');
}

// For no-submission case, verify feedback actually exists
if ($nosubmission && $gradeid > 0) {
    // Get grade record
    $grade = $DB->get_record('assign_grades', array('id' => $gradeid, 'userid' => $USER->id));
    if (!$grade) {
        \block_exaport\common\print_error('invalidgradeid', 'block_exaport');
    }
    // Verify this grade belongs to this assignment
    if ($grade->assignment != $assignment->aid) {
        \block_exaport\common\print_error('invalidgradeid', 'block_exaport');
    }
    // Verify grade is actually released (grade >= 0)
    if ($grade->grade < 0) {
        print_error('nofeedbackavailable', 'block_exaport');
    }

    // Verify assignment record exists
    $assignrecord = $DB->get_record('assign', array('id' => $aid));
    if (!$assignrecord) {
        print_error('invalidassignment', 'block_exaport');
    }
} else if ($submissionid > 0) {
    // Verify submission exists and belongs to user
    $submission = $DB->get_record('assign_submission', 
        array('id' => $submissionid, 'userid' => $USER->id, 'assignment' => $assignment->aid));
    if (!$submission) {
        \block_exaport\common\print_error('invalidsubmissionid', 'block_exaport');
    }
}

$post = new stdClass();
$checkedfile = null;
$checkedonlinetext = null;
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

if ($submissionid == 0 && $gradeid == 0 && !$nosubmission) {
    error("No assignment given!");
}

// Check for submission content
if ($nosubmission && $gradeid > 0) {
    // Check for feedback files
    $fs = get_file_storage();
    $filecontext = context_module::instance($cm->id);
    $files = $fs->get_area_files($filecontext->id, 'assignfeedback_file', 'feedback_files', $gradeid, "filename", false);
    if (empty($files) && empty($fileid)) {
        \block_exaport\common\print_error('nofeedbackfiles', 'block_exaport');
    }
} else if (!$nosubmission) {
    // Check for submission file if fileid is provided
    if (!empty($fileid)) {
        if (!($checkedfile = check_assignment_file($cm, $assignment, $fileid))) {
            print_error("invalidfileatthisassignment", "block_exaport");
        }
    }
    
    // Check for online text submission if onlinetext flag is set
    if ($onlinetext) {
        $checkedonlinetext = $DB->get_record('assignsubmission_onlinetext', array('submission' => $assignment->submissionid));
        if (!$checkedonlinetext || empty($checkedonlinetext->onlinetext)) {
            print_error("invalidonlinetextatthisassignment", "block_exaport");
        }
    }
}
// If no fileid and no onlinetext flag, we might have a submission without files/text
// This is OK - we'll create artifact with just the assignment name

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
    $existing->type = ($nosubmission || $onlinetext) ? 'note' : 'file';
    $existing->dir = "";
    $existing->name = $assignment->name; // Use assignment name
    $existing->intro = "";
    $existing->filename = $checkedfile ? $checkedfile->get_filename() : '';
    if ($checkedonlinetext) {
        $existing->intro = format_text($checkedonlinetext->onlinetext, $checkedonlinetext->onlineformat);
    }
    $existing->submission = $submissionid;
    $existing->submissionid = $submissionid;
    $existing->gradeid = $gradeid;
    $existing->fileid = $fileid;
    $existing->nosubmission = $nosubmission;
    $existing->onlinetext = $onlinetext;
    $existing->aid = $aid;
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
            do_add($cm, $fromform, $exteditform, $returnurl, $courseid, $checkedfile, $assignment, $checkedonlinetext);
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
        $post->gradeid = $gradeid;
        $post->fileid = $fileid;
        $post->name = $assignment->name; // Prefill the title with assignment name
        $post->nosubmission = $nosubmission;
        $post->onlinetext = $onlinetext;
        $post->aid = $aid;
        // Prefill intro field with online text content for display during creation
        if ($checkedonlinetext) {
            $post->intro_editor = array(
                'text' => format_text($checkedonlinetext->onlinetext, $checkedonlinetext->onlineformat),
            );
        }
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

// Only show files in block_eportfolio_center, never online text
if ($checkedfile) {
    echo $OUTPUT->box(block_exaport_print_file($checkedfile));
} else {
    // If no file, show message (online text should be in intro_editor, not here)
    echo $OUTPUT->box(get_string('nosubmissionfile', 'block_exaport'));
}
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
 * Write a new item from assignment into database using shared function
 */
function do_add($cm, $post, $blogeditform, $returnurl, $courseid, $checkedfile, $assignment, $onlinetextobj = null) {
    global $CFG, $USER, $DB, $COURSE;

    // Prepare online text if available
    $onlinetext = null;
    if ($onlinetextobj && !empty($onlinetextobj->onlinetext)) {
        $onlinetext = format_text($onlinetextobj->onlinetext, $onlinetextobj->onlineformat);
    }

    // Use the shared function to create item with feedback
    $itemid = block_exaport_create_item_from_assignment($assignment, $checkedfile, $post->categoryid, $courseid, $onlinetext);

    block_exaport_add_to_log(SITEID, 'bookmark', 'add', 'import_moodle_add_file.php?courseid=' . $courseid . '&id=' . $itemid,
        $assignment->name);
}

/**
 * Delete blog post from database
 */
function do_delete($post, $returnurl, $courseid) {

    global $DB;
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
