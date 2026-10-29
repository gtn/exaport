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

    /** @var stdClass|null First original root inserted by this step, ordered as in the template editor. */
    private ?stdClass $copiedroot = null;

    /** @var bool Course opt-in from this backup, not from pre-existing destination settings. */
    private bool $renamerootoncopy = false;

    /** @var int[] Newly inserted child IDs whose original parents have not been restored yet. */
    private array $pendingparents = [];

    /**
     * Define the structure to be restored
     */
    protected function define_structure() {

        $paths = array();

        // Define the paths for the data we want to restore.
        $paths[] = new restore_path_element('course_template', '/block/block_exaport/course_templates/course_template');
        $paths[] = new restore_path_element('view_template', '/block/block_exaport/view_templates/view_template');
        $paths[] = new restore_path_element('distribution_setting', '/block/block_exaport/distribution_settings/distribution_setting');

        return $paths;
    }

    /**
     * Process course template category
     */
    protected function process_course_template($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $isroot = (int)$data->pid === 0;
        $oldparentid = (int)$data->pid;

        // Update the courseid to the new course.
        $data->courseid = $this->get_courseid();

        // Handle parent id mapping - if pid > 0, map it to the new parent id.
        if ($data->pid > 0) {
            $data->pid = $this->get_mappingid('course_template', $data->pid);
            // A moved category may precede its newer parent in the backup. Resolve it after parsing.
            if (!$data->pid) {
                $data->pid = 0;
            }
        }

        // Insert the record.
        $newid = $DB->insert_record('block_exaport_course_templ', $data);
        if ($oldparentid > 0 && !$data->pid) {
            $this->pendingparents[$newid] = $oldparentid;
        }

        // Track only newly inserted ORIGINAL roots. A child with an unresolved parent mapping
        // must not become the rename target, nor may an existing destination template be chosen.
        if ($isroot && ($this->copiedroot === null ||
                [$data->sortorder, $oldid] < [$this->copiedroot->sortorder, $this->copiedroot->oldid])) {
            $this->copiedroot = (object)['id' => $newid, 'sortorder' => $data->sortorder, 'oldid' => $oldid];
        }

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
        // Older archives have no opt-in. They must retain their original template names.
        $data->rename_root_on_copy = !empty($data->rename_root_on_copy) ? 1 : 0;
        $this->renamerootoncopy = (bool)$data->rename_root_on_copy;

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

    /** Resolve moved children's parents once every source template ID has a destination mapping. */
    protected function after_execute() {
        global $DB;

        foreach ($this->pendingparents as $childid => $oldparentid) {
            $parentid = $this->get_mappingid('course_template', $oldparentid);
            if ($parentid) {
                $DB->set_field('block_exaport_course_templ', 'pid', $parentid,
                    ['id' => $childid, 'courseid' => $this->get_courseid()]);
            }
        }
    }

    /**
     * Name the newly copied template once, after the destination course name is final.
     * Personal portfolio categories and the source template are deliberately outside this path.
     */
    protected function after_restore() {
        global $DB;

        if ($this->copiedroot === null || !$this->renamerootoncopy ||
                !get_config('block_exaport', 'enable_copy_root_naming')) {
            return;
        }
        $courseid = $this->get_courseid();
        $fullname = $DB->get_field('course', 'fullname', ['id' => $courseid], MUST_EXIST);
        $pattern = get_config('block_exaport', 'copied_template_root_name');
        if ($pattern === false || trim($pattern) === '') {
            $pattern = 'Ausbildungsdokumente {coursefullname}';
        }
        $name = str_replace('{coursefullname}', $fullname, $pattern);
        // Template and personal category names both have an existing 255-character limit.
        $name = core_text::substr($name, 0, 255);
        $DB->set_field('block_exaport_course_templ', 'name', $name,
            ['id' => $this->copiedroot->id, 'courseid' => $courseid]);
    }
}
