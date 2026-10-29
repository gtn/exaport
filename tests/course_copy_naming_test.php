<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Course copies name their new template without editing existing portfolio categories.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_copy_naming_test extends \advanced_testcase {
    /** Real restore targets and the two independent opt-ins. */
    public static function naming_cases(): array {
        return [
            'enabled new course' => [true, true, false, false, null, false],
            'enabled existing course' => [true, true, true, false, null, false],
            'admin disabled' => [false, true, false, false, null, false],
            'course disabled' => [true, false, false, false, null, false],
            'legacy backup' => [true, true, false, true, null, false],
            'custom pattern' => [true, true, false, false, 'Unterlagen: {coursefullname}', false],
            'empty pattern uses default' => [true, true, false, false, '', false],
            'empty template' => [true, true, false, false, null, true],
        ];
    }

    /** Back up and restore through Moodle's actual course restore plan. */
    private function copy_course(int $sourceid, int $destinationid, bool $existing = false,
            bool $legacy = false, string $fullname = 'Pflege 2027 – Gruppe Ä'): void {
        $adminid = (int)get_admin()->id;
        $backup = new \backup_controller(\backup::TYPE_1COURSE, $sourceid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $adminid);
        $backup->get_plan()->get_setting('users')->set_value(false);
        $backup->execute_plan();
        $backupfile = $backup->get_results()['backup_destination'];
        $backup->destroy();
        $tempname = \restore_controller::get_tempdir_name($sourceid, $adminid);
        $temppath = make_backup_temp_directory($tempname);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($backupfile, $temppath);
        if ($legacy) {
            foreach (glob($temppath . '/course/blocks/exaport_*/exaport.xml') as $xmlpath) {
                $xml = file_get_contents($xmlpath);
                $xml = preg_replace('~<rename_root_on_copy>.*?</rename_root_on_copy>~s', '', $xml);
                file_put_contents($xmlpath, $xml);
            }
        }
        $restore = new \restore_controller($tempname, $destinationid, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $adminid, $existing ? \backup::TARGET_EXISTING_ADDING : \backup::TARGET_NEW_COURSE);
        if (!$existing) {
            // The database initially contains a placeholder. The rename must use the FINAL restore name.
            $restore->get_plan()->get_setting('course_fullname')->set_value($fullname);
            $restore->get_plan()->get_setting('course_shortname')->set_value('copy-' . $destinationid);
        }
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();
        $backupfile->delete();
    }

    /** Build deliberately out-of-order roots, children and equal-sort roots. */
    private function template(int $courseid): array {
        global $DB;
        $nodes = [];
        foreach ([
            ['Last root', 0, 20],
            ['Last root child', 'Last root', 0],
            ['First root', 0, 3],
            ['First root child', 'First root', 4],
            ['Grandchild', 'First root child', 5],
            ['Tied root', 0, 3],
        ] as [$name, $parent, $order]) {
            $nodes[$name] = $DB->insert_record('block_exaport_course_templ', [
                'courseid' => $courseid, 'name' => $name, 'pid' => $parent ? $nodes[$parent] : 0,
                'sortorder' => $order, 'share_to_teachers' => 1, 'timemodified' => 123,
            ]);
        }
        // A user may move an older category beneath a parent created later. Backup insertion
        // order must not turn that moved child into a new root in the restored hierarchy.
        $DB->set_field('block_exaport_course_templ', 'pid', $nodes['First root child'],
            ['id' => $nodes['Last root child']]);
        return $nodes;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('naming_cases')]
    public function test_only_first_new_template_root_is_renamed(bool $global, bool $courseoptin,
            bool $existing, bool $legacy, ?string $pattern, bool $empty): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enable_copy_root_naming', $global, 'block_exaport');
        if ($pattern !== null) {
            set_config('copied_template_root_name', $pattern, 'block_exaport');
        }
        $generator = $this->getDataGenerator();
        $source = $generator->create_course(['fullname' => 'Unchanged source template']);
        $destination = $generator->create_course(['fullname' => 'Pflege 2027 – Gruppe Ä']);
        foreach ([1, 2] as $unused) {
            $generator->create_block('exaport', ['parentcontextid' => \context_course::instance($source->id)->id]);
        }
        $nodes = $empty ? [] : $this->template($source->id);
        $DB->insert_record('block_exaport_templ_dist', ['courseid' => $source->id,
            'auto_distribute' => 1, 'auto_distribute_views' => 1, 'rename_root_on_copy' => (int)$courseoptin]);
        $originals = $DB->get_records('block_exaport_course_templ', ['courseid' => $source->id], 'id');
        $existingroot = $DB->insert_record('block_exaport_course_templ', ['courseid' => $destination->id,
            'pid' => 0, 'name' => 'Existing destination root', 'sortorder' => -1, 'timemodified' => 124]);
        $existingbefore = $DB->get_record('block_exaport_course_templ', ['id' => $existingroot]);
        $DB->insert_record('block_exaport_templ_dist', ['courseid' => $destination->id, 'rename_root_on_copy' => 1]);
        $owner = $generator->create_user();
        // A personal category bearing the destination course id must still NEVER be renamed.
        $personal = $DB->insert_record('block_exaportcate', ['userid' => $owner->id,
            'courseid' => $destination->id, 'pid' => 0, 'name' => 'Personal root', 'timemodified' => 321]);
        $DB->insert_record('block_exaportcate', ['userid' => $owner->id,
            'courseid' => $source->id, 'pid' => $personal, 'name' => 'Personal child', 'timemodified' => 322]);
        $DB->insert_record('block_exaportcatshar', ['catid' => $personal, 'userid' => get_admin()->id, 'notify' => 1]);
        $personalbefore = $DB->get_records('block_exaportcate', null, 'id');
        $sharesbefore = $DB->get_records('block_exaportcatshar', null, 'id');

        $this->copy_course($source->id, $destination->id, $existing, $legacy);

        $this->assertEquals($originals, $DB->get_records('block_exaport_course_templ', ['courseid' => $source->id], 'id'));
        $this->assertEquals($existingbefore, $DB->get_record('block_exaport_course_templ', ['id' => $existingroot]));
        $this->assertEquals($personalbefore, $DB->get_records('block_exaportcate', null, 'id'));
        $this->assertEquals($sharesbefore, $DB->get_records('block_exaportcatshar', null, 'id'));
        $settings = category_distributor::get_settings($destination->id);
        $this->assertEquals($courseoptin && !$legacy, $settings->rename_root_on_copy);
        $this->assertEquals(1, $settings->auto_distribute);
        $this->assertEquals(1, $settings->auto_distribute_views);
        $restored = $DB->get_records_select('block_exaport_course_templ', 'courseid = ? AND id <> ?',
            [$destination->id, $existingroot], 'sortorder ASC, id ASC');
        $this->assertCount(count($nodes), $restored, 'Multiple Exaport blocks must not duplicate templates');
        $renamed = $global && $courseoptin && !$legacy;
        $expectedname = $renamed ? str_replace('{coursefullname}', $destination->fullname,
            $pattern ?: 'Ausbildungsdokumente {coursefullname}') : 'First root';
        if (!$empty) {
            $root = $DB->get_record('block_exaport_course_templ',
                ['courseid' => $destination->id, 'name' => $expectedname], '*', MUST_EXIST);
            $this->assertEquals(3, $root->sortorder);
            $this->assertEquals(1, $root->share_to_teachers);
            $child = $DB->get_record('block_exaport_course_templ',
                ['courseid' => $destination->id, 'name' => 'First root child'], '*', MUST_EXIST);
            $this->assertEquals($root->id, $child->pid);
            foreach ($originals as $original) {
                $name = $original->name === 'First root' ? $expectedname : $original->name;
                $copy = $DB->get_record('block_exaport_course_templ',
                    ['courseid' => $destination->id, 'name' => $name], '*', MUST_EXIST);
                $this->assertEquals($original->sortorder, $copy->sortorder);
                $this->assertEquals($original->share_to_teachers, $copy->share_to_teachers);
                $this->assertEquals($original->timemodified, $copy->timemodified);
                if ($original->pid) {
                    $parentname = $originals[$original->pid]->name;
                    $parentname = $parentname === 'First root' ? $expectedname : $parentname;
                    $parentid = $DB->get_field('block_exaport_course_templ', 'id',
                        ['courseid' => $destination->id, 'name' => $parentname], MUST_EXIST);
                    $this->assertEquals($parentid, $copy->pid);
                } else {
                    $this->assertEquals(0, $copy->pid);
                }
            }
        }
        $templatesbefore = $DB->get_records('block_exaport_course_templ', null, 'id');
        update_course((object)['id' => $destination->id, 'fullname' => 'Later course name change']);
        $this->assertEquals($templatesbefore, $DB->get_records('block_exaport_course_templ', null, 'id'));
        $this->assertEquals($personalbefore, $DB->get_records('block_exaportcate', null, 'id'));
    }

    /** Saving the opt-in never edits the current template or distribution switches. */
    public function test_course_optin_only_updates_its_own_setting(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->template($course->id);
        category_distributor::update_settings($course->id, true);
        $before = $DB->get_records('block_exaport_course_templ', null, 'id');
        category_distributor::update_copy_naming($course->id, true);
        $this->assertEquals($before, $DB->get_records('block_exaport_course_templ', null, 'id'));
        $this->assertEquals(1, category_distributor::get_settings($course->id)->auto_distribute);
        category_distributor::update_settings($course->id, false);
        $this->assertEquals(1, category_distributor::get_settings($course->id)->rename_root_on_copy);
    }

    /** Copies of copies resolve a fresh destination name, and distribution uses that stored template. */
    public function test_repeated_copy_and_distribution_use_the_new_template_name(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enable_copy_root_naming', 1, 'block_exaport');
        $generator = $this->getDataGenerator();
        $source = $generator->create_course();
        $generator->create_block('exaport', ['parentcontextid' => \context_course::instance($source->id)->id]);
        $this->template($source->id);
        category_distributor::update_copy_naming($source->id, true);
        $first = $generator->create_course();
        $this->copy_course($source->id, $first->id, false, false, 'First destination');
        $firstbefore = $DB->get_records('block_exaport_course_templ', ['courseid' => $first->id], 'id');
        $second = $generator->create_course();
        $this->copy_course($first->id, $second->id, false, false, 'Second destination');
        $this->assertEquals($firstbefore, $DB->get_records('block_exaport_course_templ', ['courseid' => $first->id], 'id'));
        $this->assertTrue($DB->record_exists('block_exaport_course_templ',
            ['courseid' => $second->id, 'name' => 'Ausbildungsdokumente Second destination', 'pid' => 0]));

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $second->id, 'student');
        $personal = $DB->insert_record('block_exaportcate', ['userid' => $student->id,
            'pid' => 0, 'name' => 'Existing personal category', 'timemodified' => 100]);
        $personalbefore = $DB->get_record('block_exaportcate', ['id' => $personal]);
        $stats = category_distributor::distribute_to_course($second->id);
        $this->assertEquals(6, $stats['created']);
        $this->assertTrue($DB->record_exists('block_exaportcate',
            ['userid' => $student->id, 'pid' => 0, 'name' => 'Ausbildungsdokumente Second destination']));
        $this->assertEquals($personalbefore, $DB->get_record('block_exaportcate', ['id' => $personal]));
        $this->assertEquals(0, category_distributor::distribute_to_course($second->id)['created']);
    }

    /** Use Moodle's real MODE_COPY task, including its temporary course-name placeholder. */
    public function test_asynchronous_course_copy_uses_the_final_fullname(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enable_copy_root_naming', 1, 'block_exaport');
        set_config('backup_async_message_users', 0, 'backup');
        $generator = $this->getDataGenerator();
        $source = $generator->create_course();
        $generator->create_block('exaport', ['parentcontextid' => \context_course::instance($source->id)->id]);
        $this->template($source->id);
        category_distributor::update_copy_naming($source->id, true);
        $before = $DB->get_records('block_exaport_course_templ', ['courseid' => $source->id], 'id');
        $form = (object)['courseid' => $source->id, 'fullname' => 'Copied course full name',
            'shortname' => 'copied-short', 'category' => $source->category, 'visible' => 1,
            'startdate' => time(), 'enddate' => 0, 'idnumber' => '', 'userdata' => 0];
        $ids = \copy_helper::create_copy(\copy_helper::process_formdata($form));
        $destinationid = $DB->get_field('backup_controllers', 'itemid', ['backupid' => $ids['restoreid']], MUST_EXIST);
        $this->assertNotEquals($form->fullname, $DB->get_field('course', 'fullname', ['id' => $destinationid]));
        $task = new \core\task\asynchronous_copy_task();
        $task->set_custom_data((object)$ids);
        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }
        $this->assertSame($form->fullname, $DB->get_field('course', 'fullname', ['id' => $destinationid]));
        $this->assertTrue($DB->record_exists('block_exaport_course_templ',
            ['courseid' => $destinationid, 'pid' => 0, 'name' => 'Ausbildungsdokumente ' . $form->fullname]));
        $this->assertEquals($before, $DB->get_records('block_exaport_course_templ', ['courseid' => $source->id], 'id'));
        $this->assertEquals(1, category_distributor::get_settings($destinationid)->rename_root_on_copy);
    }

    /** Respect the existing schema limit without splitting a multibyte course name. */
    public function test_long_fullname_fits_the_existing_category_name_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enable_copy_root_naming', 1, 'block_exaport');
        $generator = $this->getDataGenerator();
        $source = $generator->create_course();
        $generator->create_block('exaport', ['parentcontextid' => \context_course::instance($source->id)->id]);
        $this->template($source->id);
        category_distributor::update_copy_naming($source->id, true);
        $destination = $generator->create_course();
        $this->copy_course($source->id, $destination->id, false, false, str_repeat('Ä', 254));
        $roots = $DB->get_records('block_exaport_course_templ',
            ['courseid' => $destination->id, 'pid' => 0], 'sortorder ASC, id ASC');
        $name = reset($roots)->name;
        $this->assertSame(255, \core_text::strlen($name));
        $this->assertSame(\core_text::substr('Ausbildungsdokumente ' . str_repeat('Ä', 254), 0, 255), $name);
    }
}
