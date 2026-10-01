<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once(__DIR__ . '/../lib/item_content_helpers.php');

/**
 * End-to-end coverage for course backup and restore of user-context portfolio content.
 *
 * @covers \backup_exaport_block_structure_step
 * @covers \restore_exaport_block_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /** Insert a course portfolio item. */
    private function item(int $courseid, int $userid, string $name, string $url = '',
            string $attachment = ''): \stdClass {
        global $DB;
        $item = (object)[
            'userid' => $userid, 'type' => 'note', 'name' => $name, 'url' => $url,
            'intro' => '', 'attachment' => $attachment, 'timecreated' => 100,
            'timemodified' => 200, 'courseid' => $courseid, 'shareall' => 0,
            'externaccess' => 0, 'externcomment' => 0,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    /** Insert a structured block without involving form code. */
    private function content_block(int $itemid, string $type, int $sortorder, string $title,
            string $content = '', string $url = ''): \stdClass {
        global $DB;
        $block = (object)[
            'itemid' => $itemid, 'type' => $type, 'sortorder' => $sortorder, 'title' => $title,
            'content' => $content, 'contentformat' => FORMAT_HTML, 'url' => $url,
            'timecreated' => 100, 'timemodified' => 200,
        ];
        $block->id = (int)$DB->insert_record('block_exaportitemblock', $block);
        return $block;
    }

    /** Add a file in its owner's context and return the expected content hash. */
    private function file(int $userid, string $filearea, int $itemid, string $filepath,
            string $filename, string $content): string {
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'block_exaport', 'filearea' => $filearea, 'itemid' => $itemid,
            'filepath' => $filepath, 'filename' => $filename, 'userid' => $userid,
            'mimetype' => 'text/plain',
        ], $content);
        return $file->get_contenthash();
    }

    /** Make and unpack a real Moodle course backup, returning its temporary restore name. */
    private function backup_course(int $courseid, int $userid, bool $includeusers = true): string {
        $controller = new \backup_controller(
            \backup::TYPE_1COURSE, $courseid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $userid
        );
        $controller->get_plan()->get_setting('users')->set_value($includeusers);
        $controller->execute_plan();
        $results = $controller->get_results();
        $backupfile = $results['backup_destination'];
        $controller->destroy();

        $tempname = \restore_controller::get_tempdir_name($courseid, $userid);
        $temppath = make_backup_temp_directory($tempname);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($backupfile, $temppath);
        return $tempname;
    }

    public function test_real_course_backup_restores_structured_and_legacy_user_files(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $destination = $generator->create_course();
        $ownerone = $generator->create_user(['username' => 'portfolio_owner_one']);
        $ownertwo = $generator->create_user(['username' => 'portfolio_owner_two']);
        $generator->enrol_user($ownerone->id, $course->id, 'student');
        $generator->enrol_user($ownertwo->id, $course->id, 'student');
        $coursecontextid = \context_course::instance($course->id)->id;
        $firstinstance = $generator->create_block('exaport', ['parentcontextid' => $coursecontextid]);
        $secondinstance = $generator->create_block('exaport', ['parentcontextid' => $coursecontextid]);
        $this->assertLessThan((int)$secondinstance->id, (int)$firstinstance->id);

        // Course-scoped records must be emitted by only one of the two block backup tasks.
        $DB->insert_record('block_exaport_course_templ', (object)[
            'courseid' => $course->id, 'pid' => 0, 'name' => 'Course template',
            'sortorder' => 4, 'share_to_teachers' => 1, 'timemodified' => 200,
        ]);
        $DB->insert_record('block_exaport_view_templ', (object)[
            'courseid' => $course->id, 'name' => 'View template', 'description' => 'Description',
            'sortorder' => 5, 'share_to_teachers' => 1, 'timemodified' => 200,
        ]);
        $DB->insert_record('block_exaport_templ_dist', (object)[
            'courseid' => $course->id, 'auto_distribute' => 1,
            'auto_distribute_views' => 1, 'timemodified' => 200,
        ]);

        // This item deliberately mixes already-structured content and both kinds of legacy source.
        $mixed = $this->item($course->id, $ownerone->id, 'Mixed content',
            'https://legacy.example/parent', 'legacy-attachment.txt');
        $text = $this->content_block($mixed->id, 'text', 3, 'Rich text',
            '<p>Editor image <img src="@@PLUGINFILE@@/images/über.png"></p>');
        $firstfiles = $this->content_block($mixed->id, 'file', 7, 'Documents A');
        $secondfiles = $this->content_block($mixed->id, 'file', 11, 'Documents B');
        $expected = [];
        $expected['editor'] = $this->file($ownerone->id, 'item_content_text', $text->id,
            '/images/', 'über.png', 'editor-image');
        $expected['nested'] = $this->file($ownerone->id, 'item_content_file', $firstfiles->id,
            '/deep/one/', 'résumé.txt', 'nested-content');
        $expected['second'] = $this->file($ownerone->id, 'item_content_file', $firstfiles->id,
            '/', 'plain.txt', 'plain-content');
        $expected['other'] = $this->file($ownerone->id, 'item_content_file', $secondfiles->id,
            '/資料/', '二番.txt', 'other-content');
        $expected['legacy'] = $this->file($ownerone->id, 'item_file', $mixed->id,
            '/old/archive/', 'älter.txt', 'legacy-content');

        // A second owner proves that files and users are not accidentally collapsed to one context.
        $other = $this->item($course->id, $ownertwo->id, 'Second owner');
        $otherblock = $this->content_block($other->id, 'file', 0, 'Owner two file');
        $expected['owner2'] = $this->file($ownertwo->id, 'item_content_file', $otherblock->id,
            '/nested/', '第二.txt', 'owner-two-content');

        $oldblockids = [$text->id, $firstfiles->id, $secondfiles->id, $otherblock->id];
        $adminid = (int)get_admin()->id;
        $tempname = $this->backup_course($course->id, $adminid);

        // Both block tasks ran, but exactly one owns the shared section. Ownership is selected by
        // instance id in the backup step, not by the order in which Moodle executes these tasks.
        $temppath = make_backup_temp_directory($tempname);
        $exaportxmls = glob($temppath . '/course/blocks/exaport_*/exaport.xml');
        $this->assertCount(2, $exaportxmls);
        $serializeditemcounts = [];
        foreach ($exaportxmls as $exaportxml) {
            $xml = file_get_contents($exaportxml);
            $this->assertNotFalse($xml);
            $serializeditemcounts[] = substr_count($xml, '<item id="');
        }
        sort($serializeditemcounts);
        $this->assertSame([0, 2], $serializeditemcounts,
            'Only the deterministic owner instance may serialize course-scoped data');

        $restore = new \restore_controller(
            $tempname, $destination->id, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL,
            $adminid, \backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $restoreditems = array_values($DB->get_records('block_exaportitem',
            ['courseid' => $destination->id], 'id ASC'));
        $this->assertCount(2, $restoreditems, 'Each source item must be restored exactly once');
        $this->assertSame(1, $DB->count_records('block_exaport_course_templ',
            ['courseid' => $destination->id]), 'Course templates must not be duplicated');
        $this->assertSame(1, $DB->count_records('block_exaport_view_templ',
            ['courseid' => $destination->id]), 'View templates must not be duplicated');
        $this->assertSame(1, $DB->count_records('block_exaport_templ_dist',
            ['courseid' => $destination->id]), 'Distribution settings must not be duplicated');
        $restoredmixed = $DB->get_record('block_exaportitem',
            ['courseid' => $destination->id, 'name' => 'Mixed content'], '*', MUST_EXIST);
        $restoredother = $DB->get_record('block_exaportitem',
            ['courseid' => $destination->id, 'name' => 'Second owner'], '*', MUST_EXIST);
        $this->assertSame((int)$ownerone->id, (int)$restoredmixed->userid);
        $this->assertSame((int)$ownertwo->id, (int)$restoredother->userid);
        $this->assertSame('', $restoredmixed->url);
        $this->assertSame('', $restoredmixed->attachment);

        $mixedblocks = array_values($DB->get_records('block_exaportitemblock',
            ['itemid' => $restoredmixed->id], 'sortorder ASC, id ASC'));
        $this->assertSame(6, $DB->count_records_select('block_exaportitemblock',
            'itemid IN (:mixed, :other)',
            ['mixed' => $restoredmixed->id, 'other' => $restoredother->id]),
            'Each source structured block and each legacy conversion block is restored once');
        $this->assertSame(['text', 'file', 'file', 'link', 'file'], array_column($mixedblocks, 'type'));
        $this->assertSame([3, 7, 11, 12, 13], array_map('intval', array_column($mixedblocks, 'sortorder')),
            'Legacy blocks must append after existing structured blocks');
        foreach (array_slice($mixedblocks, 0, 3) as $block) {
            $this->assertNotContains((int)$block->id, array_map('intval', $oldblockids));
        }
        $this->assertSame('https://legacy.example/parent', $mixedblocks[3]->url);

        $ownercontext = \context_user::instance($ownerone->id)->id;
        $checks = [
            [$mixedblocks[0], 'item_content_text', '/images/', 'über.png', $expected['editor']],
            [$mixedblocks[1], 'item_content_file', '/deep/one/', 'résumé.txt', $expected['nested']],
            [$mixedblocks[1], 'item_content_file', '/', 'plain.txt', $expected['second']],
            [$mixedblocks[2], 'item_content_file', '/資料/', '二番.txt', $expected['other']],
            [$mixedblocks[4], 'item_content_file', '/old/archive/', 'älter.txt', $expected['legacy']],
        ];
        foreach ($checks as [$block, $area, $path, $name, $hash]) {
            $file = get_file_storage()->get_file(
                $ownercontext, 'block_exaport', $area, $block->id, $path, $name
            );
            $this->assertNotFalse($file, $path . $name);
            $this->assertSame($hash, $file->get_contenthash());
            $this->assertSame((int)$ownerone->id, (int)$file->get_userid());
            $this->assertSame((int)$block->id, (int)$file->get_itemid());
        }
        $this->assertCount(4, array_filter(get_file_storage()->get_area_files(
            $ownercontext, 'block_exaport', 'item_content_file', false, 'id', false
        ), static function($file) use ($restoredmixed): bool {
            global $DB;
            return $DB->record_exists('block_exaportitemblock', [
                'id' => $file->get_itemid(), 'itemid' => $restoredmixed->id,
            ]);
        }), 'Every expected structured file record for the mixed item must exist exactly once');
        $this->assertCount(1, array_filter(get_file_storage()->get_area_files(
            $ownercontext, 'block_exaport', 'item_content_text', false, 'id', false
        ), static function($file) use ($restoredmixed): bool {
            global $DB;
            return $DB->record_exists('block_exaportitemblock', [
                'id' => $file->get_itemid(), 'itemid' => $restoredmixed->id,
            ]);
        }), 'The structured editor file record must exist exactly once');
        $this->assertEmpty(get_file_storage()->get_area_files(
            $ownercontext, 'block_exaport', 'item_file', $restoredmixed->id, 'id', false
        ));

        $otherblocks = array_values($DB->get_records('block_exaportitemblock',
            ['itemid' => $restoredother->id], 'sortorder ASC, id ASC'));
        $this->assertCount(1, $otherblocks);
        $otherfile = get_file_storage()->get_file(\context_user::instance($ownertwo->id)->id,
            'block_exaport', 'item_content_file', $otherblocks[0]->id, '/nested/', '第二.txt');
        $this->assertNotFalse($otherfile);
        $this->assertCount(1, get_file_storage()->get_area_files(
            \context_user::instance($ownertwo->id)->id, 'block_exaport', 'item_content_file',
            $otherblocks[0]->id, 'id', false
        ), 'The second owner File API record must be restored exactly once');
        $this->assertSame($expected['owner2'], $otherfile->get_contenthash());
        $this->assertSame((int)$ownertwo->id, (int)$otherfile->get_userid());

        $PAGE->set_context(\context_course::instance($destination->id));
        $html = \block_exaport_render_item_content_blocks($destination->id, $restoredmixed);
        $this->assertStringContainsString('Rich text', $html);
        $this->assertStringContainsString('résumé.txt', $html);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $html);

        $restoredblockids = array_map(static fn($block): int => (int)$block->id, $mixedblocks);
        \block_exaport_delete_item($restoredmixed);
        $this->assertFalse($DB->record_exists('block_exaportitem', ['id' => $restoredmixed->id]));
        foreach ($restoredblockids as $restoredblockid) {
            $this->assertFalse($DB->record_exists('block_exaportitemblock', ['id' => $restoredblockid]));
            foreach (['item_content_text', 'item_content_file'] as $area) {
                $this->assertEmpty(get_file_storage()->get_area_files(
                    $ownercontext, 'block_exaport', $area, $restoredblockid, 'id', false
                ));
            }
        }
        foreach (['item_file', 'item_content'] as $area) {
            $this->assertEmpty(get_file_storage()->get_area_files(
                $ownercontext, 'block_exaport', $area, $restoredmixed->id, 'id', false
            ));
        }
    }

    public function test_backup_without_users_omits_personal_items_blocks_and_files(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $destination = $generator->create_course();
        $owner = $generator->create_user(['username' => 'excluded_portfolio_owner']);
        $generator->enrol_user($owner->id, $course->id, 'student');
        $coursecontextid = \context_course::instance($course->id)->id;
        $generator->create_block('exaport', ['parentcontextid' => $coursecontextid]);
        $generator->create_block('exaport', ['parentcontextid' => $coursecontextid]);

        $item = $this->item($course->id, $owner->id, 'Must not be backed up');
        $block = $this->content_block($item->id, 'file', 0, 'Private document');
        $contenthash = $this->file($owner->id, 'item_content_file', $block->id,
            '/private/', 'private.txt', 'private-content');
        $initialblockcount = $DB->count_records('block_exaportitemblock');
        $initialfilecount = count(get_file_storage()->get_area_files(
            \context_user::instance($owner->id)->id, 'block_exaport', 'item_content_file',
            $block->id, 'id', false
        ));

        $adminid = (int)get_admin()->id;
        $tempname = $this->backup_course($course->id, $adminid, false);
        $temppath = make_backup_temp_directory($tempname);
        $exaportxmls = glob($temppath . '/course/blocks/exaport_*/exaport.xml');
        $this->assertCount(2, $exaportxmls);
        foreach ($exaportxmls as $exaportxml) {
            $xml = file_get_contents($exaportxml);
            $this->assertNotFalse($xml);
            $this->assertStringNotContainsString('<item id="', $xml);
            $this->assertStringNotContainsString('<content_block id="', $xml);
        }
        $filesxml = file_get_contents($temppath . '/files.xml');
        $this->assertNotFalse($filesxml);
        $this->assertStringNotContainsString($contenthash, $filesxml,
            'Excluded personal item content must not enter the backup file pool');

        $restore = new \restore_controller(
            $tempname, $destination->id, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL,
            $adminid, \backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $this->assertSame(0, $DB->count_records('block_exaportitem',
            ['courseid' => $destination->id]));
        $this->assertSame($initialblockcount, $DB->count_records('block_exaportitemblock'));
        $this->assertCount($initialfilecount, get_file_storage()->get_area_files(
            \context_user::instance($owner->id)->id, 'block_exaport', 'item_content_file',
            $block->id, 'id', false
        ), 'Restore must not add another copy of excluded item content');
    }

    public function test_restore_rejects_item_without_user_mapping_before_insert(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $destination = $generator->create_course();
        $owner = $generator->create_user(['username' => 'unmapped_portfolio_owner']);
        $generator->enrol_user($owner->id, $course->id, 'student');
        $generator->create_block('exaport', [
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);
        $this->item($course->id, $owner->id, 'Owner mapping required');

        $adminid = (int)get_admin()->id;
        $tempname = $this->backup_course($course->id, $adminid);
        $exaportxmls = glob(
            make_backup_temp_directory($tempname) . '/course/blocks/exaport_*/exaport.xml'
        );
        $this->assertCount(1, $exaportxmls);
        $xml = file_get_contents($exaportxmls[0]);
        $this->assertNotFalse($xml);
        $missinguserid = 999999999;
        $xml = str_replace('<userid>' . $owner->id . '</userid>',
            '<userid>' . $missinguserid . '</userid>', $xml, $replacements);
        $this->assertSame(1, $replacements);
        file_put_contents($exaportxmls[0], $xml);

        $restore = new \restore_controller(
            $tempname, $destination->id, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL,
            $adminid, \backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($restore->execute_precheck());
        try {
            $restore->execute_plan();
            $this->fail('Restore must reject an Exaport item whose owner has no user mapping');
        } catch (\moodle_exception $exception) {
            $this->assertSame('restoremissingusermapping', $exception->errorcode);
            $this->assertStringContainsString((string)$missinguserid, $exception->getMessage());
        } finally {
            $restore->destroy();
        }
        $this->assertSame(0, $DB->count_records('block_exaportitem',
            ['courseid' => $destination->id]),
            'Mapping validation must happen before the item is inserted');
    }
}
