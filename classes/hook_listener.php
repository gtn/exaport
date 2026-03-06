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
 * Hook listener for exaport block.
 */
class hook_listener {

    /**
     * Adds custom fields to the assignment activity settings form.
     *
     * @param \core\hook\form_coursemodule_standard_elements $hook
     * @return void
     */
    public static function form_coursemodule_standard_elements(
        \core\hook\form_coursemodule_standard_elements $hook
    ): void {
        if ($hook->get_cmtype() !== 'assign') {
            return;
        }

        $mform = $hook->get_form();

        $options = [
            'testvalue1' => get_string('testvalue1', 'block_exaport'),
            'testvalue2' => get_string('testvalue2', 'block_exaport'),
            'testvalue3' => get_string('testvalue3', 'block_exaport'),
        ];

        $mform->addElement(
            'select',
            'block_exaport_assignment_option',
            get_string('assignment_option', 'block_exaport'),
            $options
        );
    }
}
