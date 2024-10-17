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

class block_exaport_personal_information_form extends block_exaport_moodleform {

    public function definition() {

        global $CFG;
        $mform =& $this->_form;

        $mform->addElement('editor', 'description_editor', get_string('steckbrief', 'block_exaport'), null,
            array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));
        $mform->add_exaport_help_button('description_editor', 'forms.personal_info.description_editor');

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
