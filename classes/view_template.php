<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * View template management class
 *
 * Handles view template creation, loading, saving, and manipulation
 */
class view_template {
    /**
     * Table containing course-owned heading and text blocks.
     *
     * @var string
     */
    public const BLOCKS_TABLE = 'block_exaport_templ_block';

    /**
     * Get starter view templates from configuration
     *
     * @return array Array of template objects
     */
    public static function get_starter_templates() {
        $templates_json = get_config('block_exaport', 'starter_view_templates');
        if (empty($templates_json)) {
            return array();
        }

        $templates = json_decode($templates_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('Invalid starter view templates JSON: ' . json_last_error_msg(), DEBUG_DEVELOPER);
            return array();
        }

        return $templates;
    }

    /**
     * Get the course view template for a given course
     *
     * @param int $courseid Course ID
     * @return array Array of view template objects
     */
    public static function get_course_template($courseid) {
        global $DB;

        $views = $DB->get_records('block_exaport_view_templ',
            array('courseid' => $courseid),
            'sortorder ASC, id ASC'
        );

        return array_values($views);
    }

    /**
     * Load a starter template into the course template (replaces existing)
     *
     * @param int $courseid Course ID
     * @param string $templatename Name of starter template to load
     * @return bool Success
     */
    public static function load_starter_template($courseid, $templatename) {
        global $DB;

        $templates = self::get_starter_templates();
        $selected_template = null;

        foreach ($templates as $template) {
            if ($template['name'] === $templatename) {
                $selected_template = $template;
                break;
            }
        }

        if (!$selected_template || !isset($selected_template['views'])) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        self::clear_course_template($courseid);

        // Insert new template views.
        $sortorder = 0;
        foreach ($selected_template['views'] as $view) {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->name = $view['name'];
            $record->description = isset($view['description']) ? $view['description'] : '';
            $record->share_to_teachers = isset($view['share_to_teachers']) ? $view['share_to_teachers'] : 0;
            $record->sortorder = $sortorder++;
            $record->timemodified = time();

            $DB->insert_record('block_exaport_view_templ', $record);
        }

        $transaction->allow_commit();
        return true;
    }

    /**
     * Add a view to the course template
     *
     * @param int $courseid Course ID
     * @param string $name View name
     * @param string $description View description
     * @param bool $share_to_teachers Whether to share to course teachers
     * @return int View template ID
     */
    public static function add_view($courseid, $name, $description = '', $share_to_teachers = false) {
        global $DB;

        // Get max sortorder.
        $maxsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {block_exaport_view_templ} WHERE courseid = ?',
            array($courseid)
        );
        $sortorder = ($maxsort !== false && $maxsort !== null) ? $maxsort + 1 : 0;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->name = $name;
        $record->description = $description;
        $record->share_to_teachers = $share_to_teachers ? 1 : 0;
        $record->sortorder = $sortorder;
        $record->timemodified = time();

        return $DB->insert_record('block_exaport_view_templ', $record);
    }

    /**
     * Update the layout used for newly distributed views.
     *
     * @param int $viewid View template ID
     * @param int $layout Layout ID from the existing view editor (1–10)
     * @return bool Success
     */
    public static function update_layout($viewid, int $layout) {
        global $DB;

        view_layout::columns($layout);

        $view = $DB->get_record('block_exaport_view_templ', array('id' => $viewid));
        if (!$view) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        $view->layout = $layout;
        $view->timemodified = time();
        $DB->update_record('block_exaport_view_templ', $view);
        // Preserve blocks from columns removed by a layout change by appending them to column one.
        self::save_blocks($viewid, self::get_blocks($viewid));
        $transaction->allow_commit();
        return true;
    }

    /**
     * Return the template's blocks in their display order.
     *
     * @param int $templateid View template ID
     * @return \stdClass[] Stored block records
     */
    public static function get_blocks(int $templateid): array {
        global $DB;
        return array_values($DB->get_records(
            self::BLOCKS_TABLE,
            ['templateid' => $templateid],
            'positionx, positiony, id'
        ));
    }

    /**
     * Validate the editor's block list and keep only fields supported by templates.
     *
     * @param array $blocks Block records supplied as arrays or objects
     * @param int $layout Layout ID
     * @return \stdClass[] Validated block records with consistent positions
     */
    public static function normalise_blocks(array $blocks, int $layout): array {
        $columns = view_layout::columns($layout);
        $records = [];
        foreach ($blocks as $block) {
            $block = (array)$block;
            if (
                !in_array($block['type'] ?? '', ['headline', 'text'], true) ||
                !is_string($block['text'] ?? '') ||
                !is_string($block['block_title'] ?? '')
            ) {
                throw new \invalid_parameter_exception('Invalid template block.');
            }
            $record = (object)[
                'type' => $block['type'],
                'text' => $block['type'] === 'headline' ? clean_param($block['text'] ?? '', PARAM_TEXT) :
                    clean_text($block['text'] ?? '', FORMAT_HTML),
                'block_title' => clean_param($block['block_title'] ?? '', PARAM_TEXT),
                'positionx' => filter_var($block['positionx'] ?? 1, FILTER_VALIDATE_INT),
                'positiony' => filter_var($block['positiony'] ?? 1, FILTER_VALIDATE_INT),
            ];
            if (
                $record->positionx < 1 ||
                $record->positionx > 5 ||
                $record->positiony < 1 ||
                \core_text::strlen($record->block_title) > 1000
            ) {
                throw new \invalid_parameter_exception('Invalid template block position or title.');
            }
            if (preg_match('~@@PLUGINFILE@@|(?:src|href)\s*=\s*["\'][^"\']*(?:draftfile|pluginfile)\.php~i', $record->text)) {
                throw new \moodle_exception('view_template_files_unsupported', 'block_exaport');
            }
            $records[] = $record;
        }
        usort($records, static function ($a, $b) {
            return [$a->positionx, $a->positiony] <=> [$b->positionx, $b->positiony];
        });
        $positions = [];
        foreach ($records as $record) {
            $record->positionx = $record->positionx <= $columns ? $record->positionx : 1;
            $record->positiony = $positions[$record->positionx] = ($positions[$record->positionx] ?? 0) + 1;
        }
        return $records;
    }

    /**
     * Supply the existing editor's presentation fields without storing them.
     *
     * @param array $blocks Block records supplied as arrays or objects
     * @return \stdClass[] Copies containing the formatted preview text
     */
    public static function editor_blocks(array $blocks): array {
        return array_map(static function ($block) {
            $block = (object)$block;
            $block = clone $block;
            $block->print_text = $block->type === 'headline' ? s($block->text) :
                format_text($block->text, FORMAT_HTML, ['filter' => false]);
            return $block;
        }, $blocks);
    }

    /**
     * Save the complete validated block list atomically.
     *
     * @param int $templateid View template ID
     * @param array $blocks Complete block list
     * @return void
     */
    public static function save_blocks(int $templateid, array $blocks): void {
        global $DB;
        $template = $DB->get_record('block_exaport_view_templ', ['id' => $templateid], '*', MUST_EXIST);
        $records = self::normalise_blocks($blocks, (int)($template->layout ?? 2));
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::BLOCKS_TABLE, ['templateid' => $templateid]);
        foreach ($records as $record) {
            $record->templateid = $templateid;
            $DB->insert_record(self::BLOCKS_TABLE, $record);
        }
        $DB->set_field('block_exaport_view_templ', 'timemodified', time(), ['id' => $templateid]);
        $transaction->allow_commit();
    }

    /**
     * Rename a view in the template
     *
     * @param int $viewid View template ID
     * @param string $newname New name
     * @return bool Success
     */
    public static function rename_view($viewid, $newname) {
        global $DB;

        $view = $DB->get_record('block_exaport_view_templ', array('id' => $viewid));
        if (!$view) {
            return false;
        }

        $view->name = $newname;
        $view->timemodified = time();

        return $DB->update_record('block_exaport_view_templ', $view);
    }

    /**
     * Update description of a view in the template
     *
     * @param int $viewid View template ID
     * @param string $description New description
     * @return bool Success
     */
    public static function update_description($viewid, $description) {
        global $DB;

        $view = $DB->get_record('block_exaport_view_templ', array('id' => $viewid));
        if (!$view) {
            return false;
        }

        $view->description = $description;
        $view->timemodified = time();

        return $DB->update_record('block_exaport_view_templ', $view);
    }

    /**
     * Toggle share_to_teachers setting for a view
     *
     * @param int $viewid View template ID
     * @param bool $share Whether to share to teachers
     * @return bool Success
     */
    public static function toggle_share_to_teachers($viewid, $share) {
        global $DB;

        $view = $DB->get_record('block_exaport_view_templ', array('id' => $viewid));
        if (!$view) {
            return false;
        }

        $view->share_to_teachers = $share ? 1 : 0;
        $view->timemodified = time();

        return $DB->update_record('block_exaport_view_templ', $view);
    }

    /**
     * Remove a view from the template
     *
     * @param int $viewid View template ID
     * @return bool Success
     */
    public static function remove_view($viewid) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::BLOCKS_TABLE, array('templateid' => $viewid));
        $result = $DB->delete_records('block_exaport_view_templ', array('id' => $viewid));
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Delete the course's view templates together with their blocks.
     *
     * @param int $courseid Course ID
     * @return void
     */
    public static function clear_course_template(int $courseid): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select(
            self::BLOCKS_TABLE,
            'templateid IN (SELECT id FROM {block_exaport_view_templ} WHERE courseid = ?)',
            [$courseid]
        );
        $DB->delete_records('block_exaport_view_templ', ['courseid' => $courseid]);
        $transaction->allow_commit();
    }

    /**
     * Verify that a view template record belongs to the specified course
     *
     * @param int $viewid View template ID
     * @param int $courseid Course ID
     * @return bool True if view belongs to course
     */
    public static function verify_view($viewid, $courseid) {
        global $DB;

        $view = $DB->get_record('block_exaport_view_templ',
            array('id' => $viewid, 'courseid' => $courseid));

        return $view !== false;
    }
}
