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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/portfoliolib.php');

class portfolio_plugin_exaport extends portfolio_plugin_push_base {

    private $lastitem = null;

    public function supported_formats() {
        return array(PORTFOLIO_FORMAT_FILE);
    }

    public static function get_name() {
        return get_string('pluginname', 'portfolio_exaport');
    }

    public static function allows_multiple_instances() {
        return false;
    }

    public function expected_time($callertime) {
        return PORTFOLIO_TIME_LOW;
    }

    public function prepare_package() {
        // We send the files as they are, no prep required.
        return true;
    }

    public function steal_control($stage) {
        if ($stage == PORTFOLIO_STAGE_FINISHED) {
            return false;
            global $CFG;
            return $CFG->wwwroot . '/portfolio/exaport/file.php?id=' . $this->get('exporter')->get('id');
        }
    }

    public function send_package() {
        global $USER, $DB, $CFG;

        require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');

        $files = $this->exporter->get_tempfiles();
        $caller = $this->exporter->get('caller');

        // Try to get assignment context from caller
        $assignment = $this->get_assignment_from_caller($caller);

        if (empty($files) && !$assignment) {
            // No files and no assignment context, nothing to do
            return;
        }

        // Save files to main category
        $categoryid = 0;

        if ($assignment) {
            // We have assignment context - use shared function
            // This handles both submission files and teacher feedback
            foreach ($files as $file) {
                $itemid = block_exaport_create_item_from_assignment($assignment, $file, $categoryid, 0);
                // Store last item for redirect
                $this->lastitem = $DB->get_record('block_exaportitem', array('id' => $itemid));
            }

            // If no files but have assignment (feedback only case)
            if (empty($files)) {
                $itemid = block_exaport_create_item_from_assignment($assignment, null, $categoryid, 0);
                $this->lastitem = $DB->get_record('block_exaportitem', array('id' => $itemid));
            }
        } else {
            // No assignment context - fallback to old behavior
            $fs = get_file_storage();
            foreach ($files as $file) {
                $item = new stdClass;
                $item->userid = $USER->id;
                $item->timemodified = time();
                $item->courseid = 0;
                $item->name = $file->get_filename();
                $item->type = 'file';
                $item->intro = '';
                $item->categoryid = $categoryid;

                if ($item->id = $DB->insert_record('block_exaportitem', $item)) {
                    $filerecord = new stdClass();
                    $filerecord->contextid = context_user::instance($USER->id)->id;
                    $filerecord->component = 'block_exaport';
                    $filerecord->filearea = 'item_file';
                    $filerecord->itemid = $item->id;

                    $fs->create_file_from_storedfile($filerecord, $file);
                    $this->lastitem = $item;
                }
            }
        }
    }

    /**
     * Try to extract assignment information from the portfolio caller
     *
     * @param object $caller The portfolio caller object
     * @return object|null Assignment object with properties: aid, assignment, name, coursename
     */
    protected function get_assignment_from_caller($caller) {
        global $USER, $DB;

        try {
            $cm = null;

            // Check different ways to get the course module
            if (method_exists($caller, 'get_course_module')) {
                $cm = $caller->get_course_module();
            } else if (isset($caller->cm)) {
                $cm = $caller->cm;
            } else if (method_exists($caller, 'get')) {
                $cmid = $caller->get('cmid');
                if ($cmid) {
                    $cm = get_coursemodule_from_id('assign', $cmid);
                }
            }

            // Validate course module
            if (!$cm || !isset($cm->modname) || $cm->modname !== 'assign') {
                return null;
            }

            // Get assignment details
            $assign = $DB->get_record('assign', array('id' => $cm->instance));
            if (!$assign) {
                return null;
            }

            $course = $DB->get_record('course', array('id' => $assign->course));
            if (!$course) {
                return null;
            }

            // Verify course module belongs to the expected course
            if ($cm->course != $course->id) {
                debugging('Course module does not belong to expected course', DEBUG_DEVELOPER);
                return null;
            }

            $context = context_module::instance($cm->id);

            // Check if assignment is available to student
            if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
                debugging('Assignment is hidden and user cannot view hidden activities', DEBUG_DEVELOPER);
                return null; // Hidden assignment
            }

            // Check if module is being deleted
            if (isset($cm->deletioninprogress) && $cm->deletioninprogress) {
                debugging('Assignment is being deleted', DEBUG_DEVELOPER);
                return null;
            }

            // Verify user is enrolled in the course
            if (!is_enrolled($context, $USER->id, '', true)) {
                debugging('User is not enrolled in the course', DEBUG_DEVELOPER);
                return null; // Not enrolled
            }

            // Check assignment dates (if applicable)
            $now = time();
            if ($assign->allowsubmissionsfromdate > 0 && $now < $assign->allowsubmissionsfromdate) {
                debugging('Assignment is not yet available (before start date)', DEBUG_DEVELOPER);
                return null; // Not yet available
            }

            // Build assignment object compatible with shared function
            $assignment = new stdClass();
            $assignment->aid = $assign->id;
            $assignment->assignment = $assign->id;
            $assignment->name = $assign->name;
            $assignment->coursename = $course->fullname;

            return $assignment;
        } catch (Exception $e) {
            // Log error only in DEBUG_DEVELOPER
            debugging('Error extracting assignment from caller.', DEBUG_DEVELOPER);

            // For production, return null gracefully
            // Don't expose error details to users
            return null;
        }
    }

    public function get_interactive_continue_url() {
        global $CFG;
        return $CFG->wwwroot . '/blocks/exaport/item.php?courseid=1&id=' . $this->lastitem->id . '&sesskey=' . sesskey() . '&action=edit';
    }
}
