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
 * Notifications class for exaport.
 */
class notifications {

    /**
     * Send notification when an item is created.
     *
     * @param \block_exaport\event\item_created $event
     */
    public function send_item_created_notification(\block_exaport\event\item_created $event) {
        global $DB, $CFG;

        $courseid = $event->courseid;
        $itemid = $event->objectid;
        $userid = $event->userid;

        // Get the item details
        $item = $DB->get_record('block_exaportitem', array('id' => $itemid));
        if (!$item) {
            return;
        }

        // Get the course
        $course = $DB->get_record('course', array('id' => $courseid));
        if (!$course) {
            return;
        }

        // Get the user who created the item
        $user = $DB->get_record('user', array('id' => $userid));
        if (!$user) {
            return;
        }

        // Get all teachers in the course
        $teachers = $this->get_course_teachers($courseid);

        if (empty($teachers)) {
            return;
        }

        // Prepare message data
        $messagedata = new \core\message\message();
        $messagedata->component = 'block_exaport';
        $messagedata->name = 'itemcreated';
        $messagedata->userfrom = \core_user::get_noreply_user();
        $messagedata->subject = get_string('emailsubject_itemcreated', 'block_exaport', $course->shortname);

        $messagetext = get_string('emailbody_itemcreated', 'block_exaport', array(
            'username' => fullname($user),
            'itemname' => $item->name,
            'coursename' => $course->fullname,
            'itemurl' => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid . '&id=' . $itemid . '&action=edit'
        ));

        $messagedata->fullmessage = $messagetext;
        $messagedata->fullmessageformat = FORMAT_PLAIN;
        $messagedata->fullmessagehtml = nl2br($messagetext);
        $messagedata->smallmessage = get_string('emailsmall_itemcreated', 'block_exaport');

        // Send to all teachers
        foreach ($teachers as $teacher) {
            $messagedata->userto = $teacher;
            message_send($messagedata);
        }
    }

    /**
     * Get all teachers in a course.
     *
     * @param int $courseid
     * @return array
     */
    private function get_course_teachers($courseid) {
        global $DB;

        $context = \context_course::instance($courseid);

        // Get users with teacher capabilities
        $teachers = get_enrolled_users($context, 'moodle/course:update');

        return $teachers;
    }
}

