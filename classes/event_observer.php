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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for exaport assignment settings.
 */
class event_observer {

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
    }
}
