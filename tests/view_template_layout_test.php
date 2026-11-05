<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/** Layout defaults and distribution use the ordinary, editable portfolio views. */
final class view_template_layout_test extends \advanced_testcase {
    /** Default and all layouts offered by the existing view editor. */
    public static function layout_cases(): array {
        $cases = ['unset' => [null, 2], 'default option' => [0, 2]];
        foreach (range(1, 10) as $layout) {
            $cases['layout ' . $layout] = [$layout, $layout];
        }
        return $cases;
    }

    /** @dataProvider layout_cases */
    #[\PHPUnit\Framework\Attributes\DataProvider('layout_cases')]
    public function test_distribution_uses_optional_template_layout(?int $layout, int $expected): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        view_template::add_view($course->id, 'Portfolio', '', false, $layout);

        $result = view_distributor::distribute_to_user($student->id, view_template::get_course_template($course->id));

        $this->assertSame(['created' => 1, 'skipped' => 0], $result);
        $this->assertEquals($expected, $DB->get_field('block_exaportview', 'layout', ['userid' => $student->id]));
    }

    public function test_manual_and_enrolment_distribution_preserve_student_edits(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $templateid = view_template::add_view($course->id, 'Portfolio');
        view_template::update_layout($templateid, 5);
        $generator->enrol_user($student->id, $course->id, 'student');

        $result = view_distributor::distribute_to_course($course->id);
        $this->assertSame(1, $result['created']);
        $view = $DB->get_record('block_exaportview', ['userid' => $student->id], '*', MUST_EXIST);
        $this->assertEquals(5, $view->layout);
        $view->layout = 1;
        $view->description = 'Student content';
        $DB->update_record('block_exaportview', $view);

        view_distributor::update_settings($course->id, true);
        $newstudent = $generator->create_user();
        $generator->enrol_user($newstudent->id, $course->id, 'student');
        ob_start();
        try {
            $this->runAdhocTasks('block_exaport\\task\\distribute_to_user_task');
        } finally {
            ob_end_clean();
        }
        $this->assertEquals(5, $DB->get_field('block_exaportview', 'layout', ['userid' => $newstudent->id]));

        view_template::update_layout($templateid, 10);
        $result = view_distributor::distribute_to_course($course->id);
        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertEquals($view, $DB->get_record('block_exaportview', ['id' => $view->id]));

        view_template::update_layout($templateid, 0);
        $this->assertNull($DB->get_field('block_exaport_view_templ', 'layout', ['id' => $templateid]));
    }

    public function test_starter_templates_support_optional_layout_and_reject_invalid_layout_before_replacing(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        set_config('starter_view_templates', json_encode([
            ['name' => 'Example', 'views' => [
                ['name' => 'Default'], ['name' => 'Three columns', 'layout' => 5],
            ]],
            ['name' => 'Invalid', 'views' => [['name' => 'Invalid', 'layout' => 11]]],
        ]), 'block_exaport');
        $this->assertTrue(view_template::load_starter_template($course->id, 'Example'));
        $views = view_template::get_course_template($course->id);
        $this->assertNull($views[0]->layout);
        $this->assertEquals(5, $views[1]->layout);

        try {
            view_template::load_starter_template($course->id, 'Invalid');
            $this->fail('Invalid layouts must be rejected.');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertEquals($views, view_template::get_course_template($course->id));
        }
    }

    public function test_upgrade_preserves_existing_template_with_default_layout(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        $course = $this->getDataGenerator()->create_course();
        $templateid = view_template::add_view($course->id, 'Existing template');
        $DB->get_manager()->drop_field(new \xmldb_table('block_exaport_view_templ'), new \xmldb_field('layout'));
        set_config('version', 2026102900, 'block_exaport');

        $this->assertTrue(\xmldb_block_exaport_upgrade(2026102900));

        $view = $DB->get_record('block_exaport_view_templ', ['id' => $templateid], '*', MUST_EXIST);
        $this->assertSame('Existing template', $view->name);
        $this->assertNull($view->layout);
    }
}
