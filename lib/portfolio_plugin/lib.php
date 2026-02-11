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
        global $USER, $DB;

        $files = $this->exporter->get_tempfiles();
        if (empty($files)) {
            // Not files, do nothing.
            return;
        }

        $fs = get_file_storage();
        $caller = $this->exporter->get('caller');

        // Save files to first category, so read that id.
        // $categoryid = $DB->get_field_sql("SELECT id FROM {block_exaportcate} ".
        // " WHERE userid = ? ORDER BY name LIMIT 1", array($USER->id));
        // Save to main category. SZ: 30.09.2020
        $categoryid = 0;

        foreach ($files as $file) {

            $item = new stdClass;
            $item->userid = $USER->id;
            $item->timemodified = time();
            $item->courseid = 0;
            $item->name = $file->get_filename();
            $item->type = 'file';
            $item->intro = '';
            $item->categoryid = $categoryid;

            // Insert.
            if ($item->id = $DB->insert_record('block_exaportitem', $item)) {

                $filerecord = new stdClass();
                $filerecord->contextid = context_user::instance($USER->id)->id;
                $filerecord->component = 'block_exaport';
                $filerecord->filearea = 'item_file';
                $filerecord->itemid = $item->id;

                $fs->create_file_from_storedfile($filerecord, $file);

                $this->lastitem = $item;

                // NEW: Save feedback files if available
                $this->save_feedback_files($item->id, $caller, $fs);
            }
        }
    }

    /**
     * Save teacher feedback files as a comment on the portfolio item
     *
     * @param int $itemid The portfolio item ID
     * @param object $caller The portfolio caller object
     * @param file_storage $fs File storage instance
     */
    protected function save_feedback_files($itemid, $caller, $fs) {
        global $USER, $DB;

        // Check if caller has feedback files
        if (!method_exists($caller, 'get_feedback_files')) {
            return;
        }

        $feedbackfiles = $caller->get_feedback_files();

        if (empty($feedbackfiles)) {
            return;
        }

        // Create a comment entry to hold the feedback files
        $comment = new stdClass();
        $comment->itemid = $itemid;
        $comment->userid = $USER->id;
        $comment->entry = get_string('feedbackfromteacher', 'block_exaport');
        $comment->timemodified = time();

        $comment->id = $DB->insert_record('block_exaportitemcomm', $comment);

        // Save each feedback file to the comment
        $filerecordbase = new stdClass();
        $filerecordbase->contextid = context_system::instance()->id;
        $filerecordbase->component = 'block_exaport';
        $filerecordbase->filearea = 'item_comment_file';
        $filerecordbase->itemid = $comment->id;

        foreach ($feedbackfiles as $feedbackfile) {
            $fs->create_file_from_storedfile($filerecordbase, $feedbackfile);
        }
    }

    public function get_interactive_continue_url() {
        global $CFG;
        return $CFG->wwwroot . '/blocks/exaport/item.php?courseid=1&id=' . $this->lastitem->id . '&sesskey=' . sesskey() . '&action=edit';
    }
}
