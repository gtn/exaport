<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
// Moodle is free software under the GNU General Public License, version 3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/** Server-side structure editing, also used for non-JavaScript clients. */
class view_template_editor {
    /** Examples contain configuration only and are optional starting points for any course. */
    public static function examples(): array {
        return json_decode(file_get_contents(__DIR__ . '/../presets/view_structures.json'), true, 32, JSON_THROW_ON_ERROR);
    }

    /** Apply field edits and one structural operation to a trusted definition. */
    public static function update(array $definition, array $values, string $operation, array $addtypes = []): array {
        $definition['columns'] = (int)($values['columns'] ?? $definition['columns']);
        $merge = function(array &$nodes) use (&$merge, $values) {
            foreach ($nodes as &$node) {
                $posted = $values['nodes'][$node['key']] ?? [];
                foreach (['label', 'instructions', 'column', 'multiline', 'default', 'accept', 'reflection',
                    'repeatable', 'minimum'] as $field) {
                    if (array_key_exists($field, $posted)) {
                        $node[$field] = $posted[$field];
                    }
                }
                if ($node['type'] === 'section') {
                    $merge($node['children']);
                }
            }
        };
        $merge($definition['nodes']);
        [$action, $key] = array_pad(explode(':', $operation, 2), 2, '');
        if ($action === 'example') {
            $example = self::examples()[$key] ?? null;
            if (!$example) {
                throw new \invalid_parameter_exception('Unknown structure example');
            }
            $definition['nodes'] = array_merge($definition['nodes'], view_structure::definition($example)['nodes']);
            return view_structure::definition($definition);
        }
        $done = $action === 'save';
        $rekey = function(array $node) use (&$rekey): array {
            $node['key'] = view_structure::key();
            if (isset($node['children'])) {
                $node['children'] = array_map($rekey, $node['children']);
            }
            return $node;
        };
        $change = function(array &$nodes, string $parent) use (&$change, &$done, $action, $key, $addtypes, $rekey) {
            if ($action === 'add' && $parent === $key) {
                $type = $addtypes[$key ?: 'root'] ?? 'field';
                if (!in_array($type, view_structure::TYPES, true)) {
                    throw new \invalid_parameter_exception('Invalid element type');
                }
                $nodes[] = ['key' => view_structure::key(), 'type' => $type,
                    'label' => get_string('template_type_' . $type, 'block_exaport'), 'children' => [],
                    'multiline' => true];
                $done = true;
                return;
            }
            foreach ($nodes as $i => &$node) {
                if ($node['key'] === $key) {
                    if ($action === 'remove') {
                        array_splice($nodes, $i, 1);
                    } else if ($action === 'duplicate') {
                        array_splice($nodes, $i + 1, 0, [$rekey($node)]);
                    } else if ($action === 'up' && $i > 0) {
                        [$nodes[$i - 1], $nodes[$i]] = [$nodes[$i], $nodes[$i - 1]];
                    } else if ($action === 'down' && $i < count($nodes) - 1) {
                        [$nodes[$i + 1], $nodes[$i]] = [$nodes[$i], $nodes[$i + 1]];
                    }
                    $done = true;
                    return;
                }
                if ($node['type'] === 'section') {
                    $change($node['children'], $node['key']);
                    if ($done) {
                        return;
                    }
                }
            }
        };
        $change($definition['nodes'], '');
        if (!$done) {
            throw new \invalid_parameter_exception('Unknown structure operation');
        }
        return view_structure::definition($definition);
    }

    /** A visual editor with explicit labels and accessible move controls. */
    public static function render(array $definition): string {
        $str = static function(string $key): string { return get_string('template_' . $key, 'block_exaport'); };
        $types = [];
        foreach (view_structure::TYPES as $type) {
            $types[$type] = $str('type_' . $type);
        }
        $input = static function(string $name, $value, bool $area = false): string {
            $attributes = ['name' => $name, 'id' => 'id_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name),
                'class' => 'form-control', 'value' => $value];
            if ($area) {
                unset($attributes['value']);
                $attributes['rows'] = 3;
                return \html_writer::tag('textarea', s($value), $attributes);
            }
            $attributes['type'] = 'text';
            return \html_writer::empty_tag('input', $attributes);
        };
        $field = static function(string $label, string $name, string $control): string {
            $id = 'id_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name);
            return \html_writer::div(\html_writer::tag('label', $label, ['for' => $id]) . $control, 'mb-3');
        };
        $select = static function(array $options, string $name, $value): string {
            return \html_writer::select($options, $name, $value, false,
                ['id' => 'id_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name)]);
        };
        $button = static function(string $op, string $label, string $class = 'btn-outline-secondary'): string {
            return \html_writer::tag('button', $label, ['type' => 'submit', 'name' => 'operation',
                'value' => $op, 'class' => 'btn btn-sm ' . $class]);
        };
        $add = static function(string $parent) use ($types, $button, $str): string {
            $name = 'addtypes[' . ($parent ?: 'root') . ']';
            return \html_writer::div(\html_writer::tag('label', $str('element_type'), ['for' => 'id_add_' . $parent]) .
                \html_writer::select($types, $name, 'field', false, ['id' => 'id_add_' . $parent, 'class' => 'form-select']) .
                $button('add:' . $parent, $str('add_element'), 'btn-outline-primary'), 'exaport-template-add d-flex align-items-center gap-2 my-3');
        };
        $render = function(array $nodes, int $depth = 0) use (&$render, $input, $field, $select, $button, $add, $str, $definition): string {
            $html = '';
            foreach ($nodes as $node) {
                $key = $node['key'];
                $prefix = 'nodes[' . $key . ']';
                $html .= \html_writer::start_tag('fieldset', ['class' => 'exaport-template-editor-element border rounded p-3 my-3']);
                $html .= \html_writer::tag('legend', $str('type_' . $node['type']), ['class' => 'h5 w-auto px-2']);
                $html .= $field($str('label'), $prefix . '[label]', $input($prefix . '[label]', $node['label']));
                $html .= $field($str('instructions'), $prefix . '[instructions]', $input($prefix . '[instructions]', $node['instructions'], true));
                if (!$depth && $definition['columns'] > 1) {
                    $html .= $field($str('column'), $prefix . '[column]', $input($prefix . '[column]', $node['column']));
                }
                if ($node['type'] === 'field') {
                    $html .= $field($str('default'), $prefix . '[default]', $input($prefix . '[default]', $node['default'], true));
                    $html .= $field($str('multiline'), $prefix . '[multiline]', $select(
                        [0 => get_string('no'), 1 => get_string('yes')], $prefix . '[multiline]', (int)$node['multiline']));
                } else if ($node['type'] === 'artefact') {
                    $options = ['*' => $str('accept_any'), 'image' => $str('accept_image'),
                        'video' => $str('accept_video'), 'audio' => $str('accept_audio')];
                    $html .= $field($str('accept'), $prefix . '[accept]', $select($options, $prefix . '[accept]', $node['accept']));
                    $html .= $field($str('reflection_prompt'), $prefix . '[reflection]', $input($prefix . '[reflection]', $node['reflection'], true));
                } else if ($node['type'] === 'section') {
                    $html .= $field($str('repeatable'), $prefix . '[repeatable]', $select(
                        [0 => get_string('no'), 1 => get_string('yes')], $prefix . '[repeatable]', (int)$node['repeatable']));
                    $html .= $field($str('minimum'), $prefix . '[minimum]', $input($prefix . '[minimum]', $node['minimum']));
                    $html .= $render($node['children'], $depth + 1) . $add($key);
                }
                $html .= \html_writer::div($button('up:' . $key, $str('move_up')) . ' ' .
                    $button('down:' . $key, $str('move_down')) . ' ' .
                    $button('duplicate:' . $key, $str('duplicate')) . ' ' .
                    $button('remove:' . $key, get_string('delete'), 'btn-outline-danger'), 'd-flex flex-wrap gap-2');
                $html .= \html_writer::end_tag('fieldset');
            }
            return $html;
        };
        $examples = '';
        foreach (self::examples() as $key => $example) {
            $examples .= ' ' . $button('example:' . $key, get_string('template_add_example', 'block_exaport', s($example['name'])));
        }
        return \html_writer::div($examples, 'd-flex flex-wrap gap-2 mb-4') .
            $field($str('columns'), 'columns', $select(array_combine(range(1, 5), range(1, 5)),
                'columns', $definition['columns'])) .
            $render($definition['nodes']) . $add('') . $button('save:', get_string('savechanges'), 'btn-primary');
    }
}
