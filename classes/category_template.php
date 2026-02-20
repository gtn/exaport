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
 * Category template management class
 *
 * Handles template creation, loading, saving, and manipulation
 */
class category_template {

    /**
     * Get starter templates from configuration
     *
     * @return array Array of template objects
     */
    public static function get_starter_templates() {
        $templates_json = get_config('block_exaport', 'starter_templates');
        if (empty($templates_json)) {
            return array();
        }

        $templates = json_decode($templates_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('Invalid starter templates JSON: ' . json_last_error_msg(), DEBUG_DEVELOPER);
            return array();
        }

        return $templates;
    }

    /**
     * Get the course template for a given course
     *
     * @param int $courseid Course ID
     * @return array Tree structure of template nodes
     */
    public static function get_course_template($courseid) {
        global $DB;

        $nodes = $DB->get_records('block_exaport_course_templ', array('courseid' => $courseid), 'sortorder ASC');
        if (empty($nodes)) {
            return array();
        }

        // Build tree structure.
        return self::build_tree($nodes);
    }

    /**
     * Build a tree structure from flat template nodes
     *
     * @param array $nodes Array of template node records
     * @param int $pid Parent ID (0 for root)
     * @return array Tree structure
     */
    public static function build_tree($nodes, $pid = 0) {
        $tree = array();
        foreach ($nodes as $node) {
            if ($node->pid == $pid) {
                $children = self::build_tree($nodes, $node->id);
                $item = array(
                    'id' => $node->id,
                    'name' => $node->name,
                    'share_to_teachers' => isset($node->share_to_teachers) ? $node->share_to_teachers : 0,
                    'children' => $children,
                );
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * Save a course template (replaces existing)
     *
     * @param int $courseid Course ID
     * @param array $tree Tree structure to save
     * @return bool Success
     */
    public static function save_course_template($courseid, $tree) {
        global $DB;

        // Delete existing template for this course.
        $DB->delete_records('block_exaport_course_templ', array('courseid' => $courseid));

        // Insert new template.
        $sortorder = 0;
        self::save_nodes($courseid, $tree, 0, $sortorder);

        return true;
    }

    /**
     * Recursively save template nodes
     *
     * @param int $courseid Course ID
     * @param array $tree Tree structure
     * @param int $pid Parent ID
     * @param int $sortorder Sort order counter (by reference)
     * @return void
     */
    private static function save_nodes($courseid, $tree, $pid, &$sortorder) {
        global $DB;

        foreach ($tree as $node) {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->pid = $pid;
            $record->name = $node['name'];
            $record->sortorder = $sortorder++;
            $record->share_to_teachers = isset($node['share_to_teachers']) ? $node['share_to_teachers'] : 0;
            $record->timemodified = time();

            $newid = $DB->insert_record('block_exaport_course_templ', $record);

            // Save children if any.
            if (!empty($node['children'])) {
                self::save_nodes($courseid, $node['children'], $newid, $sortorder);
            }
        }
    }

    /**
     * Load a starter template into a course template
     *
     * @param int $courseid Course ID
     * @param string $template_name Name of starter template
     * @return bool Success
     */
    public static function load_starter_template($courseid, $template_name) {
        $templates = self::get_starter_templates();

        foreach ($templates as $template) {
            if ($template['name'] === $template_name && isset($template['tree'])) {
                // Normalize the tree structure.
                $tree = self::normalize_tree($template['tree']);
                return self::save_course_template($courseid, array($tree));
            }
        }

        return false;
    }

    /**
     * Normalize template tree structure
     *
     * Ensures tree has correct structure with children arrays
     *
     * @param array $node Template node
     * @return array Normalized node
     */
    private static function normalize_tree($node) {
        $normalized = array(
            'name' => $node['name'],
            'share_to_teachers' => isset($node['share_to_teachers']) ? $node['share_to_teachers'] : 0,
            'children' => array(),
        );

        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $normalized['children'][] = self::normalize_tree($child);
            }
        }

        return $normalized;
    }

    /**
     * Add a category to the course template
     *
     * @param int $courseid Course ID
     * @param string $name Category name
     * @param int $pid Parent ID (0 for root)
     * @param int $share_to_teachers Whether to share to teachers (default 0)
     * @return int New category ID
     */
    public static function add_category($courseid, $name, $pid = 0, $share_to_teachers = 0) {
        global $DB;

        // Get max sortorder for this parent.
        $maxsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {block_exaport_course_templ} WHERE courseid = ? AND pid = ?',
            array($courseid, $pid)
        );
        // MAX returns null when no records exist, false on error.
        if ($maxsort === false) {
            debugging('Database error getting max sortorder', DEBUG_DEVELOPER);
            return false;
        }
        $sortorder = ($maxsort === null) ? 0 : $maxsort + 1;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->pid = $pid;
        $record->name = $name;
        $record->sortorder = $sortorder;
        $record->share_to_teachers = $share_to_teachers;
        $record->timemodified = time();

        return $DB->insert_record('block_exaport_course_templ', $record);
    }

    /**
     * Rename a template category
     *
     * @param int $categoryid Category ID
     * @param string $newname New name
     * @return bool Success
     */
    public static function rename_category($categoryid, $newname) {
        global $DB;

        $category = $DB->get_record('block_exaport_course_templ', array('id' => $categoryid));
        if (!$category) {
            return false;
        }

        $category->name = $newname;
        $category->timemodified = time();

        return $DB->update_record('block_exaport_course_templ', $category);
    }

    /**
     * Move a template category to a new parent
     *
     * @param int $categoryid Category ID
     * @param int $newpid New parent ID
     * @return bool Success
     */
    public static function move_category($categoryid, $newpid) {
        global $DB;

        $category = $DB->get_record('block_exaport_course_templ', array('id' => $categoryid));
        if (!$category) {
            return false;
        }

        // Prevent circular references.
        if ($newpid !== 0) {
            $descendants = self::get_descendants($categoryid);
            if (in_array($newpid, $descendants)) {
                return false;
            }
        }

        $category->pid = $newpid;
        $category->timemodified = time();

        return $DB->update_record('block_exaport_course_templ', $category);
    }

    /**
     * Remove a template category and its children
     *
     * @param int $categoryid Category ID
     * @return bool Success
     */
    public static function remove_category($categoryid) {
        global $DB;

        // Get all descendants.
        $descendants = self::get_descendants($categoryid);
        $descendants[] = $categoryid;

        // Delete all at once.
        list($sql, $params) = $DB->get_in_or_equal($descendants);
        return $DB->delete_records_select('block_exaport_course_templ', "id $sql", $params);
    }

    /**
     * Get all descendant IDs of a category
     *
     * @param int $categoryid Category ID
     * @return array Array of descendant IDs
     */
    private static function get_descendants($categoryid) {
        global $DB;

        $descendants = array();
        $children = $DB->get_records('block_exaport_course_templ', array('pid' => $categoryid), '', 'id');

        foreach ($children as $child) {
            $descendants[] = $child->id;
            $descendants = array_merge($descendants, self::get_descendants($child->id));
        }

        return $descendants;
    }

    /**
     * Verify a template category belongs to a course
     *
     * @param int $categoryid Category ID
     * @param int $courseid Course ID
     * @return object Category record
     * @throws \moodle_exception If category doesn't belong to course
     */
    public static function verify_category($categoryid, $courseid) {
        global $DB;

        $category = $DB->get_record('block_exaport_course_templ', array('id' => $categoryid, 'courseid' => $courseid));
        if (!$category) {
            throw new \moodle_exception('Invalid category');
        }

        return $category;
    }
}
