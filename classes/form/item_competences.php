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
    private $tree = null;

    /**
     * Define the dynamic form.
     */
    protected function definition(): void {
        global $PAGE;

        $mform = $this->_form;
        $item = $this->load_item();
        $selectedids = $this->get_selectedids_for_display();
        $renderable = new \block_exaport\output\item_competences(
            $item,
            true,
            $selectedids,
            $this->get_checkbox_id_prefix(),
            $this->get_available_tree()
        );
        $renderer = $PAGE->get_renderer('block_exaport');

        $mform->addElement('hidden', 'courseid', $this->get_form_param('courseid'));
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'itemid', $this->get_form_param('itemid'));
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'competenceids', implode(',', $selectedids));
        $mform->setType('competenceids', PARAM_RAW_TRIMMED);
        $mform->addElement('html', $renderer->render_from_template(
            'block_exaport/item_competence_picker',
            $renderable->export_picker_for_template($renderer)
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

        if (array_diff($competenceids, $this->get_available_descriptorids())) {
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
        $this->load_item();
    }

    /**
     * Return the canonical page URL used by the dynamic form.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/blocks/exaport/item.php', [
            'courseid' => $this->get_form_param('courseid'),
            'id' => $this->get_form_param('itemid'),
            'itemid' => $this->get_form_param('itemid'),
            'action' => 'edit',
        ]);
    }

    /**
     * Set initial form data.
     */
    public function set_data_for_dynamic_submission(): void {
        $item = block_exaport_populate_item_competenceids($this->load_item());

        $this->set_data((object)[
            'courseid' => $this->get_form_param('courseid'),
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
        $data = $this->get_data();
        if ($data === null) {
            return [];
        }

        return block_exaport_process_item_competence_submission(
            (int)$data->courseid,
            (int)$data->itemid,
            $data->competenceids ?? ''
        );
    }

    /**
     * Load and cache the editable item after applying all workflow access checks.
     *
     * @return \stdClass
     */
    private function load_item(): \stdClass {
        if ($this->item === null) {
            $this->item = block_exaport_require_competence_item_access(
                $this->get_form_param('itemid'),
                $this->get_form_param('courseid')
            );
            $this->item = block_exaport_populate_item_competenceids($this->item);
        }

        return $this->item;
    }

    /**
     * Return the competence ids that should be reflected in the rendered picker.
     *
     * @return int[]
     */
    private function get_selectedids_for_display(): array {
        if (!$this->has_submitted_competenceids()) {
            return array_map('intval', $this->load_item()->compids_array ?? []);
        }

        try {
            return block_exaport_parse_competenceids($this->_ajaxformdata['competenceids']);
        } catch (invalid_parameter_exception $exception) {
            return [];
        }
    }

    /**
     * Whether the current request already submitted a custom selection.
     *
     * @return bool
     */
    private function has_submitted_competenceids(): bool {
        return is_array($this->_ajaxformdata) && array_key_exists('competenceids', $this->_ajaxformdata);
    }

    /**
     * Load and cache the available competence tree.
     *
     * @return array
     */
    private function get_available_tree(): array {
        global $USER;

        if ($this->tree === null) {
            $this->tree = block_exaport_get_available_competence_tree($USER->id);
        }

        return $this->tree;
    }

    /**
     * Return the available descriptor ids for the current user.
     *
     * @return int[]
     */
    private function get_available_descriptorids(): array {
        return block_exaport_competence_tree_descriptorids($this->get_available_tree());
    }

    /**
     * Build a per-item checkbox id prefix for the custom tree markup.
     *
     * @return string
     */
    private function get_checkbox_id_prefix(): string {
        return 'exaport-competence-' . $this->get_form_param('itemid') . '-';
    }

    /**
     * Read a numeric form argument from AJAX payload data when present.
     *
     * @param string $name Parameter name.
     * @return int
     */
    private function get_form_param(string $name): int {
        if (is_array($this->_ajaxformdata) && array_key_exists($name, $this->_ajaxformdata)) {
            return clean_param($this->_ajaxformdata[$name], PARAM_INT);
        }

        return $this->optional_param($name, 0, PARAM_INT);
    }
}
