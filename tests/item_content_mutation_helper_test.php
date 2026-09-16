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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests structured item block mutations.
 */
final class item_content_mutation_helper_test extends \advanced_testcase {
    private \stdClass $owner;
    private \stdClass $recipient;
    private \stdClass $course;
    private \stdClass $item;

    protected function setUp(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        $this->recipient = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($this->owner->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->recipient->id, $this->course->id, $studentrole->id);
        $this->setUser($this->owner);

        $itemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $this->owner->id,
            'type' => 'note',
            'categoryid' => 0,
            'name' => 'Structured item',
            'url' => '',
            'intro' => '',
            'attachment' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'courseid' => $this->course->id,
            'shareall' => 0,
            'externaccess' => 0,
            'externcomment' => 0,
            'sortorder' => 0,
            'isoez' => 0,
            'langid' => 0,
            'source' => 0,
            'sourceid' => 0,
            'iseditable' => 1,
            'parentid' => 0,
        ]);

        $this->item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
    }

    private function create_block(array $overrides = []): \stdClass {
        global $DB;

        $record = (object)array_merge([
            'itemid' => $this->item->id,
            'type' => 'text',
            'sortorder' => 10,
            'title' => '',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'url' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $overrides);

        $record->id = (int)$DB->insert_record('block_exaportitemblock', $record);
        return $record;
    }

    private function create_editor_data(string $text): array {
        $draftid = file_get_unused_draft_itemid();
        return [
            'text' => $text,
            'format' => FORMAT_HTML,
            'itemid' => $draftid,
        ];
    }

    private function add_block_file(int $blockid, string $filearea, string $filename, string $content): void {
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($this->owner->id)->id,
            'component' => 'block_exaport',
            'filearea' => $filearea,
            'itemid' => $blockid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    public function test_create_text_block_assigns_next_sortorder_and_updates_item_time(): void {
        global $DB;

        $this->create_block(['sortorder' => 30]);
        $oldmodified = (int)$this->item->timemodified;
        $data = (object)[
            'title' => 'New block',
            'content_editor' => $this->create_editor_data('<p>Body</p>'),
        ];

        $block = item_content_mutation_helper::create_block($this->item, 'text', $data);
        $updateditem = $DB->get_record('block_exaportitem', ['id' => $this->item->id], '*', MUST_EXIST);

        $this->assertSame(40, (int)$block->sortorder);
        $this->assertSame('New block', $block->title);
        $this->assertStringContainsString('Body', $block->content);
        $this->assertGreaterThanOrEqual($oldmodified, (int)$updateditem->timemodified);
    }

    public function test_update_link_block_preserves_sortorder_and_normalizes_url(): void {
        global $DB;

        $block = $this->create_block([
            'type' => 'link',
            'sortorder' => 77,
            'url' => 'https://old.example.com',
        ]);
        $data = (object)[
            'title' => 'Updated',
            'url' => 'example.com',
            'content_editor' => $this->create_editor_data('<p>Description</p>'),
        ];

        item_content_mutation_helper::update_block($this->item, $block, $data);
        $updated = $DB->get_record('block_exaportitemblock', ['id' => $block->id], '*', MUST_EXIST);

        $this->assertSame(77, (int)$updated->sortorder);
        $this->assertSame('http://example.com', $updated->url);
        $this->assertStringContainsString('Description', $updated->content);
    }

    public function test_delete_block_removes_record_and_both_fileareas(): void {
        global $DB;

        $block = $this->create_block(['type' => 'file']);
        $otherblock = $this->create_block(['type' => 'file', 'sortorder' => 20]);
        $this->add_block_file($block->id, item_content_helper::FILEAREA, 'attachment.pdf', 'pdf');
        $this->add_block_file($block->id, item_content_helper::CONTENT_FILEAREA, 'embedded.txt', 'content');
        $this->add_block_file($otherblock->id, item_content_helper::FILEAREA, 'kept.pdf', 'pdf');

        item_content_mutation_helper::delete_block($this->item, $block);

        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
        $this->assertCount(0, item_content_helper::get_block_area_files($this->item, $block->id, item_content_helper::FILEAREA));
        $this->assertCount(0, item_content_helper::get_block_area_files($this->item, $block->id, item_content_helper::CONTENT_FILEAREA));
        $this->assertCount(1, item_content_helper::get_block_area_files($this->item, $otherblock->id, item_content_helper::FILEAREA));
    }

    public function test_copy_item_blocks_copies_attachment_and_editor_files(): void {
        global $DB;

        $block = $this->create_block(['type' => 'file', 'title' => 'Source']);
        $this->add_block_file($block->id, item_content_helper::FILEAREA, 'attachment.pdf', 'pdf');
        $this->add_block_file($block->id, item_content_helper::CONTENT_FILEAREA, 'embedded.txt', 'content');

        $targetitemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $this->recipient->id,
            'type' => 'note',
            'categoryid' => 0,
            'name' => 'Copy target',
            'url' => '',
            'intro' => '',
            'attachment' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'courseid' => $this->course->id,
            'shareall' => 0,
            'externaccess' => 0,
            'externcomment' => 0,
            'sortorder' => 0,
            'isoez' => 0,
            'langid' => 0,
            'source' => 0,
            'sourceid' => 0,
            'iseditable' => 1,
            'parentid' => 0,
        ]);

        item_content_helper::copy_item_blocks($this->item, $targetitemid, $this->recipient->id);

        $copiedblock = $DB->get_record('block_exaportitemblock', ['itemid' => $targetitemid], '*', MUST_EXIST);
        $targetitem = $DB->get_record('block_exaportitem', ['id' => $targetitemid], '*', MUST_EXIST);

        $this->assertCount(1, item_content_helper::get_block_area_files($targetitem, $copiedblock->id, item_content_helper::FILEAREA));
        $this->assertCount(1, item_content_helper::get_block_area_files($targetitem, $copiedblock->id, item_content_helper::CONTENT_FILEAREA));
    }

    public function test_require_manage_context_rejects_foreign_item_access(): void {
        $this->setUser($this->recipient);

        $this->expectException(\moodle_exception::class);
        item_content_mutation_helper::require_manage_context($this->course->id, $this->item->id);
    }

    public function test_unsupported_block_types_are_rejected(): void {
        $this->expectException(\moodle_exception::class);
        item_content_mutation_helper::require_supported_block_type('accordion');
    }
}
