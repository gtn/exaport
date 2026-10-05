<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');
require_once($CFG->dirroot . '/blocks/exaport/lib/package_import_helpers.php');
require_once($CFG->dirroot . '/blocks/exaport/tests/fixtures/exaport_test_helpers_trait.php');

/**
 * Tests structured-content services used by active item imports.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_import_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    public function test_historical_markers_import_root_files_and_decode_url(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $categoryid = $this->create_category($user, 'Work');
        $root = make_request_directory();
        mkdir($root . '/categories/Work', 0777, true);
        file_put_contents($root . '/document.pdf', 'root pdf bytes');
        file_put_contents($root . '/notes.txt', 'root notes bytes');
        file_put_contents($root . '/categories/Work/document.pdf', 'wrong adjacent bytes');
        $filehtml = '<!--###BOOKMARK_FILE_URL###-->document.pdf<!--###BOOKMARK_FILE_URL###-->' .
            '<!--###BOOKMARK_FILE_URL###-->notes.txt<!--###BOOKMARK_FILE_URL###-->' .
            '<!--###BOOKMARK_FILE_DESC###--><p>Historical file description</p>' .
            '<!--###BOOKMARK_FILE_DESC###-->';
        file_put_contents($root . '/categories/Work/item.html', $filehtml);
        $associate = static function(\stdClass $item) use ($categoryid): void {
            item_category_helper::sync_item_categories($item->id, [$categoryid]);
        };

        $fileitem = block_exaport_import_legacy_item(
            $root, file_get_contents($root . '/categories/Work/item.html'), 'Files', $course->id, $user->id, $associate
        );
        $linkitem = block_exaport_import_legacy_item($root,
            '<!--###BOOKMARK_EXT_URL###-->https://example.test/view?a=1&amp;b=2&amp;c=3' .
                '<!--###BOOKMARK_EXT_URL###--><!--###BOOKMARK_EXT_DESC###--><p>Link description</p>' .
                '<!--###BOOKMARK_EXT_DESC###-->',
            'Link', $course->id, $user->id, $associate);

        $fileblocks = block_exaport_get_item_content_blocks($fileitem->id);
        $this->assertCount(1, $fileblocks);
        $files = block_exaport_get_item_content_files($user->id, $fileblocks[0]->id);
        $this->assertSame([(int)$fileblocks[0]->id, (int)$fileblocks[0]->id],
            array_map(fn($file) => (int)$file->get_itemid(), $files));
        $this->assertSame([(int)$user->id, (int)$user->id],
            array_map(fn($file) => (int)$file->get_userid(), $files));
        $this->assertSame(['document.pdf' => 'root pdf bytes', 'notes.txt' => 'root notes bytes'],
            array_combine(array_map(fn($file) => $file->get_filename(), $files),
                array_map(fn($file) => $file->get_content(), $files)));
        $linkblocks = block_exaport_get_item_content_blocks($linkitem->id);
        $this->assertSame('https://example.test/view?a=1&b=2&c=3', $linkblocks[0]->url);
        $this->assertSame('<p>Historical file description</p>', $fileitem->intro);
        $this->assertSame('<p>Link description</p>', $linkitem->intro);
        foreach ([$fileitem, $linkitem] as $item) {
            $storeditem = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
            $this->assertSame('', $storeditem->url);
            $this->assertSame('', $storeditem->attachment);
            $this->assertTrue($DB->record_exists('block_exaportitemcate',
                ['itemid' => $item->id, 'cateid' => $categoryid]));
            $this->assertEmpty(get_file_storage()->get_area_files(\context_user::instance($user->id)->id,
                'block_exaport', 'item_file', $item->id, 'id', false));
        }
    }

    public function test_historical_file_failure_rolls_back_whole_item(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $root = make_request_directory();
        file_put_contents($root . '/present.txt', 'present');
        $html = '<!--###BOOKMARK_FILE_URL###-->present.txt<!--###BOOKMARK_FILE_URL###-->' .
            '<!--###BOOKMARK_FILE_URL###-->missing.txt<!--###BOOKMARK_FILE_URL###-->' .
            '<!--###BOOKMARK_FILE_DESC###-->Description<!--###BOOKMARK_FILE_DESC###-->';

        try {
            block_exaport_import_legacy_item($root, $html, 'Incomplete', $course->id, $user->id,
                static function(): void {});
            $this->fail('A missing historical attachment was accepted');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertSame(0, $DB->count_records('block_exaportitem', ['userid' => $user->id]));
            $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        }
    }

    public function test_historical_file_marker_rejects_unsafe_path(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $html = '<!--###BOOKMARK_FILE_URL###-->../outside.txt<!--###BOOKMARK_FILE_URL###-->' .
            '<!--###BOOKMARK_FILE_DESC###-->Description<!--###BOOKMARK_FILE_DESC###-->';

        $this->expectException(\invalid_parameter_exception::class);
        block_exaport_import_legacy_item(make_request_directory(), $html, 'Unsafe', $course->id, $user->id,
            static function(): void {});
    }

    public function test_historical_invalid_url_is_rejected_without_partial_item(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $html = '<!--###BOOKMARK_EXT_URL###--><!--###BOOKMARK_EXT_URL###-->' .
            '<!--###BOOKMARK_EXT_DESC###-->Description<!--###BOOKMARK_EXT_DESC###-->';

        $this->expectException(\invalid_parameter_exception::class);
        try {
            block_exaport_import_legacy_item(make_request_directory(), $html, 'Invalid', $course->id, $user->id,
                static function(): void {});
        } finally {
            $this->assertSame(0, $DB->count_records('block_exaportitem', ['userid' => $user->id]));
        }
    }

    public function test_assignment_file_import_is_structured_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);
        $source = $this->create_source_file($user->id, 'submission.txt', 'assignment submission');
        $assignment = (object)[
            'aid' => PHP_INT_MAX,
            'assignment' => PHP_INT_MAX,
            'name' => 'Imported assignment',
            'coursename' => $course->fullname,
        ];

        $itemid = block_exaport_create_item_from_assignment(
            $assignment,
            $source,
            0,
            $course->id,
            '<p>Online submission</p>'
        );

        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame('file', $item->type);
        $this->assertSame('', $item->url);
        $this->assertSame('', $item->attachment);
        $this->assertSame('<p>Online submission</p>', $item->intro);
        $blocks = block_exaport_get_item_content_blocks($itemid);
        $this->assertCount(1, $blocks);
        $this->assertSame('file', $blocks[0]->type);
        $this->assertSame('', $blocks[0]->title);
        $files = block_exaport_get_item_content_files($user->id, $blocks[0]->id);
        $this->assertCount(1, $files);
        $this->assertSame('submission.txt', $files[0]->get_filename());
        $this->assertSame((int)$user->id, (int)$files[0]->get_userid());
        $this->assertSame('assignment submission', $source->get_content());
        $this->assertEmpty(get_file_storage()->get_area_files(
            \context_user::instance($user->id)->id,
            'block_exaport',
            'item_file',
            $itemid,
            'id',
            false
        ));
    }

    public function test_assignment_without_file_does_not_create_empty_block(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);
        $assignment = (object)['aid' => PHP_INT_MAX, 'assignment' => PHP_INT_MAX, 'name' => 'Online assignment'];

        $itemid = block_exaport_create_item_from_assignment($assignment, null, 0, $course->id, 'Online text');

        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame('note', $item->type);
        $this->assertSame('Online text', $item->intro);
        $this->assertSame([], block_exaport_get_item_content_blocks($itemid));
    }

    public function test_package_import_ignores_residual_content_and_preserves_structured_paths(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $item = $this->insert_item($user->id, $course->id);
        $DB->set_field('block_exaportitem', 'url', 'https://legacy.example/', ['id' => $item->id]);
        $DB->set_field('block_exaportitem', 'attachment', 'legacy.txt', ['id' => $item->id]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($user->id)->id, 'component' => 'block_exaport',
            'filearea' => 'item_file', 'itemid' => $item->id, 'filepath' => '/', 'filename' => 'legacy.txt',
        ], 'legacy');
        $root = make_request_directory();
        mkdir($root . '/entry/nested', 0777, true);
        file_put_contents($root . '/entry/first.txt', 'first');
        file_put_contents($root . '/entry/nested/second.txt', 'second');

        $block = block_exaport_import_path_files_into_content_block(
            $item,
            $root,
            $root . '/entry',
            ['first.txt', 'nested/second.txt']
        );

        $files = block_exaport_get_item_content_files($user->id, $block->id);
        $this->assertCount(2, $files);
        $this->assertSame(['/first.txt', '/nested/second.txt'], array_map(static function(\stored_file $file): string {
            return $file->get_filepath() . $file->get_filename();
        }, $files));
        $this->assertCount(1, block_exaport_get_item_content_blocks($item->id));
        $this->assertSame('https://legacy.example/',
            $DB->get_field('block_exaportitem', 'url', ['id' => $item->id]));
        $this->assertCount(1, get_file_storage()->get_area_files(
            \context_user::instance($user->id)->id, 'block_exaport', 'item_file', $item->id, 'id', false));
    }

    public function test_package_path_failure_rolls_back_parent_block_and_files(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $root = make_request_directory();
        mkdir($root . '/entry');
        file_put_contents($root . '/entry/present.txt', 'present');
        $itemid = 0;

        try {
            (function() use ($DB, $user, $course, $root, &$itemid): void {
                $transaction = $DB->start_delegated_transaction();
                try {
                    $item = $this->insert_item($user->id, $course->id);
                    $itemid = $item->id;
                    block_exaport_import_path_files_into_content_block(
                        $item,
                        $root,
                        $root . '/entry',
                        ['present.txt', 'missing.txt']
                    );
                    $transaction->allow_commit();
                } catch (\Throwable $exception) {
                    $transaction->rollback($exception);
                }
            })();
            $this->fail('Missing required package file was accepted');
        } catch (\invalid_parameter_exception $exception) {
            // Expected: destruction of the delegated transaction rolls back the parent item.
        }

        $this->assertFalse($DB->record_exists('block_exaportitem', ['id' => $itemid]));
        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $itemid]));
    }

    public function test_package_path_traversal_is_rejected_before_block_creation(): void {
        global $DB;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $item = $this->insert_item($user->id, $course->id);
        $root = make_request_directory();
        mkdir($root . '/entry');

        $this->expectException(\invalid_parameter_exception::class);
        try {
            block_exaport_import_path_files_into_content_block($item, $root, $root . '/entry', ['../outside.txt']);
        } finally {
            $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $item->id]));
        }
    }

    private function create_source_file(int $userid, string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
        ], $content);
    }

    private function insert_item(int $userid, int $courseid): \stdClass {
        global $DB;

        $item = (object)[
            'userid' => $userid,
            'type' => 'file',
            'name' => 'Imported package file',
            'url' => '',
            'intro' => 'Description',
            'attachment' => '',
            'courseid' => $courseid,
            'timemodified' => time(),
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }
}
