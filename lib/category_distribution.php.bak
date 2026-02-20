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

defined('MOODLE_INTERNAL') || die();

/**
 * Get starter templates from configuration
 *
 * @return array Array of template objects
 */
function block_exaport_get_starter_templates() {
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
function block_exaport_get_course_template($courseid) {
    global $DB;

    $nodes = $DB->get_records('block_exaport_course_templ', array('courseid' => $courseid), 'sortorder ASC');
    if (empty($nodes)) {
        return array();
    }

    // Build tree structure.
    return block_exaport_build_template_tree($nodes);
}

/**
 * Build a tree structure from flat template nodes
 *
 * @param array $nodes Array of template node records
 * @param int $pid Parent ID (0 for root)
 * @return array Tree structure
 */
function block_exaport_build_template_tree($nodes, $pid = 0) {
    $tree = array();
    foreach ($nodes as $node) {
        if ($node->pid == $pid) {
            $children = block_exaport_build_template_tree($nodes, $node->id);
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
function block_exaport_save_course_template($courseid, $tree) {
    global $DB;

    // Delete existing template for this course.
    $DB->delete_records('block_exaport_course_templ', array('courseid' => $courseid));

    // Insert new template.
    $sortorder = 0;
    block_exaport_save_template_nodes($courseid, $tree, 0, $sortorder);

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
function block_exaport_save_template_nodes($courseid, $tree, $pid, &$sortorder) {
    global $DB;

    foreach ($tree as $node) {
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->pid = $pid;
        $record->name = $node['name'];
        $record->sortorder = $sortorder++;
        $record->share_to_teachers = isset($node['share_to_teachers']) ? $node['share_to_teachers'] : 0;
        $record->timemodified = time();

        $newid = $DB->insert_record('block_exaport_course_templ', $record);

        // Save children if any.
        if (!empty($node['children'])) {
            block_exaport_save_template_nodes($courseid, $node['children'], $newid, $sortorder);
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
function block_exaport_load_starter_template($courseid, $template_name) {
    $templates = block_exaport_get_starter_templates();

    foreach ($templates as $template) {
        if ($template['name'] === $template_name) {
            // Normalize and save the tree structure directly.
            $normalized = block_exaport_normalize_template_tree($template['tree']);
            // Save as array with single root.
            return block_exaport_save_course_template($courseid, array($normalized));
        }
    }

    return false;
}

/**
 * Normalize a template tree structure (ensure consistent format)
 *
 * @param array $node Node to normalize
 * @return array Normalized node
 */
function block_exaport_normalize_template_tree($node) {
    $normalized = array(
        'name' => $node['name'],
        'children' => array(),
    );

    if (!empty($node['children'])) {
        foreach ($node['children'] as $child) {
            $normalized['children'][] = block_exaport_normalize_template_tree($child);
        }
    }

    return $normalized;
}

/**
 * Check if a category exists for a user at a specific path
 *
 * @param int $userid User ID
 * @param string $name Category name
 * @param int $pid Parent category ID
 * @return int|bool Category ID if exists, false otherwise
 */
function block_exaport_category_exists_at_path($userid, $name, $pid) {
    global $DB;

    $category = $DB->get_record('block_exaportcate', array(
        'userid' => $userid,
        'name' => $name,
        'pid' => $pid,
        'courseid' => 0,  // Global categories only.
    ));

    return $category ? $category->id : false;
}

/**
 * Create a category for a user if it doesn't exist
 *
 * @param int $userid User ID
 * @param string $name Category name
 * @param int $pid Parent category ID
 * @param int $courseid Course ID (for teacher sharing)
 * @param bool $share_to_teachers Whether to share to course teachers if newly created
 * @return array ['created' => bool, 'categoryid' => int]
 */
function block_exaport_create_category_if_not_exists($userid, $name, $pid, $courseid = 0, $share_to_teachers = false) {
    global $DB;

    // Check if exists.
    $existing = block_exaport_category_exists_at_path($userid, $name, $pid);
    if ($existing) {
        return array('created' => false, 'categoryid' => $existing);
    }

    // Create new category.
    $category = new stdClass();
    $category->userid = $userid;
    $category->name = $name;
    $category->pid = $pid;
    $category->courseid = 0;  // Global category.
    $category->timemodified = time();

    $categoryid = $DB->insert_record('block_exaportcate', $category);

    // Share to course teachers if requested.
    if ($share_to_teachers && $courseid > 0) {
        block_exaport_share_category_to_teachers($categoryid, $courseid);
    }

    return array('created' => true, 'categoryid' => $categoryid);
}

/**
 * Share a category to all teachers in a course
 *
 * @param int $categoryid Category ID
 * @param int $courseid Course ID
 * @return void
 */
function block_exaport_share_category_to_teachers($categoryid, $courseid) {
    global $DB;

    // Get course context.
    $context = context_course::instance($courseid);
    
    // Get teachers - users with block/exaport:distributecategories capability.
    $teachers = get_enrolled_users($context, 'block/exaport:distributecategories', 0, 'u.id', null, 0, 0, true);
    
    foreach ($teachers as $teacher) {
        // Check if sharing already exists.
        if (!$DB->record_exists('block_exaportcatshar', array('catid' => $categoryid, 'userid' => $teacher->id))) {
            $share = new stdClass();
            $share->catid = $categoryid;
            $share->userid = $teacher->id;
            $DB->insert_record('block_exaportcatshar', $share);
        }
    }
}

/**
 * Distribute template categories to a single user
 *
 * @param int $userid User ID
 * @param array $tree Template tree structure
 * @param int $parent_catid Parent category ID in user's categories
 * @param int $courseid Course ID (for teacher sharing)
 * @return array Statistics ['created' => int, 'skipped' => int]
 */
function block_exaport_distribute_to_user($userid, $tree, $parent_catid = 0, $courseid = 0) {
    $stats = array('created' => 0, 'skipped' => 0);

    foreach ($tree as $node) {
        $share_to_teachers = isset($node['share_to_teachers']) ? $node['share_to_teachers'] : 0;
        $result = block_exaport_create_category_if_not_exists($userid, $node['name'], $parent_catid, $courseid, $share_to_teachers);

        if ($result['created']) {
            $stats['created']++;
        } else {
            $stats['skipped']++;
        }

        // Process children.
        if (!empty($node['children'])) {
            $child_stats = block_exaport_distribute_to_user($userid, $node['children'], $result['categoryid'], $courseid);
            $stats['created'] += $child_stats['created'];
            $stats['skipped'] += $child_stats['skipped'];
        }
    }

    return $stats;
}

/**
 * Distribute template to all enrolled students in a course
 *
 * @param int $courseid Course ID
 * @return array Statistics ['created' => int, 'skipped' => int, 'students' => int]
 */
function block_exaport_distribute_to_course($courseid) {
    global $DB;

    $template = block_exaport_get_course_template($courseid);
    if (empty($template)) {
        return array('created' => 0, 'skipped' => 0, 'students' => 0, 'error' => 'no_template');
    }

    // Get enrolled students.
    $context = context_course::instance($courseid);
    // Get users with student role (typically 'student' archetype).
    $students = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);

    $total_stats = array('created' => 0, 'skipped' => 0, 'students' => 0);

    foreach ($students as $student) {
        // Skip if user has capability to distribute (teachers).
        if (has_capability('block/exaport:distributecategories', $context, $student->id)) {
            continue;
        }

        $stats = block_exaport_distribute_to_user($student->id, $template, 0, $courseid);
        $total_stats['created'] += $stats['created'];
        $total_stats['skipped'] += $stats['skipped'];
        $total_stats['students']++;
    }

    return $total_stats;
}

/**
 * Get or create distribution settings for a course
 *
 * @param int $courseid Course ID
 * @return stdClass Distribution settings
 */
function block_exaport_get_distribution_settings($courseid) {
    global $DB;

    $settings = $DB->get_record('block_exaport_templ_dist', array('courseid' => $courseid));
    if (!$settings) {
        // Create default settings.
        $settings = new stdClass();
        $settings->courseid = $courseid;
        $settings->auto_distribute = 0;
        $settings->timemodified = time();
        $settings->id = $DB->insert_record('block_exaport_templ_dist', $settings);
    }

    return $settings;
}

/**
 * Update distribution settings for a course
 *
 * @param int $courseid Course ID
 * @param bool $auto_distribute Auto-distribute on enrolment
 * @return bool Success
 */
function block_exaport_update_distribution_settings($courseid, $auto_distribute) {
    global $DB;

    $settings = block_exaport_get_distribution_settings($courseid);
    $settings->auto_distribute = $auto_distribute ? 1 : 0;
    $settings->timemodified = time();

    return $DB->update_record('block_exaport_templ_dist', $settings);
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
function block_exaport_add_template_category($courseid, $name, $pid = 0, $share_to_teachers = 0) {
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

    $record = new stdClass();
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
 * @param int $categoryid Template category ID
 * @param string $newname New name
 * @return bool Success
 */
function block_exaport_rename_template_category($categoryid, $newname) {
    global $DB;

    $record = new stdClass();
    $record->id = $categoryid;
    $record->name = $newname;
    $record->timemodified = time();

    return $DB->update_record('block_exaport_course_templ', $record);
}

/**
 * Move a template category to a new parent
 *
 * @param int $categoryid Template category ID
 * @param int $newpid New parent ID
 * @return bool Success
 */
function block_exaport_move_template_category($categoryid, $newpid) {
    global $DB;

    // Prevent moving to self or descendant.
    if ($categoryid == $newpid) {
        return false;
    }

    $descendants = block_exaport_get_template_category_descendants($categoryid);
    if (in_array($newpid, $descendants)) {
        return false;
    }

    $record = new stdClass();
    $record->id = $categoryid;
    $record->pid = $newpid;
    $record->timemodified = time();

    return $DB->update_record('block_exaport_course_templ', $record);
}

/**
 * Remove a template category (and all descendants)
 *
 * @param int $categoryid Template category ID
 * @return bool Success
 */
function block_exaport_remove_template_category($categoryid) {
    global $DB;

    // Get all descendants.
    $descendants = block_exaport_get_template_category_descendants($categoryid);
    $ids = array_merge(array($categoryid), $descendants);

    // Delete all.
    list($insql, $params) = $DB->get_in_or_equal($ids);
    return $DB->delete_records_select('block_exaport_course_templ', "id $insql", $params);
}

/**
 * Get all descendant IDs of a template category
 *
 * @param int $categoryid Template category ID
 * @return array Array of descendant IDs
 */
function block_exaport_get_template_category_descendants($categoryid) {
    global $DB;

    $descendants = array();
    $children = $DB->get_records('block_exaport_course_templ', array('pid' => $categoryid), '', 'id');

    foreach ($children as $child) {
        $descendants[] = $child->id;
        $descendants = array_merge($descendants, block_exaport_get_template_category_descendants($child->id));
    }

    return $descendants;
}
