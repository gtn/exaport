<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Form for creating one Exaport link content block.
 */
class block_exaport_item_content_link_form extends block_exaport_moodleform {

    /**
     * Define the link block form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'block_exaport'), ['maxlength' => 255]);
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement('url', 'url', get_string('url', 'block_exaport'), ['size' => 60]);
        $mform->setType('url', PARAM_URL);
        $mform->addRule('url', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('addcontentblock', 'block_exaport'));
    }
}

/**
 * Form for creating one Exaport file content block.
 */
class block_exaport_item_content_file_form extends block_exaport_moodleform {

    /**
     * Define the file block form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'block_exaport'), ['maxlength' => 255]);
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement(
            'filemanager',
            'files_filemanager',
            get_string('files'),
            null,
            $this->_customdata['fileoptions']
        );
        $mform->addRule('files_filemanager', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('addcontentblock', 'block_exaport'));
    }

    /**
     * Ensure the draft contains at least one real file.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);
        $draftitemid = (int)($data['files_filemanager'] ?? 0);
        $usercontext = context_user::instance($USER->id);
        $draftfiles = get_file_storage()->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        );
        if (!$draftitemid || !$draftfiles) {
            $errors['files_filemanager'] = get_string('required');
        }

        return $errors;
    }
}
