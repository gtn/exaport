<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require_once($CFG->libdir . '/portfoliolib.php');

class portfolio_plugin_exaport extends portfolio_plugin_push_base {

	private $_lastItem = null;
	
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
			// not files, do nothing
			return;
		}

		$fs = get_file_storage();

		// save files to first category, so read that id
		$categoryId = $DB->get_field_sql("SELECT id FROM {block_exaportcate} WHERE userid = ? ORDER BY name LIMIT 1", array($USER->id));
		
		foreach ($files as $file) {

			$item = new stdClass;
			$item->userid = $USER->id;
			$item->timemodified = time();
			$item->courseid = 0;
			$item->name = $file->get_filename();
			$item->type = 'file';
			$item->intro = '';
			$item->categoryid = $categoryId;
			
			// Insert
			if ($item->id = $DB->insert_record('block_exaportitem', $item)) {
			
				$filerecord = new stdClass();
				$filerecord->contextid = context_user::instance($USER->id)->id;
				$filerecord->component = 'block_exaport';
				$filerecord->filearea  = 'item_file';
				$filerecord->itemid	= $item->id;

				$fs->create_file_from_storedfile($filerecord, $file);
				
				$this->_lastItem = $item;
			}
		}
	}

	public function get_interactive_continue_url() {
		global $CFG;
		// return $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=1';
		return $CFG->wwwroot . '/blocks/exaport/item.php?courseid=1&id='.$this->_lastItem->id.'&sesskey='.sesskey().'&action=edit';
	}
}
