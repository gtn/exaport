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
     * Queue distribution task when user is enrolled in a course.
     * The task will execute after enrollment is complete.
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        self::queue_distribution_task($event->courseid, $event->relateduserid);
    }

    /**
     * Queue distribution task when user enrollment is updated.
     * This handles cases where someone's role is changed to student.
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event) {
        self::queue_distribution_task($event->courseid, $event->relateduserid);
    }

    /**
     * Queue an ad-hoc task to distribute categories and views to a user.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     */
    private static function queue_distribution_task($courseid, $userid) {
        // Create ad-hoc task.
        $task = new \block_exaport\task\distribute_to_user_task();

        // Set custom data.
        $task->set_custom_data([
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        // Queue the task.
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Handles course module created event.
     * Placeholder for saving block_exaport_assignment_option data when an assignment is created.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        if (($event->other['modulename'] ?? null) !== 'assign') {
            return;
        }
        // TODO: Save block_exaport_assignment_option value for the newly created assignment.
    }

    /**
     * Handles course module updated event.
     * Placeholder for saving block_exaport_assignment_option data when an assignment is updated.
     *
     * @param \core\event\course_module_updated $event
     * @return void
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        if (($event->other['modulename'] ?? null) !== 'assign') {
            return;
        }
        // TODO: Save block_exaport_assignment_option value for the updated assignment.
        // TODO: e.g. with this? optional_param('block_exaport_assignment_option', 0, PARAM_TEXT);
    }
}

