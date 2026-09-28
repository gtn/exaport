<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/item_content_helpers.php');

/**
 * Tests for independent structured-content copies.
 *
 * @covers ::block_exaport_copy_item_content
 */
final class item_content_copy_test extends \advanced_testcase {
    /** Create the minimum item record used by these tests. */
    private function create_item(int $userid, string $type = 'note'): \stdClass {
        global $DB;
        $now = time();
        $item = (object)[
            'userid' => $userid, 'type' => $type, 'name' => 'Copy test', 'url' => '',
            'intro' => '', 'attachment' => '', 'timecreated' => $now, 'timemodified' => $now,
            'courseid' => 0, 'shareall' => 0, 'externaccess' => 0, 'externcomment' => 0,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    /** Add a stored file to a structured block. */
    private function create_block_file(int $userid, string $area, int $blockid,
            string $filename, string $content, string $filepath = '/'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'block_exaport', 'filearea' => $area, 'itemid' => $blockid,
            'filepath' => $filepath, 'filename' => $filename, 'userid' => $userid,
            'mimetype' => 'text/plain', 'source' => 'copy-source', 'author' => 'Copy Author',
            'license' => 'allrightsreserved',
        ], $content);
    }

    public function test_no_blocks_returns_empty_map(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $source = $this->create_item($user->id);
        $destination = $this->create_item($user->id);
        $this->assertSame([], \block_exaport_copy_item_content($source, $destination));
        $this->assertSame(0, $this->get_count('block_exaportitemblock', ['itemid' => $destination->id]));
    }

    public function test_mixed_blocks_preserve_fields_and_deterministic_order(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $source = $this->create_item($user->id);
        $destination = $this->create_item($user->id);
        $created = [];
        $created[] = \block_exaport_create_content_block($source->id, 'link', 'Link', 'https://example.test/a',
            ['sortorder' => 8, 'timecreated' => 10, 'timemodified' => 11]);
        $created[] = \block_exaport_create_content_block($source->id, 'text', 'Text', '',
            ['sortorder' => 2, 'content' => '<b>Body</b>', 'contentformat' => FORMAT_HTML,
                'timecreated' => 12, 'timemodified' => 13]);
        $created[] = \block_exaport_create_content_block($source->id, 'link', 'Equal', 'unchanged:URL',
            ['sortorder' => 8, 'timecreated' => 14, 'timemodified' => 15]);

        $map = \block_exaport_copy_item_content($source, $destination);
        $destinationblocks = array_values($DB->get_records('block_exaportitemblock',
            ['itemid' => $destination->id], 'id ASC'));
        $expectedsourceorder = [$created[1]->id, $created[0]->id, $created[2]->id];
        $this->assertSame($expectedsourceorder, array_keys($map));
        $this->assertSame([2, 8, 8], array_map(fn($block) => (int)$block->sortorder, $destinationblocks));
        $this->assertSame('<b>Body</b>', $destinationblocks[0]->content);
        $this->assertSame(FORMAT_HTML, (int)$destinationblocks[0]->contentformat);
        $this->assertSame('https://example.test/a', $destinationblocks[1]->url);
        $this->assertSame('unchanged:URL', $destinationblocks[2]->url);
        $this->assertSame(10, (int)$destinationblocks[1]->timecreated);
        $this->assertNotSame((int)$created[0]->id, (int)$destinationblocks[1]->id);
    }

    public function test_file_blocks_and_multiple_files_are_remapped_cross_user(): void {
        global $DB;
        $this->resetAfterTest();
        $sourceuser = $this->getDataGenerator()->create_user();
        $destinationuser = $this->getDataGenerator()->create_user();
        $source = $this->create_item($sourceuser->id, 'note');
        $destination = $this->create_item($destinationuser->id);
        $first = \block_exaport_create_file_content_block($source->id, 'Files', ['sortorder' => 1]);
        $second = \block_exaport_create_file_content_block($source->id, 'Other', ['sortorder' => 9]);
        $this->create_block_file($sourceuser->id, 'item_content_file', $first->id, 'one.txt', 'one', '/sub/');
        $this->create_block_file($sourceuser->id, 'item_content_file', $first->id, 'two.txt', 'two');
        $this->create_block_file($sourceuser->id, 'item_content_file', $second->id, 'three.txt', 'three');

        $map = \block_exaport_copy_item_content($source, $destination);
        $files = get_file_storage()->get_area_files(\context_user::instance($destinationuser->id)->id,
            'block_exaport', 'item_content_file', $map[$first->id], 'id ASC', false);
        $this->assertCount(2, $files);
        $subfile = get_file_storage()->get_file(\context_user::instance($destinationuser->id)->id,
            'block_exaport', 'item_content_file', $map[$first->id], '/sub/', 'one.txt');
        $this->assertNotFalse($subfile);
        $this->assertSame('text/plain', $subfile->get_mimetype());
        $this->assertSame('copy-source', $subfile->get_source());
        $this->assertSame('Copy Author', $subfile->get_author());
        $this->assertSame($destinationuser->id, (int)$subfile->get_userid());
        $this->assertCount(1, get_file_storage()->get_area_files(\context_user::instance($destinationuser->id)->id,
            'block_exaport', 'item_content_file', $map[$second->id], 'id ASC', false));
        $this->assertSame(2, $DB->count_records('block_exaportitemblock', ['itemid' => $source->id]));
        $this->assertCount(2, \block_exaport_get_item_content_files($sourceuser->id, $first->id));
    }

    public function test_text_editor_files_and_pluginfile_tokens_are_remapped(): void {
        $this->resetAfterTest();
        $sourceuser = $this->getDataGenerator()->create_user();
        $destinationuser = $this->getDataGenerator()->create_user();
        $source = $this->create_item($sourceuser->id, 'link');
        $destination = $this->create_item($destinationuser->id);
        $block = \block_exaport_create_content_block($source->id, 'text', '', '', [
            'content' => '<img src="@@PLUGINFILE@@/image.txt">', 'contentformat' => FORMAT_HTML,
        ]);
        $this->create_block_file($sourceuser->id, 'item_content_text', $block->id, 'image.txt', 'image');
        $map = \block_exaport_copy_item_content($source, $destination);
        $copy = \block_exaport_get_item_content_blocks($destination->id)[0];
        $this->assertSame($block->content, $copy->content);
        $this->assertNotFalse(get_file_storage()->get_file(\context_user::instance($destinationuser->id)->id,
            'block_exaport', 'item_content_text', $map[$block->id], '/', 'image.txt'));
        $this->assertNotFalse(get_file_storage()->get_file(\context_user::instance($sourceuser->id)->id,
            'block_exaport', 'item_content_text', $block->id, '/', 'image.txt'));
    }

    public function test_file_block_is_independent_of_legacy_link_type_and_shares_are_not_copied(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $source = $this->create_item($owner->id, 'link');
        $destination = $this->create_item($recipient->id);
        $block = \block_exaport_create_file_content_block($source->id, 'Link parent file');
        $this->create_block_file($owner->id, 'item_content_file', $block->id, 'independent.txt', 'content');
        $DB->insert_record('block_exaportitemshar', (object)[
            'itemid' => $source->id, 'userid' => $recipient->id, 'original' => $owner->id,
            'courseid' => 0, 'notify' => 0,
        ]);

        $map = \block_exaport_copy_item_content($source, $destination);
        $this->assertNotFalse(get_file_storage()->get_file(\context_user::instance($recipient->id)->id,
            'block_exaport', 'item_content_file', $map[$block->id], '/', 'independent.txt'));
        $this->assertFalse($DB->record_exists('block_exaportitemshar', ['itemid' => $destination->id]));
    }

    public function test_invalid_destination_owner_throws_without_changing_source(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $source = $this->create_item($owner->id);
        $destination = $this->create_item($owner->id);
        $block = \block_exaport_create_content_block($source->id, 'link', 'Kept', 'https://example.test');
        $destination->userid = PHP_INT_MAX;

        $this->expectException(\dml_missing_record_exception::class);
        try {
            \block_exaport_copy_item_content($source, $destination);
        } finally {
            $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
            $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $destination->id]));
        }
    }

    /** Convenience wrapper which keeps assertions readable across Moodle versions. */
    private function get_count(string $table, array $conditions): int {
        global $DB;
        return $DB->count_records($table, $conditions);
    }
}
