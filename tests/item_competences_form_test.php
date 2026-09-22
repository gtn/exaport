<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/item_competence_helpers.php');

/** Structural tests for the competence dynamic form and its client lifecycle. */
final class item_competences_form_test extends \advanced_testcase {

    public function test_dynamic_form_helpers_load_plugin_domain_functions(): void {
        $this->assertTrue(function_exists('block_exaport_check_competence_interaction'));
        $this->assertTrue(function_exists('block_exaport_get_editable_item'));
        $this->assertTrue(function_exists('block_exaport_render_item_competence_summary'));
    }

    public function test_form_uses_moodle_dynamic_form_contract(): void {
        $this->assertTrue(is_subclass_of(
            \block_exaport\form\item_competences::class,
            \core_form\dynamic_form::class
        ));
        foreach (['definition', 'get_context_for_dynamic_submission', 'check_access_for_dynamic_submission',
            'get_page_url_for_dynamic_submission', 'set_data_for_dynamic_submission',
            'process_dynamic_submission', 'validation'] as $method) {
            $this->assertTrue(method_exists(\block_exaport\form\item_competences::class, $method), $method);
        }
    }

    public function test_client_uses_modal_form_without_legacy_ajax_lifecycle(): void {
        $source = file_get_contents(__DIR__ . '/../amd/src/item_competences.js');
        $this->assertStringContainsString("from 'core_form/modalform'", $source);
        $this->assertStringContainsString('FORM_SUBMITTED', $source);
        $this->assertStringNotContainsString('jquery', strtolower($source));
        $this->assertStringNotContainsString('$.ajax', $source);
        $this->assertStringNotContainsString('ModalSaveCancel', $source);
    }

    public function test_item_form_uses_standard_form_layout_for_competence_summary(): void {
        $source = file_get_contents(__DIR__ . '/../lib/item_edit_form.php');

        $this->assertMatchesRegularExpression(
            "/addElement\\(\\s*'static',\\s*'competencesummary',\\s*get_string\\('selectcomps'/s",
            $source
        );
    }

    public function test_shared_item_reuses_competence_summary_instead_of_legacy_table(): void {
        $source = file_get_contents(__DIR__ . '/../shared_item.php');

        $this->assertStringContainsString('block_exaport_render_item_competence_summary($item)', $source);
        $this->assertStringNotContainsString('block_exaport_build_comp_table', $source);
    }

    public function test_summary_export_contains_only_selected_nodes(): void {
        if (!class_exists(\block_exacomp\descriptor::class)) {
            $this->markTestSkipped('Exacomp is required for competence tree rendering.');
        }
        $selected = $this->create_descriptor(4, 'Selected');
        $notselected = $this->create_descriptor(8, 'Not selected');
        $renderable = new \block_exaport\output\item_competences(
            (object)['id' => 7, 'compids_array' => [4]],
            true
        );
        $summary = $renderable->export_summary_for_template([$selected, $notselected]);

        $this->assertSame(['selectednodes', 'hasselected'], array_keys($summary));
        $this->assertTrue($summary['hasselected']);
        $this->assertCount(1, $summary['selectednodes']);
        $this->assertSame('Selected', $summary['selectednodes'][0]['title']);
    }

    private function create_descriptor(int $id, string $title): \block_exacomp\descriptor {
        return new class($id, $title) extends \block_exacomp\descriptor {
            /** @var int */
            public $id;

            /** @var string */
            public $title;

            /** Set test descriptor fields. */
            public function __construct(int $id, string $title) {
                $this->id = $id;
                $this->title = $title;
            }

            /** @return array */
            public function get_subs(): array {
                return [];
            }
        };
    }

}
