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

namespace block_exaport\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Structured item block add/edit form.
 */
class item_content_block_edit_form extends \block_exaport_moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $type = \block_exaport\item_content_mutation_helper::require_supported_block_type($this->_customdata['type']);
        $itemlabel = \block_exaport\item_content_mutation_helper::get_block_type_label($type);

        $mform->addElement('header', 'general',
            get_string($this->_customdata['action'] === 'edit' ? 'itemblockeditheading' : 'itemblockaddheading',
                'block_exaport', $itemlabel));

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('hidden', 'blockid');
        $mform->setType('blockid', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_ALPHA);

        $mform->addElement('hidden', 'access');
        $mform->setType('access', PARAM_TEXT);

        $mform->addElement('hidden', 'backtype');
        $mform->setType('backtype', PARAM_ALPHA);

        $mform->addElement('text', 'title', get_string('title', 'block_exaport'), 'maxlength="255" size="60"');
        $mform->setType('title', PARAM_TEXT);

        if ($type === 'link') {
            $mform->addElement('text', 'url', get_string('url', 'block_exaport'), 'maxlength="255" size="60"');
            $mform->setType('url', PARAM_RAW_TRIMMED);
            $mform->addRule('url', get_string('urlnotempty', 'block_exaport'), 'required', null, 'client');
        }

        $mform->addElement(
            'editor',
            'content_editor',
            get_string('itemblockcontent', 'block_exaport'),
            null,
            \block_exaport\item_content_mutation_helper::get_editor_options($this->_customdata['item'])
        );
        $mform->setType('content_editor', PARAM_RAW);

        if (in_array($type, ['file', 'media'], true)) {
            $mform->addElement(
                'filemanager',
                'attachments',
                get_string('file', 'block_exaport'),
                null,
                \block_exaport\item_content_mutation_helper::get_attachment_options($type)
            );
        }

        $submitlabel = get_string($this->_customdata['action'] === 'edit' ? 'savechanges' : 'add');
        $this->add_action_buttons(true, $submitlabel);
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!\block_exaport\item_content_mutation_helper::is_supported_block_type((string)($data['type'] ?? ''))) {
            $errors['type'] = get_string('itemblockunsupportedtype', 'block_exaport');
            return $errors;
        }

        $type = \block_exaport\item_content_mutation_helper::require_supported_block_type($data['type']);

        if ($type === 'link') {
            $normalized = \block_exaport\item_content_mutation_helper::normalize_url((string)($data['url'] ?? ''));
            if ($normalized === '' || clean_param($normalized, PARAM_URL) !== $normalized) {
                $errors['url'] = get_string('invalidurl');
            }
        }

        return $errors;
    }
}
