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

use block_exaport\item_category_helper;

require_once($CFG->libdir . '/portfoliolib.php');
require_once(__DIR__ . '/../item_content_helpers.php');

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

        // Save files to first category, so read that id.
        // $categoryid = $DB->get_field_sql("SELECT id FROM {block_exaportcate} ".
        // " WHERE userid = ? ORDER BY name LIMIT 1", array($USER->id));
        // Save to main category (uncategorized).
        $categoryid = 0;

        foreach ($files as $file) {
            $transaction = $DB->start_delegated_transaction();

            $item = new stdClass;
            $item->userid = $USER->id;
            $item->timemodified = time();
            $item->courseid = 0;
            $item->name = $file->get_filename();
            $item->type = 'file';
            $item->intro = '';
            $item->url = '';
            $item->attachment = '';

            // Insert.
            if ($item->id = $DB->insert_record('block_exaportitem', $item)) {
                if ($categoryid > 0) {
                    item_category_helper::sync_item_categories($item->id, [$categoryid]);
                }

                block_exaport_import_stored_file_into_content_block($item, $file);

                $this->lastitem = $item;
                $transaction->allow_commit();
            }
        }
    }

    public function get_interactive_continue_url() {
        global $CFG;
        return $CFG->wwwroot . '/blocks/exaport/item.php?courseid=1&id=' . $this->lastitem->id . '&sesskey=' . sesskey() . '&action=edit';
    }
}
