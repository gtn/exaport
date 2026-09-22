<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use renderable;
use templatable;

/**
 * Competence summary and picker for an existing portfolio item.
 */
class item_competences implements renderable, templatable {

    /** @var \stdClass */
    private $item;

    /** @var bool */
    private $editable;

    /**
     * @param \stdClass $item Portfolio item.
     * @param bool $editable Whether the current user may change the selection.
     */
    public function __construct(\stdClass $item, bool $editable) {
        $this->item = $item;
        $this->editable = $editable;
    }

    /**
     * Export the competence tree for the section and modal templates.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $selectedids = array_map('intval', $this->item->compids_array ?? []);
        $tree = \block_exacomp\api::get_comp_tree_for_exaport($USER->id);
        $nodes = $this->export_nodes($tree, $selectedids);
        $summary = $this->export_summary_from_nodes($nodes);

        return [
            'addlabel' => get_string('addcompetences', 'block_exaport'),
            'addicon' => $output->pix_icon('t/add', '', 'moodle', ['aria-hidden' => 'true']),
            'selectednodes' => $summary['selectednodes'],
            'hasselected' => $summary['hasselected'],
            'picker' => [
                'nodes' => $nodes,
                'itemid' => (int)$this->item->id,
                'expandlabel' => get_string('expandcomps', 'block_exaport'),
                'collapselabel' => get_string('collapsecomps', 'block_exaport'),
            ],
            'editable' => $this->editable,
        ];
    }

    /**
     * Export only the selected competence summary.
     *
     * @param array|null $tree Competence tree when already available.
     * @return array
     */
    public function export_summary_for_template(?array $tree = null): array {
        global $USER;

        if ($tree === null) {
            $tree = \block_exacomp\api::get_comp_tree_for_exaport($USER->id);
        }
        $selectedids = array_map('intval', $this->item->compids_array ?? []);
        $nodes = $this->export_nodes($tree, $selectedids);

        return $this->export_summary_from_nodes($nodes);
    }

    /** Return summary template data from an already prepared node tree. */
    private function export_summary_from_nodes(array $nodes): array {
        $selectednodes = $this->filter_selected_nodes($nodes);

        return [
            'selectednodes' => $selectednodes,
            'hasselected' => !empty($selectednodes),
        ];
    }

    /**
     * Convert Exacomp tree objects to Mustache data.
     *
     * @param array $items Exacomp tree nodes.
     * @param int[] $selectedids Selected descriptor ids.
     * @return array
     */
    private function export_nodes(array $items, array $selectedids): array {
        $nodes = [];
        foreach ($items as $item) {
            $isdescriptor = $item instanceof \block_exacomp\descriptor;
            $children = $this->export_nodes($item->get_subs() ?: [], $selectedids);
            $nodes[] = [
                'id' => $isdescriptor ? (int)$item->id : 0,
                'title' => $item->title,
                'isdescriptor' => $isdescriptor,
                'isgroup' => !$isdescriptor,
                'checked' => $isdescriptor && in_array((int)$item->id, $selectedids, true),
                'children' => $children,
                'haschildren' => !empty($children),
            ];
        }
        return $nodes;
    }

    /**
     * Keep selected descriptors and the branches containing them.
     *
     * @param array $nodes Exported tree nodes.
     * @return array
     */
    private function filter_selected_nodes(array $nodes): array {
        $selected = [];
        foreach ($nodes as $node) {
            $children = $this->filter_selected_nodes($node['children']);
            if ($node['checked'] || $children) {
                $node['children'] = $children;
                $node['haschildren'] = !empty($children);
                $node['summary'] = true;
                $selected[] = $node;
            }
        }
        return $selected;
    }
}
