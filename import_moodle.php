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

global $DB, $OUTPUT, $CFG;
require_once(__DIR__ . '/inc.php');
require_once("{$CFG->dirroot}/blocks/exaport/lib/lib.php");

$output = "";
$courseid = optional_param("courseid", 0, PARAM_INT);

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);
require_capability('block/exaport:importfrommoodle', $context);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}
$url = '/blocks/exaport/import_moodle.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
block_exaport_print_header("importexport", "exportimportmoodleimport");

$modassign = block_exaport_assignmentversion();
$assignments = block_exaport_get_assignments_for_import($modassign);

$table = new html_table();
$table->head = array(get_string("modulename", $modassign->title), get_string("time"),
    get_string("submission_fileortext", "block_exaport"),
    get_string("feedback_fileortext", "block_exaport"),
    get_string("course", "block_exaport"), get_string("action"));
$table->align = array("LEFT", "LEFT", "LEFT", "LEFT", "LEFT", "RIGHT");
$table->size = array("15%", "15%", "20%", "20%", "15%", "15%");
$table->width = "85%";
$table->data = array();

if ($assignments) {
    foreach ($assignments as $assignment) {
        if (!$cm = get_coursemodule_from_instance($modassign->title, $assignment->aid)) {
            print_error('invalidcoursemodule');
        }
        $course = $DB->get_record('course', array("id" => $courseid));
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();

        // Initialize cells for this assignment
        $submissioncell = '';
        $feedbackcell = '';
        $actioncell = '';

        // Check if this assignment has a submission
        $hassubmission = isset($assignment->has_submission) ? $assignment->has_submission : true;
        $hasfile = isset($assignment->has_file) ? $assignment->has_file : false;
        $hasonlinetext = isset($assignment->has_onlinetext) ? $assignment->has_onlinetext : false;

        // SUBMISSION CONTENT
        if ($hassubmission && $assignment->submissionid > 0) {
            // Check for submission files
            if ($hasfile) {
                $files = $fs->get_area_files($context->id, $modassign->component, $modassign->filearea, $assignment->submissionid,
                    "filename", false);

                foreach ($files as $file) {
                    $icon = $OUTPUT->pix_icon(file_file_icon($file), '');
                    $filename = $file->get_filename();
                    $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                        $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out();

                    $submissioncell .= $icon . ' <a href="' . s($url) . '" >' . $filename . '</a><br />';
                }
            }

            // Check for online text submission
            if ($hasonlinetext) {
                $onlinetext = $DB->get_record('assignsubmission_onlinetext', array('submission' => $assignment->submissionid));
                if ($onlinetext && !empty($onlinetext->onlinetext)) {
                    // Get preview of text (first 100 chars)
                    $textpreview = format_text($onlinetext->onlinetext, $onlinetext->onlineformat);
                    $textpreview = strip_tags($textpreview);
                    $textpreview = core_text::substr($textpreview, 0, 100);
                    if (core_text::strlen($textpreview) == 100) {
                        $textpreview .= '...';
                    }

                    $submissioncell .= get_string('onlinetext', 'block_exaport') . ': ' . s($textpreview) . '<br />';
                }
            }
        }

        // FEEDBACK CONTENT
        // Get feedback for this assignment
        $grade = $DB->get_record('assign_grades', array('assignment' => $assignment->aid, 'userid' => $USER->id));
        if ($grade) {
            // Check for feedback files
            $feedbackfilerecord = $DB->get_record('assignfeedback_file',
                array('assignment' => $assignment->aid, 'grade' => $grade->id));

            if ($feedbackfilerecord) {
                $feedbackfiles = $fs->get_area_files($context->id, 'assignfeedback_file', 'feedback_files',
                    $grade->id, 'filename', false);

                foreach ($feedbackfiles as $file) {
                    $icon = $OUTPUT->pix_icon(file_file_icon($file), '');
                    $filename = $file->get_filename();
                    $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                        $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out();

                    $feedbackcell .= $icon . ' <a href="' . s($url) . '" >' . $filename . '</a><br />';
                }
            }

            // Check for feedback comments
            $feedbackcomment = $DB->get_record('assignfeedback_comments',
                array('assignment' => $assignment->aid, 'grade' => $grade->id));
            if ($feedbackcomment && !empty(trim($feedbackcomment->commenttext))) {
                // Get preview of comment text (first 50 chars)
                $commentpreview = strip_tags($feedbackcomment->commenttext);
                $commentpreview = core_text::substr($commentpreview, 0, 50);
                if (core_text::strlen($commentpreview) == 50) {
                    $commentpreview .= '...';
                }
                $feedbackcell .= get_string('feedbackfromteacher', 'block_exaport') . ': ' . s($commentpreview) . '<br />';
            }
        }

        // ACTION BUTTON
        // Determine action button or message
        if (empty($submissioncell) && empty($feedbackcell)) {
            // Neither submission nor feedback available
            $actioncell = get_string('no_submission_no_feedback', 'block_exaport');
        } else {
            // Create import button with sanitized parameters
            $actioncell = '<a href="' . $CFG->wwwroot . '/blocks/exaport/import_moodle_add_file.php?courseid=' .
                (int)$courseid . '&amp;submissionid=' . (int)abs($assignment->submissionid) .
                '&amp;aid=' . (int)$assignment->aid . '">' .
                get_string("add_this_assignment", "block_exaport") . '</a>';
        }

        // Remove all trailing <br /> tags (handle multiple consecutive tags)
        $submissioncell = preg_replace('/(<br \/>)+$/', '', $submissioncell);
        $feedbackcell = preg_replace('/(<br \/>)+$/', '', $feedbackcell);

        // Use dash for empty cells
        if (trim($submissioncell) === '') {
            $submissioncell = '-';
        }
        if (trim($feedbackcell) === '') {
            $feedbackcell = '-';
        }

        // Add single row for this assignment
        $table->data[] = array(
            $assignment->name,
            userdate($assignment->timemodified),
            $submissioncell,
            $feedbackcell,
            $assignment->coursename,
            $actioncell
        );
    }
    $output .= html_writer::table($table);
    echo $output;
} else {
    echo "<p>" . get_string("nomoodleimportyet", "block_exaport") . "</p>";
}
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
