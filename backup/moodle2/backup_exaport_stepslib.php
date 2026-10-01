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
 * Define the backup structure for the exaport block
 */
class backup_exaport_block_structure_step extends backup_block_structure_step {

    /**
     * Define the structure of the backup
     */
    protected function define_structure() {
        global $DB;

        // Get the block instance.
        $block = $this->get_task()->get_blockid();
        $courseid = $this->get_task()->get_courseid();

        // Define the root element.
        $exaport = new backup_nested_element('block_exaport');

        // Define course template categories (hierarchical structure).
        $course_templates = new backup_nested_element('course_templates');
        $course_template = new backup_nested_element('course_template', array('id'), array(
            'courseid', 'pid', 'name', 'sortorder', 'share_to_teachers', 'timemodified'
        ));

        // Define view templates.
        $view_templates = new backup_nested_element('view_templates');
        $view_template = new backup_nested_element('view_template', array('id'), array(
            'courseid', 'name', 'description', 'sortorder', 'share_to_teachers', 'timemodified'
        ));

        // Define distribution settings.
        $dist_settings = new backup_nested_element('distribution_settings');
        $dist_setting = new backup_nested_element('distribution_setting', array('id'), array(
            'courseid', 'auto_distribute', 'auto_distribute_views', 'timemodified'
        ));

        // Portfolio items belong to a course too. Keep their content blocks below the item so the
        // restore step can map both block records and the block-keyed file areas without guessing.
        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', array('id'), array(
            'userid', 'usercontextid', 'type', 'categoryid', 'name', 'url', 'intro', 'attachment', 'timecreated',
            'timemodified', 'courseid', 'shareall', 'externaccess', 'externcomment', 'sortorder',
            'isoez', 'fileurl', 'beispiel_url', 'exampid', 'langid', 'beispiel_angabe', 'source',
            'sourceid', 'iseditable', 'example_url', 'parentid', 'project_description',
            'project_process', 'project_result'
        ));
        $contentblocks = new backup_nested_element('content_blocks');
        $contentblock = new backup_nested_element('content_block', array('id'), array(
            'itemid', 'type', 'sortorder', 'title', 'content', 'contentformat', 'url',
            'timecreated', 'timemodified'
        ));

        // Build the tree structure.
        $exaport->add_child($course_templates);
        $course_templates->add_child($course_template);

        $exaport->add_child($view_templates);
        $view_templates->add_child($view_template);

        $exaport->add_child($dist_settings);
        $dist_settings->add_child($dist_setting);

        $exaport->add_child($items);
        $items->add_child($item);
        $item->add_child($contentblocks);
        $contentblocks->add_child($contentblock);

        // Define data sources.
        $course_template->set_source_table('block_exaport_course_templ', array('courseid' => backup::VAR_COURSEID));
        $view_template->set_source_table('block_exaport_view_templ', array('courseid' => backup::VAR_COURSEID));
        $dist_setting->set_source_table('block_exaport_templ_dist', array('courseid' => backup::VAR_COURSEID));

        // The files belonging to portfolio items deliberately live in the owner's user context.
        // Keep that source context in the XML so restore can direct the explicitly annotated files
        // to the mapped owner's user context rather than to the restored block context.
        $item->set_source_sql(
            'SELECT i.*, ctx.id AS usercontextid
               FROM {block_exaportitem} i
               JOIN {context} ctx ON ctx.contextlevel = :userlevel AND ctx.instanceid = i.userid
              WHERE i.courseid = :courseid',
            // Positive scalar source parameters are otherwise interpreted as element paths.
            array('userlevel' => array('sqlparam' => CONTEXT_USER), 'courseid' => backup::VAR_COURSEID)
        );
        $contentblock->set_source_table('block_exaportitemblock', array('itemid' => backup::VAR_PARENTID));

        $item->annotate_ids('user', 'userid');

        // backup_nested_element::annotate_files() always searches the task (block) context. Exaport
        // files are intentionally held in user contexts, so explicitly add them to Moodle's normal
        // backup file pool using their real contexts. This preserves deduplication and avoids either
        // copying data into a course context or embedding file bytes in exaport.xml.
        $backupid = $this->get_task()->get_backupid();
        $items = $DB->get_records('block_exaportitem', array('courseid' => $courseid), '', 'id, userid');
        foreach ($items as $itemrecord) {
            $usercontextid = context_user::instance((int)$itemrecord->userid)->id;
            backup_structure_dbops::annotate_files(
                $backupid, $usercontextid, 'block_exaport', 'item_file', (int)$itemrecord->id
            );
            $blocks = $DB->get_records('block_exaportitemblock', array('itemid' => $itemrecord->id), '', 'id');
            foreach ($blocks as $blockrecord) {
                foreach (array('item_content_file', 'item_content_text') as $filearea) {
                    backup_structure_dbops::annotate_files(
                        $backupid, $usercontextid, 'block_exaport', $filearea, (int)$blockrecord->id
                    );
                }
            }
        }

        // Return the root element.
        return $this->prepare_block_structure($exaport);
    }
}
