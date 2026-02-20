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
 * Category distribution class
 *
 * Handles distribution of category templates to students
 */
class category_distributor {

    /**
     * Check if a category exists at a specific path for a user
     *
     * @param int $userid User ID
     * @param string $name Category name
     * @param int $pid Parent category ID
     * @return int|false Category ID if exists, false otherwise
     */
    private static function category_exists_at_path($userid, $name, $pid) {
        global $DB;

        $existing = $DB->get_record('block_exaportcate', array(
            'userid' => $userid,
            'name' => $name,
            'pid' => $pid,
            'courseid' => 0,  // Global categories only.
        ));

        return $existing ? $existing->id : false;
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
    private static function create_category_if_not_exists($userid, $name, $pid, $courseid = 0, $share_to_teachers = false) {
        global $DB;

        // Check if exists.
        $existing = self::category_exists_at_path($userid, $name, $pid);
        if ($existing) {
            return array('created' => false, 'categoryid' => $existing);
        }

        // Create new category.
        $category = new \stdClass();
        $category->userid = $userid;
        $category->name = $name;
        $category->pid = $pid;
        $category->courseid = 0;  // Global category.
        $category->timemodified = time();

        $categoryid = $DB->insert_record('block_exaportcate', $category);

        // Share to course teachers if requested (ONLY for newly created categories).
        if ($share_to_teachers && $courseid > 0) {
            self::share_new_category_to_teachers($categoryid, $courseid, true);
        }

        return array('created' => true, 'categoryid' => $categoryid);
    }

    /**
     * Share a category to all teachers in a course.
     *
     * IMPORTANT: This must ONLY make changes when the category is newly created.
     *
     * @param int $categoryid Category ID
     * @param int $courseid Course ID
     * @param bool $isnewlycreated Must be true to perform any DB writes
     * @return void
     */
    private static function share_new_category_to_teachers($categoryid, $courseid, $isnewlycreated = false) {
        global $DB;

        // Hard guard: NEVER modify sharing/internshare for existing categories.
        if (!$isnewlycreated) {
            return;
        }

        // Get course context.
        $context = \context_course::instance($courseid);

        // Get teachers - users with block/exaport:distributecategories capability.
        $teachers = get_enrolled_users($context, 'block/exaport:distributecategories', 0, 'u.id', null, 0, 0, true);

        foreach ($teachers as $teacher) {
            // Create share record if missing (writes are allowed only for new categories).
            if (!$DB->record_exists('block_exaportcatshar', array('catid' => $categoryid, 'userid' => $teacher->id))) {
                $share = new \stdClass();
                $share->catid = $categoryid;
                $share->userid = $teacher->id;
                $DB->insert_record('block_exaportcatshar', $share);
            }
        }

        // Mark category as internally shared (writes are allowed only for new categories).
        $DB->set_field('block_exaportcate', 'internshare', 1, array('id' => $categoryid));
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
    public static function distribute_to_user($userid, $tree, $parent_catid = 0, $courseid = 0) {
        $stats = array('created' => 0, 'skipped' => 0);

        foreach ($tree as $node) {
            $share_to_teachers = isset($node['share_to_teachers']) ? $node['share_to_teachers'] : 0;
            $result = self::create_category_if_not_exists($userid, $node['name'], $parent_catid, $courseid, $share_to_teachers);

            if ($result['created']) {
                $stats['created']++;
            } else {
                $stats['skipped']++;
            }

            // Process children.
            if (!empty($node['children'])) {
                $child_stats = self::distribute_to_user($userid, $node['children'], $result['categoryid'], $courseid);
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
    public static function distribute_to_course($courseid) {
        $template = category_template::get_course_template($courseid);
        if (empty($template)) {
            return array('created' => 0, 'skipped' => 0, 'students' => 0, 'error' => 'no_template');
        }

        // Get enrolled students.
        $context = \context_course::instance($courseid);
        $students = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);

        $total_stats = array('created' => 0, 'skipped' => 0, 'students' => 0);

        foreach ($students as $student) {
            // Skip if user has capability to distribute (teachers).
            if (has_capability('block/exaport:distributecategories', $context, $student->id)) {
                continue;
            }

            $stats = self::distribute_to_user($student->id, $template, 0, $courseid);
            $total_stats['created'] += $stats['created'];
            $total_stats['skipped'] += $stats['skipped'];
            $total_stats['students']++;
        }

        return $total_stats;
    }

    /**
     * Get distribution settings for a course
     *
     * @param int $courseid Course ID
     * @return object Settings object
     */
    public static function get_settings($courseid) {
        global $DB;

        $settings = $DB->get_record('block_exaport_templ_dist', array('courseid' => $courseid));

        if (!$settings) {
            // Create default settings.
            $settings = new \stdClass();
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
    public static function update_settings($courseid, $auto_distribute) {
        global $DB;

        $settings = self::get_settings($courseid);
        $settings->auto_distribute = $auto_distribute ? 1 : 0;
        $settings->timemodified = time();

        return $DB->update_record('block_exaport_templ_dist', $settings);
    }
}
