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
use invalid_parameter_exception;
use moodle_url;

/**
 * Moodle dynamic form for editing an item's competences.
 *
 * @package block_exaport
 */
class item_competences extends dynamic_form {

    /** @var \stdClass|null */
    private $item = null;

    /** @var array|null */
    private $competencetree = null;

    /**
     * Define the dynamic form.
     */
    protected function definition(): void {
        global $PAGE;

        $mform = $this->_form;
        $item = $this->get_item();
        $selectedids = $this->get_selectedids();
        $renderable = new \block_exaport\output\item_competences(
            $item,
            true,
            $selectedids,
            $this->get_competence_tree((int)$item->userid)
        );
        $renderer = $PAGE->get_renderer('block_exaport');

        $mform->addElement('hidden', 'courseid', $this->optional_param('courseid', 0, PARAM_INT));
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'itemid', $this->optional_param('itemid', 0, PARAM_INT));
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'competenceids', implode(',', $selectedids));
        $mform->setType('competenceids', PARAM_RAW_TRIMMED);
        $mform->addElement('html', $renderer->render_from_template(
            'block_exaport/item_competence_picker',
            $renderable->export_picker_for_template()
        ));
    }

    /**
     * Validate the submitted selection.
     *
     * @param array $data Submitted data.
     * @param array $files Uploaded files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        try {
            $competenceids = block_exaport_parse_competenceids($data['competenceids'] ?? '');
        } catch (invalid_parameter_exception $exception) {
            $errors['competenceids'] = get_string('invaliddata', 'error');
            return $errors;
        }

        if (array_diff($competenceids, block_exaport_competence_tree_descriptorids(
            $this->get_competence_tree((int)$this->get_item()->userid)
        ))) {
            $errors['competenceids'] = get_string('invaliddata', 'error');
        }

        return $errors;
    }

    /**
     * Return the context validated by Moodle's dynamic-form external service.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Check access before Moodle renders or processes the form.
     */
    protected function check_access_for_dynamic_submission(): void {
        $this->get_item();
    }

    /**
     * Return the canonical page URL used by the dynamic form.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/blocks/exaport/item.php', [
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'id' => $this->optional_param('itemid', 0, PARAM_INT),
            'action' => 'edit',
        ]);
    }

    /**
     * Set initial form data.
     */
    public function set_data_for_dynamic_submission(): void {
        $item = $this->get_item();
        $item->compids_array = block_exaport_get_item_competenceids($item);

        $this->set_data((object)[
            'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
            'itemid' => (int)$item->id,
            'competenceids' => implode(',', $item->compids_array),
        ]);
    }

    /**
     * Persist the submitted selection and return the refreshed summary HTML.
     *
     * @return array
     */
    public function process_dynamic_submission(): array {
        global $PAGE;

        $data = $this->get_data();
        if ($data === null) {
            return [];
        }

        require_sesskey();

        $item = block_exaport_require_competence_item_access((int)$data->itemid, (int)$data->courseid);
        $tree = $this->get_competence_tree((int)$item->userid, true);
        $competenceids = block_exaport_parse_competenceids($data->competenceids ?? '');
        $competenceids = block_exaport_validate_competenceids(
            $competenceids,
            block_exaport_competence_tree_descriptorids($tree)
        );

        block_exaport_sync_item_competences($item, $competenceids);
        $item->compids_array = block_exaport_get_item_competenceids($item);

        $renderer = $PAGE->get_renderer('block_exaport');
        $renderable = new \block_exaport\output\item_competences($item, true);

        return [
            'content' => $renderer->render_from_template(
                'block_exaport/item_competence_summary',
                $renderable->export_summary_for_template()
            ),
            'itemid' => (int)$item->id,
        ];
    }

    /**
     * Load and cache the editable item after applying all workflow access checks.
     *
     * @return \stdClass
     */
    private function get_item(): \stdClass {
        if ($this->item === null) {
            $this->item = block_exaport_require_competence_item_access(
                $this->optional_param('itemid', 0, PARAM_INT),
                $this->optional_param('courseid', 0, PARAM_INT)
            );
        }

        return $this->item;
    }

    /**
     * Return the competence ids that should be reflected in the rendered picker.
     *
     * @return int[]
     */
    private function get_selectedids(): array {
        $submittedids = $this->optional_param('competenceids', null, PARAM_RAW_TRIMMED);
        if ($submittedids === null) {
            return block_exaport_get_item_competenceids($this->get_item());
        }

        try {
            return block_exaport_parse_competenceids($submittedids);
        } catch (invalid_parameter_exception $exception) {
            return block_exaport_get_item_competenceids($this->get_item());
        }
    }

    /**
     * Load the current user's available competence tree.
     *
     * @param int $userid User id whose available tree should be loaded.
     * @param bool $refresh Whether to force a fresh tree load.
     * @return array
     */
    private function get_competence_tree(int $userid, bool $refresh = false): array {
        if ($refresh || $this->competencetree === null) {
            $this->competencetree = \block_exacomp\api::get_comp_tree_for_exaport($userid);
        }

        return $this->competencetree;
    }
}
