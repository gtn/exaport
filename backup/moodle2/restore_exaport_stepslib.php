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

/**
 * Define the restore structure for the exaport block
 */
class restore_exaport_block_structure_step extends restore_structure_step {

    /**
     * Define the structure to be restored
     */
    protected function define_structure() {

        $paths = array();

        // Define the paths for the data we want to restore.
        $paths[] = new restore_path_element('course_template', '/block_exaport/course_templates/course_template');
        $paths[] = new restore_path_element('view_template', '/block_exaport/view_templates/view_template');
        $paths[] = new restore_path_element('distribution_setting', '/block_exaport/distribution_settings/distribution_setting');

        return $paths;
    }

    /**
     * Process course template category
     */
    protected function process_course_template($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Update the courseid to the new course.
        $data->courseid = $this->get_courseid();

        // Handle parent id mapping - if pid > 0, map it to the new parent id.
        if ($data->pid > 0) {
            $data->pid = $this->get_mappingid('course_template', $data->pid);
            // If parent mapping not found yet, set to 0 (root).
            if (!$data->pid) {
                $data->pid = 0;
            }
        }

        // Insert the record.
        $newid = $DB->insert_record('block_exaport_course_templ', $data);

        // Save the mapping for child categories to reference.
        $this->set_mapping('course_template', $oldid, $newid);
    }

    /**
     * Process view template
     */
    protected function process_view_template($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Update the courseid to the new course.
        $data->courseid = $this->get_courseid();

        // Insert the record.
        $newid = $DB->insert_record('block_exaport_view_templ', $data);

        // Save the mapping (though not used by other tables currently).
        $this->set_mapping('view_template', $oldid, $newid);
    }

    /**
     * Process distribution settings
     */
    protected function process_distribution_setting($data) {
        global $DB;

        $data = (object)$data;

        // Update the courseid to the new course.
        $data->courseid = $this->get_courseid();

        // Check if settings already exist for this course (should not, but be safe).
        $existing = $DB->get_record('block_exaport_templ_dist', array('courseid' => $data->courseid));

        if ($existing) {
            // Update existing record.
            $data->id = $existing->id;
            $DB->update_record('block_exaport_templ_dist', $data);
        } else {
            // Insert new record.
            $DB->insert_record('block_exaport_templ_dist', $data);
        }
    }

    /**
     * Actions to be executed after the restore
     */
    protected function after_execute() {
        // No additional processing needed after restore.
    }
}
