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

/**
 * Tests structured-content services used by active item imports.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_import_test extends \advanced_testcase {

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
