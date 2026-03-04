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

        // Delete existing course template.
        $DB->delete_records('block_exaport_view_templ', array('courseid' => $courseid));

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

        return $DB->delete_records('block_exaport_view_templ', array('id' => $viewid));
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
