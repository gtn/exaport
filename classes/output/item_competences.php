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

    /** @var int[] */
    private $selectedids;

    /** @var array|null */
    private $tree;

    /**
     * @param \stdClass $item Portfolio item.
     * @param bool $editable Whether the current user may change the selection.
     * @param int[]|null $selectedids Optional selected ids override.
     * @param array|null $tree Optional competence tree override.
     */
    public function __construct(
        \stdClass $item,
        bool $editable,
        ?array $selectedids = null,
        ?array $tree = null
    ) {
        $this->item = $item;
        $this->editable = $editable;
        $this->selectedids = array_map('intval', $selectedids ?? ($this->item->compids_array ?? []));
        $this->tree = $tree;
    }

    /**
     * Export the competence section for the item edit form.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'intro' => get_string('selectcomps', 'block_exaport'),
            'addlabel' => get_string('addcompetences', 'block_exaport'),
            'addicon' => $output->pix_icon('t/add', '', 'moodle', ['aria-hidden' => 'true']),
            'itemid' => (int)$this->item->id,
            'summary' => $this->export_summary_for_template(),
            'editable' => $this->editable,
        ];
    }

    /**
     * Export the authoritative summary node tree.
     *
     * @return array
     */
    public function export_summary_for_template(): array {
        $nodes = $this->export_nodes($this->get_tree(), $this->selectedids);
        $selectednodes = $this->filter_selected_nodes($nodes);

        return [
            'itemid' => (int)$this->item->id,
            'selectednodes' => $selectednodes,
            'hasselected' => !empty($selectednodes),
        ];
    }

    /**
     * Export the editable picker tree for the dynamic form.
     *
     * @return array
     */
    public function export_picker_for_template(): array {
        return [
            'itemid' => (int)$this->item->id,
            'nodes' => $this->export_nodes($this->get_tree(), $this->selectedids, true),
            'expandlabel' => get_string('expandcomps', 'block_exaport'),
            'collapselabel' => get_string('collapsecomps', 'block_exaport'),
        ];
    }

    /**
     * Convert Exacomp tree objects to Mustache data.
     *
     * @param array $items Exacomp tree nodes.
     * @param int[] $selectedids Selected descriptor ids.
     * @param bool $picker Whether to include picker checkbox metadata.
     * @return array
     */
    private function export_nodes(array $items, array $selectedids, bool $picker = false): array {
        $nodes = [];
        foreach ($items as $item) {
            $isdescriptor = $item instanceof \block_exacomp\descriptor;
            $children = $this->export_nodes($item->get_subs() ?: [], $selectedids, $picker);
            $id = $isdescriptor ? (int)$item->id : 0;
            $nodes[] = [
                'id' => $id,
                'title' => $item->title,
                'isdescriptor' => $isdescriptor,
                'isgroup' => !$isdescriptor,
                'checked' => $isdescriptor && in_array($id, $selectedids, true),
                'inputid' => $picker && $isdescriptor ? 'exaport-competence-' . $this->item->id . '-' . $id : '',
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

    /**
     * Load the current competence tree once.
     *
     * @return array
     */
    private function get_tree(): array {
        global $USER;

        if ($this->tree === null) {
            $this->tree = \block_exacomp\api::get_comp_tree_for_exaport($USER->id);
        }

        return $this->tree;
    }
}
