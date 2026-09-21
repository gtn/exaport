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

/**
 * Lightweight group node used for competence tree rendering tests.
 */
final class item_competence_group_node {
    /** @var string */
    public $title;

    /** @var array */
    private $subs;

    /**
     * @param string $title Node title.
     * @param array $subs Child nodes.
     */
    public function __construct(string $title, array $subs = []) {
        $this->title = $title;
        $this->subs = $subs;
    }

    /**
     * @return array
     */
    public function get_subs(): array {
        return $this->subs;
    }
}

/**
 * Tests for the dynamic-form competence modal presentation.
 *
 * @package block_exaport
 */
final class item_competences_modal_test extends \advanced_testcase {

    public function test_dynamic_form_class_extends_moodle_dynamic_form(): void {
        $this->assertTrue(is_subclass_of(
            \block_exaport\form\item_competences::class,
            \core_form\dynamic_form::class
        ));
    }

    public function test_client_uses_modalform_without_manual_ajax(): void {
        $source = file_get_contents(__DIR__ . '/../amd/src/item_competences.js');

        $this->assertStringContainsString("from 'core_form/modalform'", $source);
        $this->assertStringContainsString('FORM_SUBMITTED', $source);
        $this->assertStringNotContainsString("from 'jquery'", $source);
        $this->assertStringNotContainsString('$.ajax', $source);
        $this->assertStringNotContainsString('ModalSaveCancel', $source);
    }

    public function test_picker_export_marks_existing_selected_ids(): void {
        $this->resetAfterTest(true);
        $this->require_exacomp_descriptor();

        $renderable = new \block_exaport\output\item_competences(
            (object)['id' => 7, 'compids_array' => [13, 11]],
            true,
            null,
            $this->create_tree()
        );
        $data = $renderable->export_picker_for_template();

        $this->assertSame([11, 13], $this->collect_checked_ids($data['nodes']));
        $this->assertSame('exaport-competence-7-11', $data['nodes'][0]['children'][0]['inputid']);
        $this->assertSame('exaport-competence-7-13', $data['nodes'][1]['inputid']);
    }

    public function test_rendered_summary_contains_only_selected_nodes(): void {
        global $OUTPUT;

        $this->resetAfterTest(true);
        $this->require_exacomp_descriptor();
        $item = (object)['id' => 9, 'compids_array' => [11, 13]];

        $renderable = new \block_exaport\output\item_competences($item, true, null, $this->create_tree());
        $html = $OUTPUT->render_from_template(
            'block_exaport/item_competence_summary',
            $renderable->export_summary_for_template()
        );

        $this->assertStringContainsString('data-region="competence-summary"', $html);
        $this->assertStringContainsString('Descriptor 11', $html);
        $this->assertStringContainsString('Descriptor 13', $html);
        $this->assertStringNotContainsString('Descriptor 12', $html);
    }

    /**
     * @return array
     */
    private function create_tree(): array {
        return [
            new item_competence_group_node('Group', [
                $this->create_descriptor_node(11, 'Descriptor 11'),
                $this->create_descriptor_node(12, 'Descriptor 12'),
            ]),
            $this->create_descriptor_node(13, 'Descriptor 13'),
        ];
    }

    /**
     * @param int $id
     * @param string $title
     * @return \block_exacomp\descriptor
     */
    private function create_descriptor_node(int $id, string $title): \block_exacomp\descriptor {
        return new class($id, $title) extends \block_exacomp\descriptor {
            /** @var int */
            public $id;

            /** @var string */
            public $title;

            /**
             * @param int $id
             * @param string $title
             */
            public function __construct(int $id, string $title) {
                $this->id = $id;
                $this->title = $title;
            }

            /**
             * @return array
             */
            public function get_subs(): array {
                return [];
            }
        };
    }

    /**
     * @param array $nodes
     * @return int[]
     */
    private function collect_checked_ids(array $nodes): array {
        $checked = [];
        foreach ($nodes as $node) {
            if (!empty($node['checked'])) {
                $checked[] = (int)$node['id'];
            }
            if (!empty($node['children'])) {
                $checked = array_merge($checked, $this->collect_checked_ids($node['children']));
            }
        }
        sort($checked, SORT_NUMERIC);
        return $checked;
    }

    private function require_exacomp_descriptor(): void {
        if (!class_exists(\block_exacomp\descriptor::class)) {
            $this->markTestSkipped('Exacomp descriptor class is required for competence tree rendering tests.');
        }
    }
}
