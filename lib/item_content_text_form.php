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

/**
 * Form for creating one Exaport text content block.
 */
class block_exaport_item_content_text_form extends block_exaport_moodleform {

    /**
     * Define the text block form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('hidden', 'pendingcontentblocks');
        $mform->setType('pendingcontentblocks', PARAM_RAW);

        $mform->addElement('text', 'title', get_string('title', 'block_exaport'), [
            'maxlength' => 255,
        ]);
        $mform->setType('title', PARAM_TEXT);

        $editoroptions = $this->_customdata['editoroptions'];
        $mform->addElement('editor', 'content_editor', get_string('blockcontent', 'block_exaport'), null, $editoroptions);
        $mform->setType('content_editor', PARAM_RAW);

        if (empty($this->_customdata['modal'])) {
            $this->add_action_buttons(true, get_string('save'));
        }
    }
}
