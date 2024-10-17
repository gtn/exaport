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

require_once($CFG->libdir . '/formslib.php');

class block_exaport_import_scorm_form extends block_exaport_moodleform {

    public function definition() {
        global $CFG, $USER, $DB;

        $mform = &$this->_form;

        $mform->addElement('header', 'general', get_string("file", "block_exaport"));
        $mform->addElement('filepicker', 'attachment', get_string('file', 'block_exaport'), null, null);
        $mform->add_exaport_help_button('attachment', 'forms.import_scrom.file');
        $this->add_action_buttons();
    }

}
