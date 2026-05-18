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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for filtering items by category in the flat layout view.
 *
 * Uses the same autocomplete element as the item edit form to ensure
 * consistent appearance and behaviour.
 *
 * @package    block_exaport
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_exaport_flat_filter_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $categories = $this->_customdata['categories'] ?? [];

        // Category multi-select using the same autocomplete element as item.php.
        $mform->addElement('autocomplete', 'categoryids', get_string("category", "block_exaport"), $categories, ['multiple' => true]);
        $mform->setType('categoryids', PARAM_SEQUENCE);
        $mform->setDefault('categoryids', []);

        // Remove the standard form buttons — this form is used purely for JS-based filtering.
        // No submit button needed.
    }
}
