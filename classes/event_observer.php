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
 * Event observer for exaport block.
 */
class event_observer {

    /**
     * Handle course module created event.
     *
     * Placeholder for saving block_exaport_assignment_option data when an assignment is created.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        // TODO: Save block_exaport_assignment_option value for the newly created assignment.
    }

    /**
     * Handle course module updated event.
     *
     * Placeholder for saving block_exaport_assignment_option data when an assignment is updated.
     *
     * @param \core\event\course_module_updated $event
     * @return void
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        // TODO: Save block_exaport_assignment_option value for the updated assignment.
    }
}
