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

require_once $CFG->libdir.'/formslib.php';
require_once $CFG->libdir . '/filelib.php';

class block_exaport_scorm_upload_form extends moodleform {

	function definition() {
		global $CFG, $USER, $COURSE;
		$mform	=& $this->_form;

		$mform->addElement('header', 'general', "Import");

		$mform->addElement('filepicker', 'attachment', get_string('file'), null, array('accepted_types' => '*'));
		$this->add_action_buttons();

		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);
		$mform->setDefault('courseid', 0);
	}

}
