<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../db/upgradelib.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

/**
 * Tests for migration of item-level legacy content.
 *
 * @covers ::block_exaport_migrate_legacy_item_content
 */
final class item_content_upgrade_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

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

    /** Insert a structured block with deterministic fields. */
    private function create_block(\stdClass $item, string $type, int $sortorder, string $url = ''): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $item->id, 'type' => $type, 'sortorder' => $sortorder,
            'title' => 'Existing', 'content' => 'Existing body', 'contentformat' => FORMAT_HTML,
            'url' => $url, 'timecreated' => 101, 'timemodified' => 202,
        ]);
    }

    /** Return a record as an array without DB-driver-specific object identity. */
    private function record_array(string $table, int $id): array {
        global $DB;
        return (array)$DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
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

    public function test_parent_type_and_content_matrix_loses_no_content(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        foreach (['note', 'link', 'file'] as $type) {
            foreach (['none', 'url', 'files', 'both'] as $contentcase) {
                $hasurl = in_array($contentcase, ['url', 'both'], true);
                $hasfiles = in_array($contentcase, ['files', 'both'], true);
                $item = $this->create_item($user->id, $type, $hasurl ? 'custom:value' : '', 'stale', 51, 52);
                if ($hasfiles) {
                    $this->create_legacy_file($item, "{$type}-{$contentcase}.txt", "{$type}:{$contentcase}");
                }

                \block_exaport_migrate_legacy_item_content($item);
                $blocks = array_values($DB->get_records('block_exaportitemblock',
                    ['itemid' => $item->id], 'sortorder ASC'));
                $expectedtypes = array_merge($hasurl ? ['link'] : [], $hasfiles ? ['file'] : []);
                $this->assertSame($expectedtypes, array_column($blocks, 'type'), "{$type}/{$contentcase}");
                $expectedorders = $blocks ? range(0, count($blocks) - 1) : [];
                $this->assertSame($expectedorders,
                    $blocks ? array_map(fn($block) => (int)$block->sortorder, $blocks) : [], "{$type}/{$contentcase}");
                $parent = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
                $this->assertSame($type, $parent->type);
                $this->assertSame('', $parent->url);
                $this->assertSame('', $parent->attachment);
                $this->assertSame(52, (int)$parent->timemodified);
            }
        }
    }

    public function test_existing_structured_blocks_and_files_are_untouched_and_not_deduplicated(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'note', 'same:value');
        $linkid = $this->create_block($item, 'link', 4, 'same:value');
        $fileid = $this->create_block($item, 'file', 4);
        $textid = $this->create_block($item, 'text', 9);
        $contextid = \context_user::instance($user->id)->id;
        $existingfile = get_file_storage()->create_file_from_string([
            'contextid' => $contextid, 'component' => 'block_exaport', 'filearea' => 'item_content_file',
            'itemid' => $fileid, 'filepath' => '/same/', 'filename' => 'duplicate.txt', 'userid' => $user->id,
        ], 'identical bytes');
        $editorfile = get_file_storage()->create_file_from_string([
            'contextid' => $contextid, 'component' => 'block_exaport', 'filearea' => 'item_content_text',
            'itemid' => $textid, 'filepath' => '/', 'filename' => 'inline.png', 'userid' => $user->id,
        ], 'inline');
        $legacyfile = $this->create_legacy_file($item, 'duplicate.txt', 'identical bytes', '/same/');
        $before = [$linkid => $this->record_array('block_exaportitemblock', $linkid),
            $fileid => $this->record_array('block_exaportitemblock', $fileid),
            $textid => $this->record_array('block_exaportitemblock', $textid)];

        $result = \block_exaport_migrate_legacy_item_content($item);
        foreach ($before as $id => $record) {
            $this->assertSame($record, $this->record_array('block_exaportitemblock', $id));
        }
        $this->assertNotSame($linkid, $result['linkblockid']);
        $this->assertNotSame($fileid, $result['fileblockid']);
        $this->assertNotFalse(get_file_storage()->get_file_by_id($existingfile->get_id()));
        $this->assertNotFalse(get_file_storage()->get_file_by_id($editorfile->get_id()));
        $copy = get_file_storage()->get_file($contextid, 'block_exaport', 'item_content_file',
            $result['fileblockid'], '/same/', 'duplicate.txt');
        $this->assertNotFalse($copy);
        $this->assertSame($legacyfile->get_contenthash(), $copy->get_contenthash());
        $this->assertSame([10, 11], [(int)$DB->get_field('block_exaportitemblock', 'sortorder',
            ['id' => $result['linkblockid']]), (int)$DB->get_field('block_exaportitemblock', 'sortorder',
            ['id' => $result['fileblockid']])]);
    }

    public function test_view_share_category_and_comment_relationships_remain_exactly_unchanged(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $cohort = $this->getDataGenerator()->create_cohort();
        $item = $this->create_item($owner->id, 'link', 'https://shared.example/', '', 10, 20);
        $this->create_legacy_file($item, 'shared.pdf', 'shared contents', '/', 'application/pdf');
        $categoryid = $this->create_category($owner, 'Migration category');
        $viewid = $this->create_view($owner, 1);
        $ids = [];
        $ids['block_exaportitemcate'][] = (int)$DB->insert_record('block_exaportitemcate',
            (object)['itemid' => $item->id, 'cateid' => $categoryid]);
        $ids['block_exaportitemshar'][] = (int)$DB->insert_record('block_exaportitemshar', (object)[
            'itemid' => $item->id, 'userid' => $recipient->id, 'original' => $owner->id,
            'courseid' => $course->id, 'notify' => 1, 'timestart' => 100, 'timeend' => 200,
        ]);
        $ids['block_exaportitemgroupshar'][] = (int)$DB->insert_record('block_exaportitemgroupshar',
            (object)['itemid' => $item->id, 'groupid' => $cohort->id]);
        $ids['block_exaportitemcomm'][] = (int)$DB->insert_record('block_exaportitemcomm', (object)[
            'itemid' => $item->id, 'userid' => $recipient->id, 'entry' => 'Do not lose me', 'timemodified' => 300,
        ]);
        foreach ([1, 2] as $position) {
            $ids['block_exaportviewblock'][] = (int)$DB->insert_record('block_exaportviewblock', (object)[
                'viewid' => $viewid, 'positionx' => $position, 'positiony' => 1,
                'type' => 'item', 'itemid' => $item->id, 'block_title' => "Position {$position}",
            ]);
        }
        $ids['block_exaportviewshar'][] = (int)$DB->insert_record('block_exaportviewshar',
            (object)['viewid' => $viewid, 'userid' => $recipient->id, 'notify' => 1]);
        $ids['block_exaportviewgroupshar'][] = (int)$DB->insert_record('block_exaportviewgroupshar',
            (object)['viewid' => $viewid, 'groupid' => $cohort->id]);
        $before = [];
        foreach ($ids as $table => $recordids) {
            foreach ($recordids as $id) {
                $before[$table][$id] = $this->record_array($table, $id);
            }
        }

        \block_exaport_migrate_legacy_item_content($item);
        foreach ($before as $table => $records) {
            foreach ($records as $id => $record) {
                $this->assertSame($record, $this->record_array($table, $id), "Changed {$table}/{$id}");
            }
        }
        $this->assertSame(2, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
    }

    public function test_large_file_set_ignores_interactive_limit_and_preserves_every_file(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'file');
        $sources = [];
        for ($index = 0; $index < 25; $index++) {
            $path = $index % 2 ? '/nested/' : '/';
            $sources[] = $this->create_legacy_file($item, "file-{$index}.dat", "bytes-{$index}", $path,
                $index % 3 ? 'application/octet-stream' : 'image/png');
        }

        $result = \block_exaport_migrate_legacy_item_content($item);
        $contextid = \context_user::instance($user->id)->id;
        $this->assertSame(25, $result['filecount']);
        $destinations = get_file_storage()->get_area_files($contextid, 'block_exaport', 'item_content_file',
            $result['fileblockid'], 'id ASC', false);
        $this->assertCount(25, $destinations);
        foreach ($sources as $source) {
            $destination = get_file_storage()->get_file($contextid, 'block_exaport', 'item_content_file',
                $result['fileblockid'], $source->get_filepath(), $source->get_filename());
            $this->assertNotFalse($destination);
            $this->assertSame($source->get_contenthash(), $destination->get_contenthash());
            $this->assertSame($source->get_filesize(), $destination->get_filesize());
            $this->assertSame($source->get_mimetype(), $destination->get_mimetype());
            $this->assertSame($source->get_source(), $destination->get_source());
            $this->assertSame($source->get_author(), $destination->get_author());
            $this->assertSame($source->get_license(), $destination->get_license());
            $this->assertSame($source->get_timecreated(), $destination->get_timecreated());
            $this->assertSame($source->get_timemodified(), $destination->get_timemodified());
            $this->assertSame((int)$user->id, (int)$destination->get_userid());
        }
    }

    public function test_logged_in_user_never_changes_owner_context_selection(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $item = $this->create_item($owner->id, 'note');
        $this->create_legacy_file($item, 'owner.txt', 'owner data');

        $result = \block_exaport_migrate_legacy_item_content($item);
        $ownercontext = \context_user::instance($owner->id);
        $othercontext = \context_user::instance($other->id);
        $this->assertNotFalse(get_file_storage()->get_file($ownercontext->id, 'block_exaport',
            'item_content_file', $result['fileblockid'], '/', 'owner.txt'));
        $this->assertSame([], get_file_storage()->get_area_files($othercontext->id, 'block_exaport',
            'item_content_file', $result['fileblockid'], 'id', false));
    }

    public function test_timestamp_fallbacks_use_modified_then_migration_time(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $modifiedonly = $this->create_item($user->id, 'note', 'modified:value', '', 0, 654);
        $allzero = $this->create_item($user->id, 'note', 'clock:value', '', 0, 0);
        $before = time();

        $first = \block_exaport_migrate_legacy_item_content($modifiedonly);
        $second = \block_exaport_migrate_legacy_item_content($allzero);
        $after = time();
        $firstblock = $DB->get_record('block_exaportitemblock', ['id' => $first['linkblockid']], '*', MUST_EXIST);
        $secondblock = $DB->get_record('block_exaportitemblock', ['id' => $second['linkblockid']], '*', MUST_EXIST);
        $this->assertSame(654, (int)$firstblock->timecreated);
        $this->assertSame(654, (int)$firstblock->timemodified);
        $this->assertGreaterThanOrEqual($before, (int)$secondblock->timecreated);
        $this->assertLessThanOrEqual($after, (int)$secondblock->timecreated);
        $this->assertSame((int)$secondblock->timecreated, (int)$secondblock->timemodified);
    }

    public function test_missing_owner_context_fails_without_creating_a_link_block(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'note', 'private:value', 'kept');
        $context = \context_user::instance($user->id);
        $context->delete();
        \context_helper::reset_caches();

        try {
            \block_exaport_migrate_legacy_item_content($item);
            $this->fail('Item without an owner context was migrated');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString((string)$item->id, $exception->getMessage());
            $this->assertStringContainsString((string)$user->id, $exception->getMessage());
            $this->assertStringNotContainsString('private:value', $exception->getMessage());
        }
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertSame('private:value', $DB->get_field('block_exaportitem', 'url', ['id' => $item->id]));
        $this->assertSame('kept', $DB->get_field('block_exaportitem', 'attachment', ['id' => $item->id]));
    }

    public function test_deleted_owner_fails_even_when_a_context_record_remains(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'note', 'deleted-owner:value', 'kept');
        \context_user::instance($user->id);
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);

        try {
            \block_exaport_migrate_legacy_item_content($item);
            $this->fail('Item belonging to a deleted owner was migrated');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString((string)$item->id, $exception->getMessage());
            $this->assertStringContainsString((string)$user->id, $exception->getMessage());
            $this->assertStringNotContainsString('deleted-owner:value', $exception->getMessage());
        }
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertSame('deleted-owner:value', $DB->get_field('block_exaportitem', 'url', ['id' => $item->id]));
        $this->assertSame('kept', $DB->get_field('block_exaportitem', 'attachment', ['id' => $item->id]));
    }

    public function test_copy_failure_rolls_back_blocks_and_preserves_all_sources(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'note', 'https://rollback.example/', 'kept');
        $source = $this->create_legacy_file($item, 'source.txt', 'source');

        try {
            \block_exaport_migrate_legacy_item_content($item, static function(string $stage): void {
                if ($stage === 'file_copied') {
                    throw new \coding_exception('Injected copy failure');
                }
            });
            $this->fail('Injected copy failure was ignored');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString((string)$item->id, $exception->getMessage());
        }
        $parent = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
        $this->assertSame('https://rollback.example/', $parent->url);
        $this->assertSame('kept', $parent->attachment);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertNotFalse(get_file_storage()->get_file_by_id($source->get_id()));
    }

    public function test_verification_failure_rolls_back_and_keeps_source_area(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item($user->id, 'file', '', 'kept');
        $source = $this->create_legacy_file($item, 'verify.txt', 'verify');
        $contextid = \context_user::instance($user->id)->id;

        try {
            \block_exaport_migrate_legacy_item_content($item,
                static function(string $stage, $value) use ($contextid): void {
                    if ($stage === 'before_verification') {
                        get_file_storage()->delete_area_files(
                            $contextid, 'block_exaport', 'item_content_file', (int)$value);
                    }
                });
            $this->fail('Deleted destination passed verification');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString((string)$item->id, $exception->getMessage());
        }
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertNotFalse(get_file_storage()->get_file_by_id($source->get_id()));
        $this->assertSame('kept', $DB->get_field('block_exaportitem', 'attachment', ['id' => $item->id]));
    }

    public function test_batched_interruption_rerun_skips_completed_items_without_duplicates(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $items = [];
        for ($index = 0; $index < 5; $index++) {
            $items[] = $this->create_item($user->id, 'note', "value:{$index}");
        }
        $failedid = $items[2]->id;
        try {
            \block_exaport_migrate_legacy_item_content_batches(2,
                static function(\stdClass $item) use ($failedid): void {
                    if ((int)$item->id === (int)$failedid) {
                        throw new \coding_exception('Injected batch interruption');
                    }
                    \block_exaport_migrate_legacy_item_content($item);
                });
            $this->fail('Injected batch interruption was ignored');
        } catch (\coding_exception $exception) {
            // Expected interruption after the first full batch committed.
        }
        $this->assertSame('', $DB->get_field('block_exaportitem', 'url', ['id' => $items[0]->id]));
        $this->assertSame('', $DB->get_field('block_exaportitem', 'url', ['id' => $items[1]->id]));
        $this->assertSame('value:2', $DB->get_field('block_exaportitem', 'url', ['id' => $items[2]->id]));
        $this->assertSame('value:3', $DB->get_field('block_exaportitem', 'url', ['id' => $items[3]->id]));

        \block_exaport_migrate_legacy_item_content_batches(2);
        foreach ($items as $item) {
            $this->assertSame(1, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
            $this->assertSame('', $DB->get_field('block_exaportitem', 'url', ['id' => $item->id]));
        }
    }
}
