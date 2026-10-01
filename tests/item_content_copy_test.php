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

    /** Add a residual file in the legacy parent item file area. */
    private function create_legacy_item_file(\stdClass $item, string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($item->userid)->id,
            'component' => 'block_exaport', 'filearea' => 'item_file', 'itemid' => $item->id,
            'filepath' => '/', 'filename' => $filename, 'userid' => $item->userid,
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
        $sourcefile = get_file_storage()->get_file(\context_user::instance($sourceuser->id)->id,
            'block_exaport', 'item_content_file', $first->id, '/sub/', 'one.txt');
        $this->assertNotFalse($sourcefile);
        $this->assertSame($sourcefile->get_contenthash(), $subfile->get_contenthash());
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

    public function test_residual_legacy_content_is_ignored_and_source_is_unchanged(): void {
        global $DB;
        $this->resetAfterTest();
        $sourceuser = $this->getDataGenerator()->create_user();
        $destinationuser = $this->getDataGenerator()->create_user();
        $source = $this->create_item($sourceuser->id, 'file');
        $DB->set_field('block_exaportitem', 'url', 'https://legacy.example/source', ['id' => $source->id]);
        $DB->set_field('block_exaportitem', 'attachment', 'legacy.txt', ['id' => $source->id]);
        $source->url = 'https://legacy.example/source';
        $source->attachment = 'legacy.txt';
        $legacyfile = $this->create_legacy_item_file($source, 'legacy.txt', 'legacy bytes');
        $block = \block_exaport_create_file_content_block($source->id, 'Authoritative', ['sortorder' => 4]);
        $structuredfile = $this->create_block_file(
            $sourceuser->id,
            'item_content_file',
            $block->id,
            'structured.txt',
            'structured bytes',
            '/nested/'
        );
        $destination = $this->create_item($destinationuser->id, 'file');

        $map = \block_exaport_copy_item_content($source, $destination);

        $sourceafter = $DB->get_record('block_exaportitem', ['id' => $source->id], '*', MUST_EXIST);
        $destinationafter = $DB->get_record('block_exaportitem', ['id' => $destination->id], '*', MUST_EXIST);
        $this->assertSame('https://legacy.example/source', $sourceafter->url);
        $this->assertSame('legacy.txt', $sourceafter->attachment);
        $this->assertSame('legacy bytes', $legacyfile->get_content());
        $this->assertSame('', $destinationafter->url);
        $this->assertSame('', $destinationafter->attachment);
        $this->assertEmpty(get_file_storage()->get_area_files(
            \context_user::instance($destinationuser->id)->id,
            'block_exaport',
            'item_file',
            $destination->id,
            'id',
            false
        ));
        $copiedfile = get_file_storage()->get_file(
            \context_user::instance($destinationuser->id)->id,
            'block_exaport',
            'item_content_file',
            $map[$block->id],
            '/nested/',
            'structured.txt'
        );
        $this->assertNotFalse($copiedfile);
        $this->assertSame($structuredfile->get_contenthash(), $copiedfile->get_contenthash());
        $this->assertSame((int)$destinationuser->id, (int)$copiedfile->get_userid());
    }

    public function test_structured_file_failure_rolls_back_destination_copy(): void {
        global $DB;
        $this->resetAfterTest();
        $sourceuser = $this->getDataGenerator()->create_user();
        $destinationuser = $this->getDataGenerator()->create_user();
        $source = $this->create_item($sourceuser->id, 'file');
        $block = \block_exaport_create_file_content_block($source->id, 'File');
        $sourcefile = $this->create_block_file(
            $sourceuser->id,
            'item_content_file',
            $block->id,
            'source.txt',
            'source remains'
        );
        $destinationid = 0;

        try {
            (function() use ($DB, $destinationuser, $source, &$destinationid): void {
                $transaction = $DB->start_delegated_transaction();
                try {
                    $destination = $this->create_item($destinationuser->id, 'file');
                    $destinationid = $destination->id;
                    \block_exaport_copy_item_content(
                        $source,
                        $destination,
                        static function(array $fileinfo, \stored_file $file): void {
                            throw new \RuntimeException('Injected structured-file copy failure');
                        }
                    );
                    $transaction->allow_commit();
                } catch (\Throwable $exception) {
                    $transaction->rollback($exception);
                }
            })();
            $this->fail('Injected structured-file copy failure was accepted');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected structured-file copy failure', $exception->getMessage());
        }

        $this->assertFalse($DB->record_exists('block_exaportitem', ['id' => $destinationid]));
        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $destinationid]));
        $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
        $this->assertSame('source remains', $sourcefile->get_content());
    }

    /** Convenience wrapper which keeps assertions readable across Moodle versions. */
    private function get_count(string $table, array $conditions): int {
        global $DB;
        return $DB->count_records($table, $conditions);
    }
}
