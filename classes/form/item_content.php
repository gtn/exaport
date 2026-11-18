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
use block_exaport\local\item_linked_content;
use block_exaport\local\item_editor;
use block_exaport\local\item_content_edit;

/**
 * Moodle dynamic form for adding an item content block.
 *
 * @package block_exaport
 */
class item_content extends dynamic_form {

    /** Define fields for the selected content type. */
    protected function definition(): void {
        global $USER;
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
        $mform->addElement('hidden', 'pending', $this->is_pending() ? 1 : 0);
        $mform->setType('pending', PARAM_BOOL);
        $mform->addElement('hidden', 'pendingblock', $this->optional_param('pendingblock', '', PARAM_RAW));
        $mform->setType('pendingblock', PARAM_RAW);

        if ($this->get_operation() === 'delete') {
            $message = $this->is_pending() ? 'deletecontentblockpendingconfirm' : 'deletecontentblockconfirm';
            $mform->addElement('static', 'confirmation', '', get_string($message, 'block_exaport'));
            if (!empty($this->_customdata['standalone'])) {
                $this->add_action_buttons(true, get_string('delete'));
            }
            return;
        }

        $mform->addElement('text', 'title', get_string('title', 'block_exaport'), ['maxlength' => 255]);
        $mform->setType('title', PARAM_TEXT);

        if ($type === 'text') {
            $mform->addElement('editor', 'content_editor', get_string('blockcontent', 'block_exaport'), null,
                block_exaport_item_content_editor_options());
            $mform->setType('content_editor', PARAM_RAW);
            if ($this->is_pending()) {
                // maxfiles=0 hides Moodle's draft-ID input; retain imported media without enabling uploads.
                $mform->addElement('hidden', 'content_editor[itemid]', 0);
                $mform->setType('content_editor[itemid]', PARAM_INT);
            }
        } else if ($type === 'link') {
            $mform->addElement('url', 'url', get_string('url', 'block_exaport'), ['size' => 60]);
            $mform->setType('url', PARAM_URL);
            $mform->addRule('url', get_string('required'), 'required', null, 'client');
        } else if (in_array($type, item_linked_content::TYPES, true)) {
            $item = $this->item_context();
            $ownerid = (int)($item->userid ?? $USER->id);
            $mform->addElement('static', 'linkednotice', '', get_string('linkedcontentnotice', 'block_exaport'));
            if ($type === 'personal') {
                $sourceurl = new moodle_url('/user/profile.php', ['id' => $ownerid]);
                $sourcelabel = get_string('viewprofile');
            } else if ($type === 'badge') {
                $sourceurl = new moodle_url('/badges/mybadges.php');
                $sourcelabel = get_string('badges', 'badges');
            } else {
                $sourceurl = new moodle_url('/blocks/exaport/resume.php', [
                    'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
                ]);
                $sourcelabel = get_string('resume_my', 'block_exaport');
            }
            $mform->addElement('static', 'linkedsource', '', \html_writer::link($sourceurl, $sourcelabel,
                ['target' => '_blank', 'rel' => 'noopener noreferrer']));
            $mform->addElement('autocomplete', 'selection', get_string('fieldstoshow', 'block_exaport'),
                item_linked_content::options($type, $ownerid), ['multiple' => true]);
            $mform->setType('selection', PARAM_TEXT);
            if ($type === 'cv') {
                $mform->addElement('advcheckbox', 'withfiles', get_string('cofigureblock_cvinfo_withfiles', 'block_exaport'));
                $mform->setDefault('withfiles', 0);
            }
        } else {
            $options = block_exaport_item_content_file_options();
            if ($this->optional_param('blockid', 0, PARAM_INT)) {
                $item = $this->item_context();
                $block = block_exaport_get_item_content_block($item, $this->optional_param('blockid', 0, PARAM_INT));
                $options = block_exaport_item_content_file_options($item, $block);
            }
            $mform->addElement('filemanager', 'files_filemanager', get_string('files'), null, $options);
            if (!$this->optional_param('blockid', 0, PARAM_INT)) {
                $mform->addRule('files_filemanager', get_string('required'), 'required', null, 'client');
            }
        }
        if (!empty($this->_customdata['standalone'])) {
            $this->add_action_buttons();
        }
    }

    /** Validate owner-specific selections and file-manager submissions. */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($this->is_pending()) {
            try {
                $seed = $this->pending_state($data);
                if (($data['operation'] ?? '') !== 'delete') {
                    $item = item_editor::context((int)$data['itemid'], (int)$data['courseid']);
                    $block = $seed['id'] ? block_exaport_get_item_content_block($item, $seed['id']) : null;
                    // Quota belongs to the whole pending item; validate individual file limits here.
                    item_content_edit::normalise(item_content_edit::from_form((object)$data, $seed['key']),
                        $item, $block, false);
                }
            } catch (\moodle_exception | \block_exacomp_permission_exception $exception) {
                $field = ['file' => 'files_filemanager', 'text' => 'content_editor', 'link' => 'url']
                    [$data['contenttype'] ?? ''] ?? 'selection';
                $errors[$field] = $exception instanceof \invalid_parameter_exception &&
                        in_array($data['contenttype'] ?? '', item_linked_content::TYPES, true)
                    ? get_string('linkedcontentselectionrequired', 'block_exaport') : $exception->getMessage();
                if (($data['operation'] ?? '') === 'delete') {
                    $errors['confirmation'] = $exception->getMessage();
                }
            }
            return $errors;
        }
        if (in_array($data['contenttype'] ?? '', item_linked_content::TYPES, true) &&
                ($data['operation'] ?? '') !== 'delete') {
            $item = block_exaport_get_editable_content_item((int)$data['itemid'], (int)$data['courseid']);
            try {
                item_linked_content::configuration($data['contenttype'], (int)$item->userid,
                    (array)($data['selection'] ?? []), !empty($data['withfiles']));
            } catch (\invalid_parameter_exception $e) {
                $errors['selection'] = get_string('linkedcontentselectionrequired', 'block_exaport');
            }
        }
        if (($data['contenttype'] ?? '') !== 'file' ||
                ($data['operation'] ?? '') === 'delete') {
            return $errors;
        }
        $item = block_exaport_get_editable_content_item((int)$data['itemid'], (int)$data['courseid']);
        $block = !empty($data['blockid']) ? block_exaport_get_item_content_block($item, (int)$data['blockid']) : null;
        $draftitemid = (int)($data['files_filemanager'] ?? 0);
        if ($error = block_exaport_item_content_file_submission_error($item, $block, $draftitemid)) {
            $errors['files_filemanager'] = get_string($error[0], $error[1], $error[2]);
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
        if ($this->is_pending()) {
            $this->pending_state();
            return;
        }
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
        if ($this->is_pending()) {
            $seed = $this->pending_state();
            $item = $this->item_context();
            $block = $seed['id'] ? block_exaport_get_item_content_block($item, $seed['id']) : null;
            // Each opening gets its own file drafts. A validation rerender keeps the submitted
            // working drafts; copying the accepted version again would lose the user's edits.
            $values = !$this->is_submitted() && $data->operation !== 'delete'
                ? item_content_edit::prepare($seed, $item, $block) : [];
            $this->set_data((object)array_merge($values, (array)$data, [
                'title' => $values['title'] ?? $seed['title'],
                'pendingblock' => $this->optional_param('pendingblock', '', PARAM_RAW),
            ]));
            return;
        }
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
        } else if (in_array($type, item_linked_content::TYPES, true)) {
            $selection = $block ? item_linked_content::selection($block, (int)$item->userid) : [];
            $options = item_linked_content::options($type, (int)$item->userid);
            $data->selection = array_values(array_intersect((array)($selection['selection'] ?? []), array_keys($options)));
            $data->withfiles = !empty($selection['withfiles']);
        }
        $this->set_data($data);
    }

    /** Apply pending data to the main form, or save an explicit standalone submission. */
    public function process_dynamic_submission(): array {
        global $DB;

        require_sesskey();
        $data = $this->get_data();
        if ($this->is_pending()) {
            $seed = $this->pending_state((array)$data);
            if ($data->operation === 'delete') {
                return ['key' => $seed['key'], 'removed' => true];
            }
            $item = item_editor::context((int)$data->itemid, (int)$data->courseid);
            $block = $seed['id'] ? block_exaport_get_item_content_block($item, $seed['id']) : null;
            $state = item_content_edit::normalise(item_content_edit::from_form($data, $seed['key']),
                $item, $block, false);
            if ($block && $state['type'] === 'file' && $state['files_filemanager'] > 0 &&
                    file_get_draft_area_info($state['files_filemanager'])['filecount'] === 0) {
                return ['key' => $seed['key'], 'removed' => true];
            }
            // Apply returns form state and presentation only. The main item Save owns persistence.
            return ['block' => $state, 'content' =>
                \block_exaport\output\pending_item_content_blocks::render_row($state, $item, (int)$data->courseid)];
        }
        if ($data->contenttype !== $this->get_content_type()) {
            throw new \invalid_parameter_exception('Content block type does not match the form');
        }
        $item = block_exaport_get_editable_content_item((int)$data->itemid, (int)$data->courseid);
        $block = !empty($data->blockid) ? block_exaport_get_item_content_block($item, (int)$data->blockid) : null;
        if ($block && $block->type !== $data->contenttype) {
            throw new \invalid_parameter_exception('Content block type does not match the form');
        }
        if (($data->operation ?? '') === 'delete' && !$block) {
            throw new \invalid_parameter_exception('A content block is required for deletion');
        }
        $transaction = $DB->start_delegated_transaction();
        try {
            // Standalone pages retain their explicit Save behavior, using the same persistence
            // helper as the complete item form rather than maintaining a second implementation.
            item_editor::lock_item((int)$item->id);
            $item = block_exaport_get_editable_content_item((int)$data->itemid, (int)$data->courseid);
            $block = !empty($data->blockid) ? block_exaport_get_item_content_block($item, (int)$data->blockid) : null;
            item_editor::guard_files($item, $block ? [$block] : []);
            if ($data->operation === 'delete') {
                block_exaport_delete_item_content_block($item, $block);
            } else {
                $state = item_content_edit::normalise(item_content_edit::from_form($data), $item, $block, true);
                item_content_edit::save($item, $state, $block, true);
            }
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
        }
        return ['content' => block_exaport_render_item_content_blocks((int)$data->courseid, $item)];
    }

    /** A pending modal belongs to a main form; ordinary standalone forms save explicitly. */
    private function is_pending(): bool {
        return (bool)$this->optional_param('pending', 0, PARAM_BOOL) ||
            $this->optional_param('pendingblock', '', PARAM_RAW) !== '';
    }

    /** Resolve ownership afresh, including when validation is invoked after construction. */
    private function item_context(): ?\stdClass {
        $itemid = $this->optional_param('itemid', 0, PARAM_INT);
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        return $this->is_pending() ? item_editor::context($itemid, $courseid)
            : block_exaport_get_editable_content_item($itemid, $courseid);
    }

    /** Match submitted block identifiers against its current authorized parent and content type. */
    private function pending_state(?array $data = null): array {
        $read = function(string $name, $default, string $type) use ($data) {
            return $data !== null && array_key_exists($name, $data)
                ? $data[$name] : $this->optional_param($name, $default, $type);
        };
        $json = $read('pendingblock', '', PARAM_RAW);
        if (!is_string($json)) {
            throw new \moodle_exception('itemeditorinvalid', 'block_exaport');
        }
        $state = item_editor::pending_seed(
            $json,
            (int)$read('itemid', 0, PARAM_INT), (int)$read('courseid', 0, PARAM_INT)
        );
        if ($state['type'] !== $this->get_content_type() ||
                $state['type'] !== $read('contenttype', '', PARAM_ALPHA) ||
                $state['id'] !== (int)$read('blockid', 0, PARAM_INT)) {
            throw new \invalid_parameter_exception('Content block does not match the pending editor');
        }
        return $state;
    }

    /** Return and validate the requested content type. */
    private function get_content_type(): string {
        $type = $this->_customdata['contenttype'] ?? $this->optional_param('contenttype', '', PARAM_ALPHA);
        if (!in_array($type, block_exaport_item_content_types(), true)) {
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
