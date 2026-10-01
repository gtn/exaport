<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/item_content_helpers.php');
require_once(__DIR__ . '/../lib/lib.exaport.php');

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
            'sortorder' => 0,
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

    /** Add a residual legacy file to an item. */
    private function create_legacy_file(\stdClass $item, string $filename, string $content,
            string $filepath = '/'): \stored_file {
        return $this->create_block_file(
            (int)$item->userid,
            'item_file',
            (int)$item->id,
            $filename,
            $content,
            $filepath
        );
    }

    /** Assert the copied parent cannot expose either legacy representation. */
    private function assert_clean_destination(\stdClass $item): void {
        global $DB;
        $stored = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
        $this->assertSame('', $stored->url);
        $this->assertSame('', $stored->attachment);
        $this->assertCount(0, get_file_storage()->get_area_files(
            context_user::instance((int)$item->userid)->id,
            'block_exaport',
            'item_file',
            (int)$item->id,
            'id',
            false
        ));
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
        $this->assertSame((int)FORMAT_HTML, (int)$destinationblocks[0]->contentformat);
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
        $this->assertSame((int)$destinationuser->id, (int)$subfile->get_userid());
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

    public function test_direct_copy_converts_residual_url_only(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $source = $this->create_item($owner->id, 'link');
        $source->url = ' https://legacy.example/path ';
        $source->attachment = 'stale';
        $DB->update_record('block_exaportitem', $source);

        $copy = \block_exaport_copy_item_to_user($source, (int)$recipient->id);
        $blocks = \block_exaport_get_item_content_blocks((int)$copy->id);
        $this->assertCount(1, $blocks);
        $this->assertSame('link', $blocks[0]->type);
        $this->assertSame($source->url, $blocks[0]->url);
        $this->assert_clean_destination($copy);
        $this->assertSame($source->url,
            $DB->get_field('block_exaportitem', 'url', ['id' => $source->id]));
    }

    public function test_direct_copy_converts_one_residual_file_cross_user(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $source = $this->create_item($owner->id);
        $legacy = $this->create_legacy_file($source, 'one.txt', 'one');

        $copy = \block_exaport_copy_item_to_user($source, (int)$recipient->id);
        $block = \block_exaport_get_item_content_blocks((int)$copy->id)[0];
        $this->assertSame('file', $block->type);
        $copied = \block_exaport_get_item_content_files((int)$recipient->id, (int)$block->id);
        $this->assertCount(1, $copied);
        $this->assertSame($legacy->get_contenthash(), $copied[0]->get_contenthash());
        $this->assertSame((int)$recipient->id, (int)$copied[0]->get_userid());
        $this->assert_clean_destination($copy);
        $this->assertNotFalse(get_file_storage()->get_file(
            context_user::instance((int)$owner->id)->id,
            'block_exaport', 'item_file', (int)$source->id, '/', 'one.txt'));
    }

    public function test_direct_copy_appends_multiple_nested_non_ascii_files_after_url_and_existing_blocks(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $source = $this->create_item($owner->id);
        $source->url = 'https://legacy.example/both';
        $DB->update_record('block_exaportitem', $source);
        \block_exaport_create_content_block((int)$source->id, 'text', 'Existing', '', ['sortorder' => 7]);
        $first = $this->create_legacy_file($source, 'résumé.txt', 'alpha', '/資料/');
        $second = $this->create_legacy_file($source, 'second.txt', 'beta', '/deep/path/');

        $copy = \block_exaport_copy_item_to_user($source, (int)$recipient->id);
        $blocks = \block_exaport_get_item_content_blocks((int)$copy->id);
        $this->assertSame(['text', 'link', 'file'], array_column($blocks, 'type'));
        $files = \block_exaport_get_item_content_files((int)$recipient->id, (int)$blocks[2]->id);
        $this->assertCount(2, $files);
        $expected = [
            $second->get_filepath() . $second->get_filename() => $second->get_contenthash(),
            $first->get_filepath() . $first->get_filename() => $first->get_contenthash(),
        ];
        foreach ($files as $file) {
            $this->assertSame($expected[$file->get_filepath() . $file->get_filename()], $file->get_contenthash());
            $this->assertSame((int)$blocks[2]->id, (int)$file->get_itemid());
        }
        $this->assert_clean_destination($copy);
    }

    public function test_direct_copy_does_not_duplicate_usable_structured_equivalents(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $source = $this->create_item($owner->id);
        $source->url = 'https://legacy.example/equivalent';
        $DB->update_record('block_exaportitem', $source);
        \block_exaport_create_link_content_block((int)$source->id, '', $source->url);
        $legacy = $this->create_legacy_file($source, 'same.txt', 'identical', '/nested/');
        $fileblock = \block_exaport_create_file_content_block((int)$source->id, 'Already structured');
        $this->create_block_file((int)$owner->id, 'item_content_file', (int)$fileblock->id,
            $legacy->get_filename(), 'identical', $legacy->get_filepath());

        $copy = \block_exaport_copy_item_to_user($source, (int)$recipient->id);
        $this->assertSame(['link', 'file'], array_column(
            \block_exaport_get_item_content_blocks((int)$copy->id), 'type'));
        $this->assert_clean_destination($copy);
    }

    public function test_direct_copy_failure_rolls_back_parent_blocks_and_files(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $source = $this->create_item($owner->id);
        $this->create_legacy_file($source, 'failure.txt', 'failure');
        $beforeitems = $DB->count_records('block_exaportitem');

        try {
            \block_exaport_copy_item_to_user($source, (int)$recipient->id,
                static function(string $stage): void {
                    if ($stage === 'before_verification') {
                        throw new \coding_exception('Injected copy failure');
                    }
                });
            $this->fail('The injected failure should escape the copy operation');
        } catch (\coding_exception $exception) {
            $this->assertSame('Injected copy failure', $exception->getMessage());
        }
        $this->assertSame($beforeitems, $DB->count_records('block_exaportitem'));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        $this->assertCount(0, get_file_storage()->get_area_files(
            context_user::instance((int)$recipient->id)->id,
            'block_exaport', 'item_content_file', false, 'id', false));
    }

    public function test_category_tree_copy_converts_residual_url_and_files(): void {
        global $COURSE, $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;
        $this->setUser($recipient);
        $category = (object)[
            'pid' => 0, 'userid' => $owner->id, 'name' => 'Shared tree', 'timemodified' => time(),
            'courseid' => $course->id, 'description' => '', 'creatorid' => $owner->id,
        ];
        $category->id = $DB->insert_record('block_exaportcate', $category);
        $source = $this->create_item($owner->id);
        $source->courseid = $course->id;
        $source->url = 'https://legacy.example/tree';
        $DB->update_record('block_exaportitem', $source);
        $DB->insert_record('block_exaportitemcate', (object)[
            'itemid' => $source->id, 'cateid' => $category->id,
        ]);
        $this->create_legacy_file($source, '樹.txt', 'tree', '/枝/');

        \block_exaport\copy_category_to_myself((int)$category->id);
        $copy = $DB->get_record('block_exaportitem', ['userid' => $recipient->id], '*', MUST_EXIST);
        $blocks = \block_exaport_get_item_content_blocks((int)$copy->id);
        $this->assertSame(['link', 'file'], array_column($blocks, 'type'));
        $this->assertNotFalse(get_file_storage()->get_file(
            context_user::instance((int)$recipient->id)->id,
            'block_exaport', 'item_content_file', (int)$blocks[1]->id, '/枝/', '樹.txt'));
        $this->assert_clean_destination($copy);
    }

    public function test_category_tree_copy_failure_rolls_back_tree_item_blocks_and_files(): void {
        global $COURSE, $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;
        $this->setUser($recipient);
        $category = (object)[
            'pid' => 0, 'userid' => $owner->id, 'name' => 'Rollback tree', 'timemodified' => time(),
            'courseid' => $course->id, 'description' => '', 'creatorid' => $owner->id,
        ];
        $category->id = $DB->insert_record('block_exaportcate', $category);
        $source = $this->create_item($owner->id);
        $source->courseid = $course->id;
        $DB->update_record('block_exaportitem', $source);
        $DB->insert_record('block_exaportitemcate', (object)[
            'itemid' => $source->id, 'cateid' => $category->id,
        ]);
        $this->create_legacy_file($source, 'failure.txt', 'failure');

        try {
            \block_exaport\copy_category_to_myself((int)$category->id,
                static function(string $stage): void {
                    if ($stage === 'before_verification') {
                        throw new \coding_exception('Injected tree copy failure');
                    }
                });
            $this->fail('The injected failure should escape the tree copy operation');
        } catch (\coding_exception $exception) {
            $this->assertSame('Injected tree copy failure', $exception->getMessage());
        }
        $this->assertSame(0, $DB->count_records('block_exaportcate', ['userid' => $recipient->id]));
        $this->assertSame(0, $DB->count_records('block_exaportitem', ['userid' => $recipient->id]));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        $this->assertCount(0, get_file_storage()->get_area_files(
            context_user::instance((int)$recipient->id)->id,
            'block_exaport', 'item_content_file', false, 'id', false));
    }

    /** Convenience wrapper which keeps assertions readable across Moodle versions. */
    private function get_count(string $table, array $conditions): int {
        global $DB;
        return $DB->count_records($table, $conditions);
    }
}
