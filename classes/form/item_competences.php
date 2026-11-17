<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport\form;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib/item_competence_helpers.php');

use context;
use context_system;
use core_form\dynamic_form;
use moodle_url;
use block_exaport\local\item_editor;

/** Moodle dynamic form for replacing an item's competence selection. */
class item_competences extends dynamic_form {

    /** Define the serialized value and the custom competence tree. */
    protected function definition(): void {
        global $OUTPUT, $USER;

        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $itemid = $this->optional_param('itemid', 0, PARAM_INT);
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'itemid', $itemid);
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'pending', $this->is_pending() ? 1 : 0);
        $mform->setType('pending', PARAM_BOOL);
        if ($this->is_pending()) {
            $mform->addElement('hidden', 'selectedids', $this->optional_param('selectedids', '', PARAM_RAW_TRIMMED));
            $mform->setType('selectedids', PARAM_RAW_TRIMMED);
        }
        // One registered field bridges the custom checkboxes into the dynamic-form submission.
        $mform->addElement('hidden', 'competenceids', '');
        $mform->setType('competenceids', PARAM_RAW_TRIMMED);

        $item = (object)['id' => $itemid, 'compids_array' => $this->selection_for_picker($itemid)];
        if ($this->is_pending()) {
            // Former assignments remain available to reselect until the main form saves.
            $item->availablecompids_array = $this->get_selected_ids($itemid);
        }
        $renderable = new \block_exaport\output\item_competences($item, true);
        $data = $renderable->export_for_template($OUTPUT);
        $mform->addElement('static', 'competencepicker', '', $OUTPUT->render_from_template(
            'block_exaport/item_competence_picker', $data['picker']));
    }

    /** Validate the sequence against the current tree and this item's existing assignments. */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            $item = $this->is_pending()
                ? item_editor::context((int)$data['itemid'], (int)$data['courseid'])
                : block_exaport_get_editable_competence_item((int)$data['itemid'], (int)$data['courseid']);
            $ids = block_exaport_parse_competenceids($data['competenceids'] ?? '');
            block_exaport_validate_item_competenceids($item, $ids);
        } catch (\invalid_parameter_exception | \block_exacomp_permission_exception $exception) {
            $errors['competenceids'] = get_string('invaliddata', 'error');
            $errors['competencepicker'] = $errors['competenceids'];
        }
        return $errors;
    }

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /** Apply all feature and item authorization checks for render and submit requests. */
    protected function check_access_for_dynamic_submission(): void {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        // The dynamic-form external service already requires an authenticated user. Avoid
        // changing PAGE course state from inside its AJAX request.
        require_capability('block/exaport:use', $this->get_context_for_dynamic_submission());
        if (!block_exaport_check_competence_interaction()) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        if ($this->is_pending()) {
            $itemid = $this->optional_param('itemid', 0, PARAM_INT);
            item_editor::context($itemid, $courseid);
            return;
        }
        block_exaport_get_editable_competence_item(
            $this->optional_param('itemid', 0, PARAM_INT), $courseid);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/blocks/exaport/item.php', [
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'id' => $this->optional_param('itemid', 0, PARAM_INT),
            'action' => $this->optional_param('itemid', 0, PARAM_INT) ? 'edit' : 'add',
        ]);
    }

    /** Populate the serialized selection from persisted records. */
    public function set_data_for_dynamic_submission(): void {
        $itemid = $this->optional_param('itemid', 0, PARAM_INT);
        $this->set_data((object)[
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'itemid' => $itemid,
            'competenceids' => implode(',', $this->selection_for_picker($itemid)),
            'selectedids' => $this->optional_param('selectedids', '', PARAM_RAW_TRIMMED),
        ]);
    }

    /** Apply the selection to its main form, or save an independent picker submission. */
    public function process_dynamic_submission(): array {
        global $DB, $OUTPUT, $USER;

        require_sesskey();
        $data = $this->get_data();
        $item = $this->is_pending()
            ? item_editor::context((int)$data->itemid, (int)$data->courseid)
            : block_exaport_get_editable_competence_item((int)$data->itemid, (int)$data->courseid);
        $ids = block_exaport_parse_competenceids($data->competenceids ?? '');
        $tree = \block_exacomp\api::get_comp_tree_for_exaport($USER->id);
        $ids = block_exaport_validate_item_competenceids($item, $ids, $tree);
        if (!$this->is_pending()) {
            $transaction = $DB->start_delegated_transaction();
            try {
                item_editor::lock_item((int)$item->id);
                $item = block_exaport_get_editable_competence_item((int)$data->itemid, (int)$data->courseid);
                $ids = block_exaport_validate_item_competenceids($item, $ids, $tree);
                block_exaport_sync_item_competences($item, $ids);
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
        }
        // An unsaved selection can use the existing renderable without a placeholder DB item.
        $item = $item ? clone $item : (object)['id' => 0];
        $item->compids_array = $ids;

        $renderable = new \block_exaport\output\item_competences($item, true);
        return ['competenceids' => $ids, 'content' => $OUTPUT->render_from_template(
            'block_exaport/item_competence_summary',
            $renderable->export_summary_for_template($tree)
        )];
    }

    /** Keep selection-bearing requests pending even if their explicit flag is lost. */
    private function is_pending(): bool {
        return (bool)$this->optional_param('pending', 0, PARAM_BOOL) ||
            $this->optional_param('selectedids', null, PARAM_RAW_TRIMMED) !== null;
    }

    /** Read accepted pending values on opening, and actual submitted values on validation errors. */
    private function selection_for_picker(int $itemid): array {
        if (!$this->is_pending()) {
            return $this->get_selected_ids($itemid);
        }
        // definition() runs before Moodle marks the form as submitted. The registered
        // field's presence distinguishes submitted values from initial launcher arguments.
        $submitted = $this->optional_param('competenceids', null, PARAM_RAW_TRIMMED);
        $raw = $submitted !== null ? $submitted : $this->optional_param('selectedids', '', PARAM_RAW_TRIMMED);
        try {
            return block_exaport_parse_competenceids($raw);
        } catch (\invalid_parameter_exception $exception) {
            // Keep the submitted field intact so validation reports the error without exposing
            // arbitrary descriptor labels in the custom picker.
            return [];
        }
    }

    /** @return int[] */
    private function get_selected_ids(int $itemid): array {
        global $DB;
        if (!$itemid || !defined('BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY')) {
            return [];
        }
        return block_exaport_normalize_competenceids($DB->get_fieldset_select(
            BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, 'compid', 'activityid = ? AND eportfolioitem = 1', [$itemid]));
    }
}
