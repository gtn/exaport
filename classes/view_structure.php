<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
// Moodle is free software: you can redistribute it and/or modify it under the
// terms of the GNU General Public License, version 3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/** Generic, versioned view blueprints and independent student instances. */
class view_structure {
    const TYPES = ['heading', 'field', 'artefact', 'section'];
    const LAYOUTS = [1 => 1, 2 => 2, 3 => 5, 4 => 8, 5 => 10];
    const MAX_NODES = 200;
    const MAX_DEPTH = 5;

    /** Stable logical keys are independent of database block IDs. */
    public static function key(): string {
        return bin2hex(random_bytes(12));
    }

    /** Validate imported definitions too; definitions never contain learner responses or item IDs. */
    public static function definition($input): array {
        if (is_string($input)) {
            $input = $input === '' ? [] : json_decode($input, true);
        }
        if (!is_array($input) || (isset($input['version']) && $input['version'] !== 1)) {
            throw new \invalid_parameter_exception('Unsupported view structure');
        }
        $seen = [];
        $count = 0;
        $columns = max(1, min(5, (int)($input['columns'] ?? 1)));
        $normalize = function(array $nodes, int $depth) use (&$normalize, &$seen, &$count, $columns): array {
            if ($depth > self::MAX_DEPTH) {
                throw new \invalid_parameter_exception('View structure is too deeply nested');
            }
            $result = [];
            foreach ($nodes as $node) {
                if (!is_array($node) || !in_array($node['type'] ?? '', self::TYPES, true) || ++$count > self::MAX_NODES) {
                    throw new \invalid_parameter_exception('Invalid view structure element');
                }
                $key = $node['key'] ?? self::key();
                if (!is_string($key) || !preg_match('/^[a-f0-9]{24}$/D', $key) || isset($seen[$key])) {
                    throw new \invalid_parameter_exception('Invalid or duplicate structure key');
                }
                $seen[$key] = true;
                $clean = [
                    'key' => $key, 'type' => $node['type'],
                    'label' => \core_text::substr(clean_param($node['label'] ?? '', PARAM_TEXT), 0, 255),
                    'instructions' => clean_param($node['instructions'] ?? '', PARAM_TEXT),
                    'column' => max(1, min($columns, (int)($node['column'] ?? 1))),
                ];
                if ($clean['type'] === 'field') {
                    $clean['multiline'] = !empty($node['multiline']);
                    $clean['default'] = clean_param($node['default'] ?? '', PARAM_TEXT);
                } else if ($clean['type'] === 'artefact') {
                    $clean['accept'] = in_array($node['accept'] ?? '', ['image', 'video', 'audio'], true) ? $node['accept'] : '*';
                    $clean['reflection'] = clean_param($node['reflection'] ?? '', PARAM_TEXT);
                } else if ($clean['type'] === 'section') {
                    $clean['repeatable'] = !empty($node['repeatable']);
                    $clean['minimum'] = $clean['repeatable'] ? max(1, min(10, (int)($node['minimum'] ?? 1))) : 1;
                    $clean['children'] = $normalize($node['children'] ?? [], $depth + 1);
                }
                $result[] = $clean;
            }
            return $result;
        };
        $definition = ['version' => 1, 'columns' => $columns, 'nodes' => $normalize($input['nodes'] ?? [], 0)];
        // Bound the expanded size as well as the definition (nested repeats multiply).
        $expanded = function(array $nodes) use (&$expanded): int {
            $total = 0;
            foreach ($nodes as $node) {
                $total += ($node['minimum'] ?? 1) * (1 + $expanded($node['children'] ?? []));
                if ($total > self::MAX_NODES) {
                    throw new \invalid_parameter_exception('View structure has too many initial elements');
                }
            }
            return $total;
        };
        $expanded($definition['nodes']);
        return $definition;
    }

    /** JSON always contains data, never pre-rendered HTML. */
    public static function encode(array $definition): string {
        return json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Create normal view-block records, with separate configuration and responses. */
    public static function instantiate(int $viewid, array $definition): void {
        $definition = self::definition($definition);
        self::insert_nodes($viewid, $definition['nodes']);
    }

    private static function insert_nodes(int $viewid, array $nodes, string $parent = '', ?int $column = null, bool $single = false): void {
        global $DB;
        $sort = (int)$DB->get_field_sql('SELECT MAX(positiony) FROM {block_exaportviewblock} WHERE viewid = ?', [$viewid]);
        foreach ($nodes as $node) {
            $nodecolumn = $column ?? $node['column'];
            for ($i = 0; $i < ($single ? 1 : ($node['minimum'] ?? 1)); $i++) {
                $key = self::key();
                $DB->insert_record('block_exaportviewblock', (object)[
                    'viewid' => $viewid, 'type' => 'template_' . $node['type'],
                    'templatekey' => $key, 'parentkey' => $parent,
                    'templateconfig' => self::encode($node),
                    'positionx' => $nodecolumn, 'positiony' => ++$sort,
                    'block_title' => $node['label'], 'text' => $node['default'] ?? '', 'itemid' => 0,
                ]);
                if ($node['type'] === 'section') {
                    self::insert_nodes($viewid, $node['children'], $key, $nodecolumn);
                }
            }
        }
    }

    /** Stored configuration comes only from the trusted template snapshot. */
    public static function blocks(int $viewid): array {
        global $DB;
        $blocks = $DB->get_records_select('block_exaportviewblock',
            'viewid = ? AND templatekey IS NOT NULL', [$viewid], 'positionx, positiony, id');
        foreach ($blocks as $block) {
            $block->config = json_decode($block->templateconfig, true, 32, JSON_THROW_ON_ERROR);
        }
        return $blocks;
    }

    /** Parent-first traversal is shared by the learner form and the renderer. */
    public static function children(array $blocks, string $parent = ''): array {
        return array_values(array_filter($blocks, static function($block) use ($parent) {
            return ($block->parentkey ?? '') === $parent;
        }));
    }

    /** Add another blank occurrence; never duplicate another occurrence's answers or files. */
    public static function repeat(int $viewid, string $key): void {
        $blocks = self::blocks($viewid);
        foreach ($blocks as $block) {
            if ($block->templatekey === $key && !empty($block->config['repeatable'])) {
                if (count($blocks) + self::expanded_size($block->config) > self::MAX_NODES) {
                    throw new \invalid_parameter_exception('View structure has too many elements');
                }
                $prototype = $block->config;
                self::insert_nodes($viewid, [$prototype], $block->parentkey, (int)$block->positionx, true);
                return;
            }
        }
        throw new \invalid_parameter_exception('Section is not repeatable');
    }

    private static function expanded_size(array $node): int {
        $size = 1;
        foreach ($node['children'] ?? [] as $child) {
            $size += ($child['minimum'] ?? 1) * self::expanded_size($child);
        }
        return $size;
    }

    /** Remove an optional occurrence; its independent portfolio artefacts remain owned by the learner. */
    public static function remove(int $viewid, string $key): void {
        global $DB;
        $blocks = self::blocks($viewid);
        foreach ($blocks as $block) {
            if ($block->templatekey !== $key || empty($block->config['repeatable'])) {
                continue;
            }
            $siblings = array_filter(self::children($blocks, $block->parentkey), static function($sibling) use ($block) {
                return $sibling->config['key'] === $block->config['key'];
            });
            if (count($siblings) <= $block->config['minimum']) {
                throw new \invalid_parameter_exception('Minimum number of sections must be retained');
            }
            $remove = function($current) use (&$remove, $blocks, $DB, $viewid) {
                foreach (self::children($blocks, $current->templatekey) as $child) {
                    $remove($child);
                }
                $DB->delete_records('block_exaportviewblock', ['viewid' => $viewid, 'id' => $current->id]);
            };
            $remove($block);
            return;
        }
        throw new \invalid_parameter_exception('Section cannot be removed');
    }

    /** Read-only HTML and PDF use the same labels, responses and artefact projection. */
    public static function render(\stdClass $view, string $access = '', bool $pdf = false, ?int $column = null): string {
        global $DB, $OUTPUT;
        $blocks = self::blocks((int)$view->id);
        $columncount = [1 => 1, 2 => 2, 3 => 2, 4 => 2, 5 => 3, 6 => 3, 7 => 3, 8 => 4, 9 => 4, 10 => 5];
        $visiblecolumns = $columncount[$view->layout] ?? 1;
        $render = function(string $parent, int $depth = 0) use (&$render, $blocks, $view, $access, $pdf, $column,
                $visiblecolumns, $DB, $OUTPUT): string {
            $html = '';
            foreach (self::children($blocks, $parent) as $block) {
                if ($parent === '' && $column !== null && min($visiblecolumns, (int)$block->positionx) !== $column) {
                    continue;
                }
                $config = $block->config;
                $type = $config['type'];
                $html .= \html_writer::start_div('exaport-template-element exaport-template-' . $type);
                $html .= \html_writer::tag($depth ? 'h4' : 'h3', s($config['label']));
                if ($config['instructions'] !== '') {
                    $html .= \html_writer::div(nl2br(s($config['instructions'])), 'exaport-template-instructions');
                }
                if ($type === 'section') {
                    $html .= $render($block->templatekey, $depth + 1);
                } else if ($type === 'field') {
                    $html .= \html_writer::div(nl2br(s($block->text ?? '')), 'exaport-template-answer');
                } else if ($type === 'artefact') {
                    // Only learner-owned items can fill a slot. A deleted item leaves a usable empty slot.
                    $item = $block->itemid ? $DB->get_record('block_exaportitem',
                        ['id' => $block->itemid, 'userid' => $view->userid]) : false;
                    if ($item) {
                        $renderable = new \block_exaport\output\item_content_blocks(
                            \block_exaport_get_item_content_blocks((int)$item->id), null,
                            (int)$view->userid, false, false, $access ? 'view/' . $access : null);
                        $data = $renderable->export_for_template($OUTPUT);
                        $html .= $pdf ? $renderable->render_for_pdf($OUTPUT, $data) :
                            $OUTPUT->render_from_template('block_exaport/item_content_blocks', $data);
                    } else {
                        $html .= \html_writer::div(get_string('template_slot_empty', 'block_exaport'), 'text-muted');
                    }
                }
                $html .= \html_writer::end_div();
            }
            return $html;
        };
        return $render('');
    }
}
