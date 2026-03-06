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
 * Handles course module form element injection for exaport block.
 */
class hook_listener {

    /**
     * Adds a dropdown field to the assignment activity settings form.
     *
     * Called via {@see block_exaport_coursemodule_standard_elements()} in lib.php,
     * which is invoked by Moodle's plugin callback mechanism
     * ({@see moodleform_mod::plugin_extend_coursemodule_standard_elements()}).
     *
     * @param \moodleform_mod $formwrapper The moodle quickforms wrapper object.
     * @param \MoodleQuickForm $mform The actual form object.
     * @return void
     */
    public static function coursemodule_standard_elements(\moodleform_mod $formwrapper, \MoodleQuickForm $mform): void {
        if (($formwrapper->get_current()->modulename ?? null) !== 'assign') {
            return;
        }

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
