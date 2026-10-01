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

    /** @var int[] Items created by this restore, keyed by their new id. */
    private $restoreditems = array();

    /** @var int[] Source user context IDs present in this backup. */
    private $usercontexts = array();

    /** @var int[] Source user context ID for each source item ID. */
    private $itemcontexts = array();

    /**
     * Define the structure to be restored
     */
    protected function define_structure() {

        $paths = array();

        // Define the paths for the data we want to restore.
        $paths[] = new restore_path_element('course_template', '/block/block_exaport/course_templates/course_template');
        $paths[] = new restore_path_element('view_template', '/block/block_exaport/view_templates/view_template');
        $paths[] = new restore_path_element('distribution_setting', '/block/block_exaport/distribution_settings/distribution_setting');
        $paths[] = new restore_path_element('item', '/block/block_exaport/items/item');
        $paths[] = new restore_path_element('content_block',
            '/block/block_exaport/items/item/content_blocks/content_block');

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
     * Restore a portfolio item, including the legacy fields needed to import old backups.
     */
    protected function process_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = (int)$data->id;
        // usercontextid was introduced with explicit support for user-context files. Keep older
        // archives restorable; they can still contain records and legacy URLs, but their standard
        // block-context annotation could never have included these user-context files.
        $oldusercontextid = !empty($data->usercontextid) ? (int)$data->usercontextid : 0;
        unset($data->usercontextid);
        $data->courseid = $this->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->id = (int)$DB->insert_record('block_exaportitem', $data);
        // File mappings are joined to backup files by their source context. Supplying that context
        // as the mapping's parent item is therefore required for add_related_files() to find files
        // which were explicitly annotated outside this block task's own context.
        $this->set_mapping('exaport_item', $oldid, $data->id, false, null,
            $oldusercontextid ?: null);
        // Core's file restoration needs an explicit context mapping because this block task's
        // natural context is the block, while every annotated Exaport file has a user context.
        if ($oldusercontextid) {
            $newusercontextid = context_user::instance((int)$data->userid)->id;
            $this->set_mapping('context', $oldusercontextid, $newusercontextid);
            $this->usercontexts[$oldusercontextid] = $oldusercontextid;
            $this->itemcontexts[$oldid] = $oldusercontextid;
        }
        $this->restoreditems[$data->id] = $data->id;
    }

    /**
     * Restore one structured content block and its two block-keyed file areas.
     */
    protected function process_content_block($data) {
        global $DB;

        $data = (object)$data;
        $oldid = (int)$data->id;
        $olditemid = (int)$data->itemid;
        $data->itemid = $this->get_mappingid('exaport_item', $olditemid);
        $data->id = (int)$DB->insert_record('block_exaportitemblock', $data);
        $this->set_mapping('exaport_item_content_block', $oldid, $data->id, false, null,
            $this->itemcontexts[$olditemid] ?? null);
    }

    /**
     * Conversion seam kept separate so restore tests can inject a copy failure.
     */
    protected function migrate_legacy_item_content(stdClass $item): void {
        block_exaport_migrate_legacy_item_content($item);
    }

    /**
     * Actions to be executed after the restore
     */
    protected function after_execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/blocks/exaport/db/upgradelib.php');
        // Restore files only after every item/block mapping is known. Old archives have item_file;
        // current archives have the two block-keyed structured areas.
        foreach ($this->usercontexts as $oldusercontextid) {
            $this->add_related_files('block_exaport', 'item_file', 'exaport_item', $oldusercontextid);
            $this->add_related_files(
                'block_exaport', 'item_content_file', 'exaport_item_content_block', $oldusercontextid
            );
            $this->add_related_files(
                'block_exaport', 'item_content_text', 'exaport_item_content_block', $oldusercontextid
            );
        }
        foreach ($this->restoreditems as $itemid) {
            $item = $DB->get_record('block_exaportitem', array('id' => $itemid), '*', MUST_EXIST);
            $this->migrate_legacy_item_content($item);
        }
    }
}
