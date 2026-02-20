<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/category_distribution.php');

/**
 * Observer for exaport events.
 */
class observer {

    /**
     * Handle user enrolment event to auto-distribute categories
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        global $DB;

        $courseid = $event->courseid;
        $userid = $event->relateduserid;

        // Check if auto-distribution is enabled for this course.
        $settings = $DB->get_record('block_exaport_templ_dist', array('courseid' => $courseid));
        if (!$settings || !$settings->auto_distribute) {
            return;
        }

        // Get course template.
        $template = block_exaport_get_course_template($courseid);
        if (empty($template)) {
            return;
        }

        // Distribute to the newly enrolled user.
        block_exaport_distribute_to_user($userid, $template);
    }
}

