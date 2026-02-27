<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib/lib.php');

/**
 * Ad-hoc task for distributing categories and views to a user.
 * This task is queued when a user is enrolled to allow the enrollment to complete first.
 */
class distribute_to_user_task extends \core\task\adhoc_task {

    /**
     * Execute the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        
        $userid = $data->userid;
        $courseid = $data->courseid;
        
        // Check if user is enrolled in this course as a student.
        if (!block_exaport_is_enrolled_as_student($userid, $courseid)) {
            mtrace("User {$userid} is not enrolled as student in course {$courseid}, skipping distribution");
            return;
        }
        
        // Check if auto-distribution is enabled for categories.
        $category_settings = \block_exaport\category_distributor::get_settings($courseid);
        if ($category_settings->auto_distribute) {
            // Get course template.
            $template = \block_exaport\category_template::get_course_template($courseid);
            if (!empty($template)) {
                mtrace("Distributing categories to user {$userid} in course {$courseid}");
                \block_exaport\category_distributor::distribute_to_user($userid, $template, 0, $courseid);
            }
        }
        
        // Check if auto-distribution is enabled for views.
        $view_settings = \block_exaport\view_distributor::get_settings($courseid);
        if ($view_settings->auto_distribute_views) {
            // Get course view template.
            $view_template = \block_exaport\view_template::get_course_template($courseid);
            if (!empty($view_template)) {
                mtrace("Distributing views to user {$userid} in course {$courseid}");
                \block_exaport\view_distributor::distribute_to_user($userid, $view_template, $courseid);
            }
        }
    }
}
