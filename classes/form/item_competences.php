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
        // One registered field bridges the custom checkboxes into the dynamic-form submission.
        $mform->addElement('hidden', 'competenceids', '');
        $mform->setType('competenceids', PARAM_RAW_TRIMMED);

        $item = (object)['id' => $itemid, 'compids_array' => $this->get_selected_ids($itemid)];
        $renderable = new \block_exaport\output\item_competences($item, true);
        $data = $renderable->export_for_template($OUTPUT);
        $mform->addElement('html', $OUTPUT->render_from_template(
            'block_exaport/item_competence_picker', $data['picker']));
    }

    /** Validate the submitted sequence against the current user's Exacomp tree. */
    public function validation($data, $files): array {
        global $USER;

        $errors = parent::validation($data, $files);
        try {
            $ids = block_exaport_parse_competenceids($data['competenceids'] ?? '');
            $availableids = block_exaport_competence_tree_descriptorids(
                \block_exacomp\api::get_comp_tree_for_exaport($USER->id));
            block_exaport_validate_competenceids($ids, $availableids);
        } catch (\invalid_parameter_exception $exception) {
            $errors['competenceids'] = get_string('invaliddata', 'error');
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
        block_exaport_get_editable_competence_item(
            $this->optional_param('itemid', 0, PARAM_INT), $courseid);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/blocks/exaport/item.php', [
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'id' => $this->optional_param('itemid', 0, PARAM_INT),
            'action' => 'edit',
        ]);
    }

    /** Populate the serialized selection from persisted records. */
    public function set_data_for_dynamic_submission(): void {
        $itemid = $this->optional_param('itemid', 0, PARAM_INT);
        $this->set_data((object)[
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'itemid' => $itemid,
            'competenceids' => implode(',', $this->get_selected_ids($itemid)),
        ]);
    }

    /** Persist the complete selection and return the server-rendered refreshed summary. */
    public function process_dynamic_submission(): array {
        global $OUTPUT, $USER;

        $data = $this->get_data();
        $item = block_exaport_get_editable_competence_item((int)$data->itemid, (int)$data->courseid);
        $ids = block_exaport_parse_competenceids($data->competenceids ?? '');
        $tree = \block_exacomp\api::get_comp_tree_for_exaport($USER->id);
        $availableids = block_exaport_competence_tree_descriptorids($tree);
        $ids = block_exaport_validate_competenceids($ids, $availableids);
        block_exaport_sync_item_competences($item, $ids);
        $item->compids_array = $this->get_selected_ids((int)$item->id);

        $renderable = new \block_exaport\output\item_competences($item, true);
        return ['content' => $OUTPUT->render_from_template(
            'block_exaport/item_competence_summary',
            $renderable->export_summary_for_template($tree)
        )];
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
