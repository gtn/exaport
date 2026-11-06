<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Course template content becomes independent blocks in ordinary student views.
 *
 * @package block_exaport
 * @copyright 2026 GTN - Global Training Network GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_template_content_test extends \advanced_testcase {
    /**
     * Build representative content in multiple columns, including a title and rich text.
     *
     * @return array Block records
     */
    private function blocks(): array {
        return [
            (object)['type' => 'headline', 'text' => 'Learning goals', 'positionx' => 1, 'positiony' => 1],
            (object)['type' => 'text', 'block_title' => 'Tasks & reflection',
                'text' => '<p><strong>Describe</strong> your work.</p>',
                'positionx' => 1, 'positiony' => 2],
            (object)['type' => 'headline', 'text' => 'Evidence', 'positionx' => 3, 'positiony' => 1],
        ];
    }

    /**
     * Build an editable course template.
     *
     * @return array Course record and template ID
     */
    private function template(): array {
        $course = $this->getDataGenerator()->create_course();
        $id = view_template::add_view($course->id, 'Portfolio');
        view_template::update_layout($id, 5);
        view_template::save_blocks($id, $this->blocks());
        return [$course, $id];
    }

    public function test_content_roundtrip_and_layout_changes_preserve_all_blocks(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $id] = $this->template();
        $blocks = view_template::get_blocks($id);
        $this->assertSame(['headline', 'text', 'headline'], array_column($blocks, 'type'));
        $this->assertSame('Tasks & reflection', $blocks[1]->block_title);
        $this->assertStringContainsString('<strong>Describe</strong>', $blocks[1]->text);
        $this->assertSame(0, $DB->count_records('block_exaportview'));
        $this->assertSame(0, $DB->count_records('block_exaportviewblock'));

        view_template::update_layout($id, 1);
        $blocks = view_template::get_blocks($id);
        $this->assertSame(
            ['Learning goals', '<p><strong>Describe</strong> your work.</p>', 'Evidence'],
            array_column($blocks, 'text')
        );
        $this->assertEquals([1, 1, 1], array_column($blocks, 'positionx'));
        $this->assertEquals([1, 2, 3], array_column($blocks, 'positiony'));
    }

    public function test_manual_and_enrolment_distribution_make_independent_copies(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $id] = $this->template();
        $generator = $this->getDataGenerator();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->assertSame(1, view_distributor::distribute_to_course($course->id)['created']);
        $viewid = $DB->get_field('block_exaportview', 'id', ['userid' => $student->id], MUST_EXIST);
        $blocks = array_values($DB->get_records('block_exaportviewblock', ['viewid' => $viewid], 'positionx, positiony'));
        $this->assertCount(3, $blocks);
        $this->assertSame('Tasks & reflection', $blocks[1]->block_title);
        $this->assertEquals(3, $blocks[2]->positionx);
        $this->assertNull($blocks[1]->itemid);
        $DB->set_field('block_exaportviewblock', 'text', 'Student response', ['id' => $blocks[1]->id]);
        $this->assertStringContainsString('Describe', view_template::get_blocks($id)[1]->text);

        $replacement = $this->blocks();
        $replacement[0]->text = 'Updated template';
        view_template::save_blocks($id, $replacement);
        view_distributor::update_settings($course->id, true);
        $newstudent = $generator->create_user();
        $generator->enrol_user($newstudent->id, $course->id, 'student');
        ob_start();
        try {
            $this->runAdhocTasks('block_exaport\\task\\distribute_to_user_task');
        } finally {
            ob_end_clean();
        }
        $newviewid = $DB->get_field('block_exaportview', 'id', ['userid' => $newstudent->id], MUST_EXIST);
        $this->assertSame('Updated template', $DB->get_field(
            'block_exaportviewblock',
            'text',
            ['viewid' => $newviewid, 'type' => 'headline', 'positionx' => 1]
        ));
        $this->assertSame(2, view_distributor::distribute_to_course($course->id)['skipped']);
        $this->assertSame('Student response', $DB->get_field('block_exaportviewblock', 'text', ['id' => $blocks[1]->id]));

        view_template::remove_view($id);
        $this->assertSame(0, $DB->count_records(view_template::BLOCKS_TABLE));
        $this->assertSame(6, $DB->count_records('block_exaportviewblock'));
    }

    public function test_invalid_blocks_leave_stored_content_unchanged(): void {
        $this->resetAfterTest();
        [$course, $id] = $this->template();
        $before = view_template::get_blocks($id);
        foreach (
            [['type' => 'item', 'itemid' => 99], ['type' => 'text', 'positionx' => -1],
                ['type' => 'text', 'block_title' => str_repeat('x', 1001)],
                ['type' => 'text', 'text' => '<img src="@@PLUGINFILE@@/private.png">']] as $invalid
        ) {
            try {
                view_template::save_blocks($id, array_merge($this->blocks(), [$invalid]));
                $this->fail('Invalid template content must be rejected before replacing any blocks.');
            } catch (\moodle_exception $exception) {
                $this->assertEquals($before, view_template::get_blocks($id));
            }
        }
    }

    public function test_saving_ignores_foreign_ids_and_cleans_html(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $id] = $this->template();
        view_template::save_blocks($id, [[
            'type' => 'text', 'text' => '<p onclick="evil()">Hello</p><script>evil()</script>',
            'id' => 9999999, 'templateid' => 9999999, 'viewid' => 9999999, 'itemid' => 9999999,
            'print_text' => '<script>evil()</script>',
        ]]);
        $block = view_template::get_blocks($id)[0];
        $this->assertEquals($id, $block->templateid);
        $this->assertNotEquals(9999999, $block->id);
        $this->assertStringNotContainsString('<script', $block->text);
        $this->assertStringNotContainsString('onclick', $block->text);
        $this->assertSame(0, $DB->count_records('block_exaportviewblock'));
    }

    public function test_replacing_template_or_deleting_course_cleans_its_blocks(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $id] = $this->template();
        set_config('starter_view_templates', json_encode([
            ['name' => 'Empty starter', 'views' => [['name' => 'Replacement']]],
        ]), 'block_exaport');
        $this->assertTrue(view_template::load_starter_template($course->id, 'Empty starter'));
        $this->assertSame(0, $DB->count_records(view_template::BLOCKS_TABLE));
        $replacement = view_template::get_course_template($course->id)[0];
        view_template::save_blocks($replacement->id, $this->blocks());
        require_once($CFG->dirroot . '/course/lib.php');
        delete_course($course, false);
        $this->assertSame(0, $DB->count_records(view_template::BLOCKS_TABLE));
        $this->assertSame(0, $DB->count_records('block_exaport_view_templ', ['courseid' => $course->id]));
    }

    public function test_upgrade_from_layout_only_version_adds_empty_block_table(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $id = view_template::add_view($course->id, 'Existing template');
        $before = $DB->get_record('block_exaport_view_templ', ['id' => $id]);
        $DB->get_manager()->drop_table(new \xmldb_table(view_template::BLOCKS_TABLE));
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        set_config('version', 2026110501, 'block_exaport');
        $this->assertTrue(\xmldb_block_exaport_upgrade(2026110501));
        $this->assertSame([], view_template::get_blocks($id));
        $this->assertEquals($before, $DB->get_record('block_exaport_view_templ', ['id' => $id]));
    }

    public function test_block_fragment_reuses_the_form_without_saving_the_draft(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('block_exaport_enable_category_distribution', 1);
        [$course, $id] = $this->template();
        require_once($CFG->libdir . '/external/externallib.php');
        $args = [
            ['name' => 'courseid', 'value' => (string)$course->id],
            ['name' => 'templateid', 'value' => (string)$id],
            ['name' => 'type_block', 'value' => 'headline'],
            ['name' => 'block', 'value' => json_encode(['text' => 'Draft heading'])],
        ];
        $fragment = \core_external::get_fragment(
            'block_exaport',
            'view_template_blockedit',
            \context_course::instance($course->id)->id,
            $args
        );
        $this->assertStringContainsString('value="Draft heading"', $fragment['html']);
        $this->assertStringNotContainsString('submit_block_and_notify', $fragment['html']);
        $this->assertSame('Learning goals', view_template::get_blocks($id)[0]->text);

        $this->expectException(\invalid_parameter_exception::class);
        \block_exaport_output_fragment_view_template_blockedit([
            'courseid' => $course->id, 'context' => \context_system::instance(),
        ]);
    }

    public function test_student_cannot_load_a_template_block_dialog(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('block_exaport_enable_category_distribution', 1);
        [$course, $id] = $this->template();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        require_once($CFG->libdir . '/external/externallib.php');
        $this->expectException(\required_capability_exception::class);
        \core_external::get_fragment(
            'block_exaport',
            'view_template_blockedit',
            \context_course::instance($course->id)->id,
            [
                ['name' => 'courseid', 'value' => (string)$course->id],
                ['name' => 'templateid', 'value' => (string)$id],
                ['name' => 'type_block', 'value' => 'headline'],
            ]
        );
    }
}
