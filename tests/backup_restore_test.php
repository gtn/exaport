<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/** Course configuration survives real backups without copying personal portfolios. */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_exaport_block_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_exaport_block_structure_step::class)]
final class backup_restore_test extends \advanced_testcase {
    /** User-data selection and an archive containing the removed development-only item structure. */
    public static function course_backup_cases(): array {
        return [
            'with users' => [true, false],
            'without users' => [false, false],
            'ignore development archive items' => [true, true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('course_backup_cases')]
    public function test_course_backup_restores_only_course_configuration(bool $includeusers, bool $additempayload): void {
        global $DB;
        $this->resetAfterTest();
        // Exercise the naming feature while user data and personal files exist in the source course.
        set_config('enable_copy_root_naming', 1, 'block_exaport');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $destination = $generator->create_course();
        $owner = $generator->create_user();
        $generator->enrol_user($owner->id, $course->id, 'student');
        $contextid = \context_course::instance($course->id)->id;
        $generator->create_block('exaport', ['parentcontextid' => $contextid]);
        $generator->create_block('exaport', ['parentcontextid' => $contextid]);

        $rootid = $DB->insert_record('block_exaport_course_templ', (object)[
            'courseid' => $course->id, 'pid' => 0, 'name' => 'Course template',
            'sortorder' => 4, 'share_to_teachers' => 1, 'timemodified' => 200,
        ]);
        $DB->insert_record('block_exaport_course_templ', (object)[
            'courseid' => $course->id, 'pid' => $rootid, 'name' => 'Child template',
            'sortorder' => 5, 'share_to_teachers' => 0, 'timemodified' => 201,
        ]);
        $DB->insert_record('block_exaport_view_templ', (object)[
            'courseid' => $course->id, 'name' => 'View template', 'description' => 'Course collection',
            'sortorder' => 6, 'share_to_teachers' => 1, 'timemodified' => 202,
        ]);
        $DB->insert_record('block_exaport_templ_dist', (object)[
            'courseid' => $course->id, 'auto_distribute' => 1,
            'auto_distribute_views' => 1, 'rename_root_on_copy' => 1, 'timemodified' => 203,
        ]);

        // Even an enrolled user's items bearing this courseid remain personal portfolio data.
        $itemid = $DB->insert_record('block_exaportitem', (object)[
            'userid' => $owner->id, 'courseid' => $course->id, 'type' => 'note',
            'name' => 'Personal item', 'intro' => '<p>Personal description</p>',
            'url' => 'https://example.invalid/legacy', 'attachment' => 'historical-marker',
            'timecreated' => 100, 'timemodified' => 200,
        ]);
        $blockids = [];
        foreach (['text', 'file'] as $sortorder => $type) {
            $blockids[$type] = $DB->insert_record('block_exaportitemblock', (object)[
                'itemid' => $itemid, 'type' => $type, 'sortorder' => $sortorder,
                'title' => 'Personal ' . $type, 'content' => '', 'contentformat' => FORMAT_HTML,
                'url' => '', 'timecreated' => 100, 'timemodified' => 200,
            ]);
        }
        $hashes = [];
        foreach ([
            'item_file' => $itemid,
            'item_iconfile' => $itemid,
            'item_content_intro' => $itemid,
            'item_content_file' => $blockids['file'],
            'item_content_text' => $blockids['text'],
        ] as $filearea => $fileitemid) {
            $file = get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($owner->id)->id,
                'component' => 'block_exaport', 'filearea' => $filearea,
                'itemid' => $fileitemid, 'filepath' => '/', 'filename' => $filearea . '.txt',
                'userid' => $owner->id,
            ], 'Private portfolio bytes: ' . $filearea);
            $hashes[] = $file->get_contenthash();
        }
        $itemsbefore = $DB->get_records('block_exaportitem', null, 'id');
        $blocksbefore = $DB->get_records('block_exaportitemblock', null, 'id');
        $filesbefore = $DB->get_records('files', ['component' => 'block_exaport'], 'id');

        $adminid = (int)get_admin()->id;
        $backup = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $adminid);
        $backup->get_plan()->get_setting('users')->set_value($includeusers);
        $backup->execute_plan();
        $backupfile = $backup->get_results()['backup_destination'];
        $backup->destroy();
        $tempname = \restore_controller::get_tempdir_name($course->id, $adminid);
        $temppath = make_backup_temp_directory($tempname);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($backupfile, $temppath);

        $xmlpaths = glob($temppath . '/course/blocks/exaport_*/exaport.xml');
        $this->assertCount(2, $xmlpaths);
        $templatecounts = [];
        $populatedxmlpath = null;
        foreach ($xmlpaths as $xmlpath) {
            $xml = file_get_contents($xmlpath);
            $this->assertNotFalse($xml);
            $this->assertStringNotContainsString('<item id="', $xml);
            $this->assertStringNotContainsString('<content_block id="', $xml);
            $templatecounts[] = substr_count($xml, '<course_template id="');
            if (str_contains($xml, '<course_template id="')) {
                $populatedxmlpath = $xmlpath;
            }
        }
        sort($templatecounts);
        $this->assertSame([0, 2], $templatecounts, 'Only one block task serializes course-wide templates');
        $filesxml = file_get_contents($temppath . '/files.xml');
        $this->assertNotFalse($filesxml);
        foreach ($hashes as $hash) {
            $this->assertStringNotContainsString($hash, $filesxml, 'Personal files must stay out of the backup');
        }

        if ($additempayload) {
            // Earlier unshipped development archives may contain personal nodes. They must be ignored,
            // including when their source owner has no destination user mapping.
            $xml = file_get_contents($populatedxmlpath);
            $payload = '<items><item id="9000001"><userid>9999999</userid><type>note</type>' .
                '<name>Do not restore</name><url>https://example.invalid/unshipped</url>' .
                '<attachment>Do not migrate</attachment><content_blocks><content_block id="9000002">' .
                '<itemid>9000001</itemid><type>file</type><sortorder>0</sortorder>' .
                '</content_block></content_blocks></item></items>';
            $xml = str_replace('</block_exaport>', $payload . '</block_exaport>', $xml, $replacements);
            $this->assertSame(1, $replacements);
            file_put_contents($populatedxmlpath, $xml);
        }

        $restore = new \restore_controller($tempname, $destination->id, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $adminid, \backup::TARGET_EXISTING_ADDING);
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $this->assertEquals($itemsbefore, $DB->get_records('block_exaportitem', null, 'id'));
        $this->assertEquals($blocksbefore, $DB->get_records('block_exaportitemblock', null, 'id'));
        $this->assertEquals($filesbefore, $DB->get_records('files', ['component' => 'block_exaport'], 'id'));
        $this->assertSame(0, $DB->count_records('block_exaportitem', ['courseid' => $destination->id]));

        $templates = $DB->get_records('block_exaport_course_templ', ['courseid' => $destination->id]);
        $this->assertCount(2, $templates, 'Multiple block instances must not duplicate templates');
        $restoredroot = $DB->get_record('block_exaport_course_templ',
            ['courseid' => $destination->id, 'name' => 'Ausbildungsdokumente ' . $destination->fullname], '*', MUST_EXIST);
        $restoredchild = $DB->get_record('block_exaport_course_templ',
            ['courseid' => $destination->id, 'name' => 'Child template'], '*', MUST_EXIST);
        $this->assertNotEquals($rootid, $restoredroot->id);
        $this->assertEquals($restoredroot->id, $restoredchild->pid);
        $this->assertEquals(4, $restoredroot->sortorder);
        $this->assertEquals(1, $restoredroot->share_to_teachers);
        $view = $DB->get_record('block_exaport_view_templ', ['courseid' => $destination->id], '*', MUST_EXIST);
        $this->assertSame('View template', $view->name);
        $this->assertSame('Course collection', $view->description);
        $this->assertEquals(6, $view->sortorder);
        $distribution = $DB->get_record('block_exaport_templ_dist', ['courseid' => $destination->id], '*', MUST_EXIST);
        $this->assertEquals(1, $distribution->auto_distribute);
        $this->assertEquals(1, $distribution->auto_distribute_views);
    }
}
