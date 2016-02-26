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

class block_exaport_personal_information_form extends moodleform {

	function definition() {

		global $CFG;
		$mform	=& $this->_form;

//		$mform->addElement('editor', 'description', get_string('message', 'forum'), array('cols'=>50, 'rows'=>30));
//		$mform->setType('description', PARAM_RAW);
//		$mform->addRule('description', get_string('required'), 'required', null, 'client');

				$mform->addElement('editor', 'description_editor', get_string('steckbrief', 'block_exaport'), null,
					array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
				
		$mform->addElement('hidden', 'cataction');
		$mform->setType('cataction', PARAM_ALPHA);
		
		$mform->addElement('hidden', 'compid');
		$mform->setType('compid', PARAM_INT);
		
		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);
		
		$mform->addElement('hidden', 'edit');
		$mform->setType('edit', PARAM_BOOL);
		
		 $this->add_action_buttons();
	}

}
