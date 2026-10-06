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
        $mform->addElement('hidden', 'blockid', $this->optional_param('blockid', 0, PARAM_INT));
        $mform->setType('blockid', PARAM_INT);
        $mform->addElement('hidden', 'operation', $this->get_operation());
        $mform->setType('operation', PARAM_ALPHA);
        $mform->addElement('hidden', 'contenttype', $type);
        $mform->setType('contenttype', PARAM_ALPHA);

        if ($this->get_operation() === 'delete') {
            $mform->addElement('static', 'confirmation', '', get_string('deletecontentblockconfirm', 'block_exaport'));
            return;
        }

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
            $options = block_exaport_item_content_file_options();
            if ($this->optional_param('blockid', 0, PARAM_INT)) {
                $item = block_exaport_get_editable_content_item(
                    $this->optional_param('itemid', 0, PARAM_INT),
                    $this->optional_param('courseid', 0, PARAM_INT)
                );
                $block = block_exaport_get_item_content_block($item, $this->optional_param('blockid', 0, PARAM_INT));
                $options = block_exaport_item_content_file_options($item, $block);
            }
            $mform->addElement('filemanager', 'files_filemanager', get_string('files'), null, $options);
            if (!$this->optional_param('blockid', 0, PARAM_INT)) {
                $mform->addRule('files_filemanager', get_string('required'), 'required', null, 'client');
            }
        }
    }

    /** Validate file-manager submissions contain a file. */
    public function validation($data, $files): array {
        global $USER;

        $errors = parent::validation($data, $files);
        if (($data['contenttype'] ?? '') !== 'file' || ($data['operation'] ?? '') === 'delete') {
            return $errors;
        }
        $blockid = (int)($data['blockid'] ?? 0);
        $draftitemid = (int)($data['files_filemanager'] ?? 0);
        $draftfiles = get_file_storage()->get_area_files(
            context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        );
        if (!$blockid && (!$draftitemid || !$draftfiles)) {
            $errors['files_filemanager'] = get_string('required');
        }
        if ($draftitemid || $blockid) {
            $item = block_exaport_get_editable_content_item(
                (int)($data['itemid'] ?? 0),
                (int)($data['courseid'] ?? 0)
            );
            if ($blockid) {
                block_exaport_get_item_content_block($item, $blockid);
            }
            $validationerror = block_exaport_validate_item_content_file_draft(
                $draftitemid,
                $blockid ?: null
            );
            if ($validationerror !== null) {
                $errors['files_filemanager'] = $validationerror;
            }
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
        $item = block_exaport_get_editable_content_item(
            $this->optional_param('itemid', 0, PARAM_INT),
            $this->optional_param('courseid', 0, PARAM_INT)
        );
        $blockid = $this->optional_param('blockid', 0, PARAM_INT);
        if ($blockid) {
            $block = block_exaport_get_item_content_block($item, $blockid);
            if ($block->type !== $this->get_content_type()) {
                throw new \invalid_parameter_exception('Content block type does not match the form');
            }
        }
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
        $type = $this->get_content_type();
        $data = (object)[
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'itemid' => $this->optional_param('itemid', 0, PARAM_INT),
            'blockid' => $this->optional_param('blockid', 0, PARAM_INT),
            'operation' => $this->get_operation(),
            'contenttype' => $type,
            'title' => '',
        ];
        $item = block_exaport_get_editable_content_item((int)$data->itemid, (int)$data->courseid);
        $block = $data->blockid ? block_exaport_get_item_content_block($item, (int)$data->blockid) : null;
        if ($block) {
            $data->title = $block->title;
        }
        if ($data->operation === 'delete') {
            $this->set_data($data);
            return;
        }
        $context = context_user::instance((int)$item->userid);
        if ($type === 'text') {
            $data->content = $block->content ?? '';
            $data->contentformat = $block->contentformat ?? FORMAT_HTML;
            $data = file_prepare_standard_editor($data, 'content', block_exaport_item_content_editor_options(),
                $context, 'block_exaport', 'item_content_text', (int)$data->blockid);
        } else if ($type === 'link') {
            $data->url = $block->url ?? '';
        } else if ($type === 'file') {
            $data->files = '';
            $options = block_exaport_item_content_file_options($item, $block);
            $data = file_prepare_standard_filemanager($data, 'files', $options,
                $context, 'block_exaport', 'item_content_file', (int)$data->blockid);
        }
        $this->set_data($data);
    }

    /** Persist a validated form submission and return the refreshed section HTML. */
    public function process_dynamic_submission(): array {
        global $DB;

        require_sesskey();
        $data = $this->get_data();
        $item = block_exaport_get_editable_content_item((int)$data->itemid, (int)$data->courseid);
        $transaction = $DB->start_delegated_transaction();
        $block = !empty($data->blockid)
            ? block_exaport_get_item_content_block($item, (int)$data->blockid)
            : block_exaport_create_content_block(
                (int)$data->itemid,
                $data->contenttype,
                $data->title,
                $data->contenttype === 'link' ? $data->url : ''
            );
        if (($data->operation ?? '') === 'delete') {
            block_exaport_delete_item_content_block($item, $block);
            $transaction->allow_commit();
            return ['content' => block_exaport_render_item_content_blocks((int)$data->courseid, $item)];
        }
        $DB->update_record('block_exaportitemblock', (object)[
            'id' => $block->id,
            'title' => $data->title,
            'url' => $data->contenttype === 'link' ? $data->url : '',
            'timemodified' => time(),
        ]);
        $context = context_user::instance((int)$item->userid);
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
            $options = block_exaport_item_content_file_options($item, $block);
            file_postupdate_standard_filemanager($data, 'files', $options,
                $context, 'block_exaport', 'item_content_file', $block->id);
            if (!block_exaport_get_item_content_files((int)$item->userid, (int)$block->id)) {
                // Removing the final file intentionally removes its now-empty content block.
                block_exaport_delete_item_content_block($item, $block);
            }
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

    /** Return the requested operation. */
    private function get_operation(): string {
        $operation = $this->optional_param('operation', 'save', PARAM_ALPHA);
        if (!in_array($operation, ['save', 'delete'], true)) {
            throw new \coding_exception('Unsupported Exaport item content operation');
        }
        return $operation;
    }
}
