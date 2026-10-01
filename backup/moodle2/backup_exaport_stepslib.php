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
            'userid', 'type', 'categoryid', 'name', 'url', 'intro', 'attachment', 'timecreated',
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

        $item->set_source_table('block_exaportitem', array('courseid' => backup::VAR_COURSEID));
        $contentblock->set_source_table('block_exaportitemblock', array('itemid' => backup::VAR_PARENTID));

        $item->annotate_ids('user', 'userid');
        // item_file is retained here solely so pre-migration data can be recovered safely by the
        // restore compatibility path. Normal new backups contain only the two structured areas.
        $item->annotate_files('block_exaport', 'item_file', 'id');
        $contentblock->annotate_files('block_exaport', 'item_content_file', 'id');
        $contentblock->annotate_files('block_exaport', 'item_content_text', 'id');

        // Return the root element.
        return $this->prepare_block_structure($exaport);
    }
}
