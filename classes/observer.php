<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Observer for exaport events.
 */
class observer {

    /**
     * Handle user enrolment event to auto-distribute categories and views
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        $courseid = $event->courseid;
        $userid = $event->relateduserid;

        // Check if auto-distribution is enabled for categories.
        $category_settings = category_distributor::get_settings($courseid);
        if ($category_settings->auto_distribute) {
            // Get course template.
            $template = category_template::get_course_template($courseid);
            if (!empty($template)) {
                // Distribute to the newly enrolled user (pass courseid for teacher sharing).
                category_distributor::distribute_to_user($userid, $template, 0, $courseid);
            }
        }

        // Check if auto-distribution is enabled for views.
        $view_settings = view_distributor::get_settings($courseid);
        if ($view_settings->auto_distribute_views) {
            // Get course view template.
            $view_template = view_template::get_course_template($courseid);
            if (!empty($view_template)) {
                // Distribute views to the newly enrolled user.
                view_distributor::distribute_to_user($userid, $view_template, $courseid);
            }
        }
    }
}

