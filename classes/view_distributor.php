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

require_once(__DIR__ . '/../lib/lib.php');

/**
 * View distribution class
 *
 * Handles distribution of view templates to students
 */
class view_distributor {

    /**
     * Check if a view with the same name exists for a user
     *
     * @param int $userid User ID
     * @param string $name View name
     * @return int|false View ID if exists, false otherwise
     */
    private static function view_exists($userid, $name) {
        global $DB;

        $existing = $DB->get_record('block_exaportview', array(
            'userid' => $userid,
            'name' => $name,
        ));

        return $existing ? $existing->id : false;
    }

    /**
     * Create a view for a user if it doesn't exist
     *
     * @param int $userid User ID
     * @param string $name View name
     * @param string $description View description
     * @param int $courseid Course ID (for teacher sharing)
     * @param bool $share_to_teachers Whether to share to course teachers if newly created
     * @return array ['created' => bool, 'viewid' => int]
     */
    private static function create_view_if_not_exists($userid, $name, $description, $courseid = 0, $share_to_teachers = false) {
        global $DB, $USER;

        // Check if exists.
        $existing = self::view_exists($userid, $name);
        if ($existing) {
            return array('created' => false, 'viewid' => $existing);
        }

        // Create new view.
        $view = new \stdClass();
        $view->userid = $userid;
        $view->name = $name;
        $view->description = $description;
        $view->timemodified = time();
        $view->shareall = 0;
        $view->externaccess = 0;
        $view->externcomment = 0;
        $view->langid = 0;
        $view->layout = 2;  // Default layout.
        $view->creatorid = !empty($USER->id) ? $USER->id : 0;

        $viewid = $DB->insert_record('block_exaportview', $view);

        // Generate hash for the view.
        $hash = substr(bin2hex(random_bytes(4)), 0, 8);
        $DB->set_field('block_exaportview', 'hash', $hash, array('id' => $viewid));

        // Share to course teachers if requested (ONLY for newly created views).
        if ($share_to_teachers && $courseid > 0) {
            // Use existing function.
            block_exaport_share_view_to_teachers($viewid);
        }

        return array('created' => true, 'viewid' => $viewid);
    }

    /**
     * Distribute views to a single user
     *
     * @param int $userid User ID
     * @param array $views Array of view template objects
     * @param int $courseid Course ID (for teacher sharing)
     * @return array ['created' => int, 'skipped' => int]
     */
    public static function distribute_to_user($userid, $views, $courseid = 0) {
        $created = 0;
        $skipped = 0;

        foreach ($views as $view) {
            $result = self::create_view_if_not_exists(
                $userid,
                $view->name,
                $view->description,
                $courseid,
                $view->share_to_teachers
            );

            if ($result['created']) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return array('created' => $created, 'skipped' => $skipped);
    }

    /**
     * Distribute views to all students in a course
     *
     * @param int $courseid Course ID
     * @return array ['students' => int, 'created' => int, 'skipped' => int, 'errors' => array]
     */
    public static function distribute_to_course($courseid) {
        global $DB;

        // Get view template for this course.
        $views = view_template::get_course_template($courseid);
        if (empty($views)) {
            return array(
                'students' => 0,
                'created' => 0,
                'skipped' => 0,
                'errors' => array(get_string('no_template_to_distribute', 'block_exaport')),
            );
        }

        // Get all students in the course.
        $students = \block_exaport_get_course_students_by_courseid($courseid);
        if (empty($students)) {
            return array(
                'students' => 0,
                'created' => 0,
                'skipped' => 0,
                'errors' => array(get_string('no_students_enrolled', 'block_exaport')),
            );
        }

        $total_created = 0;
        $total_skipped = 0;
        $errors = array();

        foreach ($students as $studentid) {
            try {
                $result = self::distribute_to_user($studentid, $views, $courseid);
                $total_created += $result['created'];
                $total_skipped += $result['skipped'];
            } catch (\Exception $e) {
                $errors[] = "User {$studentid}: " . $e->getMessage();
            }
        }

        return array(
            'students' => count($students),
            'created' => $total_created,
            'skipped' => $total_skipped,
            'errors' => $errors,
        );
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
            // Return default settings.
            $settings = new \stdClass();
            $settings->courseid = $courseid;
            $settings->auto_distribute = 0;
            $settings->auto_distribute_views = 0;
            $settings->timemodified = time();
        }

        return $settings;
    }

    /**
     * Update distribution settings for a course
     *
     * @param int $courseid Course ID
     * @param bool $auto_distribute_views Auto-distribute views on enrolment
     * @return bool Success
     */
    public static function update_settings($courseid, $auto_distribute_views) {
        global $DB;

        $settings = $DB->get_record('block_exaport_templ_dist', array('courseid' => $courseid));
        
        if ($settings) {
            $settings->auto_distribute_views = $auto_distribute_views ? 1 : 0;
            $settings->timemodified = time();
            return $DB->update_record('block_exaport_templ_dist', $settings);
        } else {
            $settings = new \stdClass();
            $settings->courseid = $courseid;
            $settings->auto_distribute = 0;  // Keep existing category setting.
            $settings->auto_distribute_views = $auto_distribute_views ? 1 : 0;
            $settings->timemodified = time();
            return $DB->insert_record('block_exaport_templ_dist', $settings) !== false;
        }
    }
}
