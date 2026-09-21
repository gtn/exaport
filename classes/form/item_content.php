<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport\form;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib/item_content_helpers.php');

use context;
use context_system;
use context_user;
use core_form\dynamic_form;
use moodle_url;

/**
 * Moodle dynamic form for adding an item content block.
 *
 * @package block_exaport
 */
class item_content extends dynamic_form {

    /** @var string[] Supported content block types. */
    private const TYPES = ['text', 'link', 'file'];

    /** Define fields for the selected content type. */
    protected function definition(): void {
        $mform = $this->_form;
        $type = $this->get_content_type();

        $mform->addElement('hidden', 'courseid', $this->optional_param('courseid', 0, PARAM_INT));
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'itemid', $this->optional_param('itemid', 0, PARAM_INT));
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'contenttype', $type);
        $mform->setType('contenttype', PARAM_ALPHA);

        $mform->addElement('text', 'title', get_string('title', 'block_exaport'), ['maxlength' => 255]);
        $mform->setType('title', PARAM_TEXT);

        if ($type === 'text') {
            $mform->addElement('editor', 'content_editor', get_string('blockcontent', 'block_exaport'), null,
                block_exaport_item_content_editor_options());
            $mform->setType('content_editor', PARAM_RAW);
        } else if ($type === 'link') {
            $mform->addElement('url', 'url', get_string('url', 'block_exaport'), ['size' => 60]);
            $mform->setType('url', PARAM_URL);
            $mform->addRule('url', get_string('required'), 'required', null, 'client');
        } else {
            $mform->addElement('filemanager', 'files_filemanager', get_string('files'), null,
                block_exaport_item_content_file_options());
            $mform->addRule('files_filemanager', get_string('required'), 'required', null, 'client');
        }
    }

    /** Validate file-manager submissions contain a file. */
    public function validation($data, $files): array {
        global $USER;

        $errors = parent::validation($data, $files);
        if (($data['contenttype'] ?? '') !== 'file') {
            return $errors;
        }
        $draftitemid = (int)($data['files_filemanager'] ?? 0);
        $draftfiles = get_file_storage()->get_area_files(
            context_user::instance($USER->id)->id,
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

    /** Return the context validated by Moodle's dynamic-form external service. */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /** Check access before Moodle renders or processes the form. */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('block/exaport:use', $this->get_context_for_dynamic_submission());
        block_exaport_get_editable_content_item(
            $this->optional_param('itemid', 0, PARAM_INT),
            $this->optional_param('courseid', 0, PARAM_INT)
        );
    }

    /** Return the canonical standalone URL used by editor autosave and form elements. */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/blocks/exaport/item_content_' . $this->get_content_type() . '.php', [
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'itemid' => $this->optional_param('itemid', 0, PARAM_INT),
        ]);
    }

    /** Prepare initial editor or file-manager drafts. */
    public function set_data_for_dynamic_submission(): void {
        global $USER;

        $type = $this->get_content_type();
        $data = (object)[
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'itemid' => $this->optional_param('itemid', 0, PARAM_INT),
            'contenttype' => $type,
            'title' => '',
        ];
        $context = context_user::instance($USER->id);
        if ($type === 'text') {
            $data->content = '';
            $data->contentformat = FORMAT_HTML;
            $data = file_prepare_standard_editor($data, 'content', block_exaport_item_content_editor_options(),
                $context, 'block_exaport', 'item_content_text', 0);
        } else if ($type === 'file') {
            $data->files = '';
            $data = file_prepare_standard_filemanager($data, 'files', block_exaport_item_content_file_options(),
                $context, 'block_exaport', 'item_content_file', 0);
        }
        $this->set_data($data);
    }

    /** Persist a validated form submission and return the refreshed section HTML. */
    public function process_dynamic_submission(): array {
        global $DB, $USER;

        require_sesskey();
        $data = $this->get_data();
        $item = block_exaport_get_editable_content_item((int)$data->itemid, (int)$data->courseid);
        $transaction = $DB->start_delegated_transaction();
        $block = block_exaport_new_content_block(
            (int)$data->itemid,
            $data->contenttype,
            $data->title,
            $data->contenttype === 'link' ? $data->url : ''
        );
        $block->id = $DB->insert_record('block_exaportitemblock', $block);
        $context = context_user::instance($USER->id);
        if ($data->contenttype === 'text') {
            $data = file_postupdate_standard_editor($data, 'content', block_exaport_item_content_editor_options(),
                $context, 'block_exaport', 'item_content_text', $block->id);
            $DB->update_record('block_exaportitemblock', (object)[
                'id' => $block->id,
                'content' => $data->content,
                'contentformat' => $data->contentformat,
                'timemodified' => time(),
            ]);
        } else if ($data->contenttype === 'file') {
            file_postupdate_standard_filemanager($data, 'files', block_exaport_item_content_file_options(),
                $context, 'block_exaport', 'item_content_file', $block->id);
        }
        $transaction->allow_commit();
        return ['content' => block_exaport_render_item_content_blocks((int)$data->courseid, $item)];
    }

    /** Return and validate the requested content type. */
    private function get_content_type(): string {
        $type = $this->optional_param('contenttype', '', PARAM_ALPHA);
        if (!in_array($type, self::TYPES, true)) {
            throw new \coding_exception('Unsupported Exaport item content block type');
        }
        return $type;
    }
}
