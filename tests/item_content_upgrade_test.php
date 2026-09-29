<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../db/upgradelib.php');

/**
 * Tests for migration of item-level legacy content.
 *
 * @covers ::block_exaport_migrate_legacy_item_content
 */
final class item_content_upgrade_test extends \advanced_testcase {
    /** Create a legacy item. */
    private function create_item(int $userid, string $type = 'note', string $url = '',
            string $attachment = '', int $timecreated = 0, int $timemodified = 0): \stdClass {
        global $DB;
        $item = (object)[
            'userid' => $userid, 'type' => $type, 'name' => 'Upgrade fixture', 'url' => $url,
            'intro' => '', 'attachment' => $attachment, 'timecreated' => $timecreated,
            'timemodified' => $timemodified, 'courseid' => 0, 'shareall' => 0,
            'externaccess' => 0, 'externcomment' => 0,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    /** Create a real legacy File API file. */
    private function create_legacy_file(\stdClass $item, string $filename, string $content,
            string $filepath = '/', string $mimetype = 'text/plain'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($item->userid)->id,
            'component' => 'block_exaport', 'filearea' => 'item_file', 'itemid' => $item->id,
            'filepath' => $filepath, 'filename' => $filename, 'userid' => $item->userid,
            'mimetype' => $mimetype, 'source' => 'legacy-source', 'author' => 'Legacy Author',
            'license' => 'allrightsreserved', 'timecreated' => 1234, 'timemodified' => 2345,
        ], $content);
    }

    public function test_empty_and_false_urls_create_no_blocks_and_clear_compatibility_values(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        foreach (['', '   ', 'false'] as $url) {
            $item = $this->create_item($user->id, 'note', $url);
            \block_exaport_migrate_legacy_item_content($item);
            $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
            $this->assertSame('', $DB->get_field('block_exaportitem', 'url', ['id' => $item->id]));
        }
    }

    public function test_url_is_exactly_preserved_for_any_parent_type_and_timestamp_policy(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $url = '  unusual+scheme:value  ';
        $item = $this->create_item($user->id, 'file', $url, '', 111, 222);

        $result = \block_exaport_migrate_legacy_item_content($item);
        $block = $DB->get_record('block_exaportitemblock', ['id' => $result['linkblockid']], '*', MUST_EXIST);
        $parent = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
        $this->assertSame($url, $block->url);
        $this->assertSame('link', $block->type);
        $this->assertSame(111, (int)$block->timecreated);
        $this->assertSame(222, (int)$block->timemodified);
        $this->assertSame('file', $parent->type);
        $this->assertSame('', $parent->url);
        $this->assertSame(222, (int)$parent->timemodified);
    }

    public function test_multiple_nested_files_share_one_block_and_preserve_metadata(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'note', '', '999');
        $first = $this->create_legacy_file($item, 'image.png', 'image bytes', '/nested/', 'image/png');
        $second = $this->create_legacy_file($item, 'document.pdf', 'pdf bytes', '/', 'application/pdf');

        $result = \block_exaport_migrate_legacy_item_content($item);
        $contextid = \context_user::instance($user->id)->id;
        $files = get_file_storage()->get_area_files($contextid, 'block_exaport', 'item_content_file',
            $result['fileblockid'], 'filepath ASC, filename ASC', false);
        $this->assertCount(2, $files);
        $copy = get_file_storage()->get_file($contextid, 'block_exaport', 'item_content_file',
            $result['fileblockid'], '/nested/', 'image.png');
        $this->assertNotFalse($copy);
        $this->assertSame($first->get_contenthash(), $copy->get_contenthash());
        $this->assertSame($first->get_filesize(), $copy->get_filesize());
        $this->assertSame('image/png', $copy->get_mimetype());
        $this->assertSame('legacy-source', $copy->get_source());
        $this->assertSame('Legacy Author', $copy->get_author());
        $this->assertSame('allrightsreserved', $copy->get_license());
        $this->assertSame((int)$user->id, (int)$copy->get_userid());
        $this->assertSame('/nested/', $copy->get_filepath());
        $this->assertSame('image.png', $copy->get_filename());
        $this->assertSame($second->get_contenthash(), array_values($files)[0]->get_contenthash());
        $this->assertSame([], get_file_storage()->get_area_files(
            $contextid, 'block_exaport', 'item_file', $item->id, 'id', false));
        $this->assertSame('', $DB->get_field('block_exaportitem', 'attachment', ['id' => $item->id]));
        $this->assertSame('note', $DB->get_field('block_exaportitem', 'type', ['id' => $item->id]));
    }

    public function test_combined_content_appends_after_sparse_equal_orders_without_changing_existing(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'note', 'https://example.test/path');
        foreach ([2, 7, 7] as $sortorder) {
            $DB->insert_record('block_exaportitemblock', (object)[
                'itemid' => $item->id, 'type' => 'text', 'sortorder' => $sortorder, 'title' => '',
                'content' => 'kept', 'contentformat' => FORMAT_HTML, 'url' => '',
                'timecreated' => 1, 'timemodified' => 2,
            ]);
        }
        $this->create_legacy_file($item, 'one.txt', 'one');

        \block_exaport_migrate_legacy_item_content($item);
        $blocks = array_values($DB->get_records('block_exaportitemblock', ['itemid' => $item->id], 'id ASC'));
        $this->assertSame([2, 7, 7, 8, 9], array_map(fn($block) => (int)$block->sortorder, $blocks));
        $this->assertSame(['text', 'text', 'text', 'link', 'file'], array_column($blocks, 'type'));
        $this->assertSame(['kept', 'kept', 'kept'], array_column(array_slice($blocks, 0, 3), 'content'));
    }

    public function test_rerun_is_a_noop_and_other_areas_and_items_are_isolated(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'link', 'https://example.test');
        $other = $this->create_item($user->id);
        $this->create_legacy_file($item, 'migrate.txt', 'migrate');
        $otherfile = $this->create_legacy_file($other, 'keep.txt', 'keep');
        $contextid = \context_user::instance($user->id)->id;
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid, 'component' => 'block_exaport', 'filearea' => 'item_iconfile',
            'itemid' => $item->id, 'filepath' => '/', 'filename' => 'keep.png', 'userid' => $user->id,
        ], 'icon');

        \block_exaport_migrate_legacy_item_content($item);
        \block_exaport_migrate_legacy_item_content($item);
        $this->assertSame(2, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertNotFalse(get_file_storage()->get_file_by_id($otherfile->get_id()));
        $this->assertCount(1, get_file_storage()->get_area_files(
            $contextid, 'block_exaport', 'item_iconfile', $item->id, 'id', false));
    }

    public function test_stale_attachment_and_directory_only_area_create_no_file_block(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'file', '', '123456');
        get_file_storage()->create_directory(\context_user::instance($user->id)->id,
            'block_exaport', 'item_file', $item->id, '/empty/');

        \block_exaport_migrate_legacy_item_content($item);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertSame('', $DB->get_field('block_exaportitem', 'attachment', ['id' => $item->id]));
        $this->assertSame([], get_file_storage()->get_area_files(\context_user::instance($user->id)->id,
            'block_exaport', 'item_file', $item->id, 'id', false));
    }
}
