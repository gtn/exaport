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

require_once $CFG->libdir . '/formslib.php';

class block_exaport_import_scorm_form extends moodleform {

	function definition() {
		global $CFG, $USER, $DB;

		$mform = & $this->_form;


		$mform->addElement('header', 'general', get_string("file", "block_exaport"));
		$mform->addElement('filepicker', 'attachment', get_string('file', 'block_exaport'), null, null);
		$this->add_action_buttons();
	}

}
