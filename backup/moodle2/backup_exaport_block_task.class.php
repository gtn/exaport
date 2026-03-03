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

require_once($CFG->dirroot . '/blocks/exaport/backup/moodle2/backup_exaport_stepslib.php');

/**
 * Block backup task for exaport
 */
class backup_exaport_block_task extends backup_block_task {

    /**
     * Define particular settings for this block
     */
    protected function define_my_settings() {
        // No specific settings for exaport block.
    }

    /**
     * Define the backup steps for this block
     */
    protected function define_my_steps() {
        // Add the exaport structure step.
        $this->add_step(new backup_exaport_block_structure_step('exaport_structure', 'exaport.xml'));
    }

    /**
     * Code to prepare file area for backup
     */
    public function get_fileareas() {
        return array(); // No file areas to backup for these tables.
    }

    /**
     * Encode content links for this block
     */
    public function get_configdata_encoded_attributes() {
        return array(); // No configdata attributes to encode.
    }

    /**
     * Define encoding rules for links
     */
    static public function encode_content_links($content) {
        return $content; // No special encoding needed.
    }
}
