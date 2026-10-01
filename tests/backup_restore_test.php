<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once(__DIR__ . '/../db/upgradelib.php');

/**
 * Regression tests for the legacy-content compatibility phase used after restore.
 *
 * The fixtures model the state immediately after Moodle has restored the archive's files and item
 * records. The restore structure step then invokes the same transactional converter tested here.
 *
 * @covers ::block_exaport_migrate_legacy_item_content
 */
final class backup_restore_test extends \advanced_testcase {

    public function test_backup_and_restore_definitions_include_structured_content(): void {
        $backup = file_get_contents(__DIR__ . '/../backup/moodle2/backup_exaport_stepslib.php');
        $restore = file_get_contents(__DIR__ . '/../backup/moodle2/restore_exaport_stepslib.php');
        $backuptask = file_get_contents(__DIR__ . '/../backup/moodle2/backup_exaport_block_task.class.php');
        $restoretask = file_get_contents(__DIR__ . '/../backup/moodle2/restore_exaport_block_task.class.php');

        $this->assertStringContainsString("new backup_nested_element('content_block'", $backup);
        foreach (['item_content_file', 'item_content_text'] as $filearea) {
            $this->assertStringContainsString("annotate_files('block_exaport', '{$filearea}', 'id')", $backup);
            $this->assertStringContainsString("add_related_files('block_exaport', '{$filearea}'", $restore);
            $this->assertStringContainsString("'{$filearea}'", $backuptask);
            $this->assertStringContainsString("'{$filearea}'", $restoretask);
        }
        $this->assertStringContainsString("add_related_files('block_exaport', 'item_file'", $restore);
        $this->assertStringContainsString('migrate_legacy_item_content($item)', $restore);
    }

    /** Create an item in the state produced from an old backup. */
    private function item(int $userid, string $url = '', string $attachment = ''): \stdClass {
        global $DB;
        $item = (object)[
            'userid' => $userid, 'type' => 'note', 'name' => 'Restored item', 'url' => $url,
            'intro' => '', 'attachment' => $attachment, 'timecreated' => 100,
            'timemodified' => 200, 'courseid' => 0, 'shareall' => 0, 'externaccess' => 0,
            'externcomment' => 0,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    /** Create a file as restored from an old item_file archive entry. */
    private function legacy_file(\stdClass $item, string $path, string $name, string $content): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($item->userid)->id,
            'component' => 'block_exaport', 'filearea' => 'item_file', 'itemid' => $item->id,
            'filepath' => $path, 'filename' => $name, 'userid' => $item->userid,
        ], $content);
    }

    /** Assert that no legacy runtime content remains after a successful restore conversion. */
    private function assert_legacy_content_removed(\stdClass $item): void {
        global $DB;
        $parent = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
        $this->assertSame('', $parent->url);
        $this->assertSame('', $parent->attachment);
        $this->assertSame([], get_file_storage()->get_area_files(
            \context_user::instance($item->userid)->id,
            'block_exaport', 'item_file', $item->id, 'id', false
        ));
    }

    public function test_old_and_structured_backup_content_is_restored_as_structured_blocks(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();

        $urlonly = $this->item($owner->id, 'https://restore.example/url', 'stale-url-name');
        $fileonly = $this->item($owner->id, '', 'old-file-name');
        $file = $this->legacy_file($fileonly, '/deep/path/', 'file-only.txt', 'file only');
        $combined = $this->item($owner->id, 'https://restore.example/combined', 'combined-name');
        $combinedfile = $this->legacy_file($combined, '/one/two/', 'combined.txt', 'combined');
        $structured = $this->item($owner->id);
        $structuredblock = (object)[
            'itemid' => $structured->id, 'type' => 'text', 'sortorder' => 4, 'title' => 'Kept',
            'content' => '<p>Structured</p>', 'contentformat' => FORMAT_HTML, 'url' => '',
            'timecreated' => 100, 'timemodified' => 200,
        ];
        $structuredblock->id = (int)$DB->insert_record('block_exaportitemblock', $structuredblock);
        $structuredfile = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id, 'component' => 'block_exaport',
            'filearea' => 'item_content_text', 'itemid' => $structuredblock->id,
            'filepath' => '/images/', 'filename' => 'existing.png', 'userid' => $owner->id,
        ], 'image');

        foreach ([$urlonly, $fileonly, $combined, $structured] as $item) {
            \block_exaport_migrate_legacy_item_content($item);
            $this->assert_legacy_content_removed($item);
        }

        $this->assertSame(['link'], array_column(array_values($DB->get_records(
            'block_exaportitemblock', ['itemid' => $urlonly->id], 'sortorder ASC')), 'type'));
        $fileblocks = array_values($DB->get_records('block_exaportitemblock', ['itemid' => $fileonly->id]));
        $this->assertSame(['file'], array_column($fileblocks, 'type'));
        $copy = get_file_storage()->get_file(\context_user::instance($owner->id)->id,
            'block_exaport', 'item_content_file', $fileblocks[0]->id, '/deep/path/', 'file-only.txt');
        $this->assertNotFalse($copy);
        $this->assertSame($file->get_contenthash(), $copy->get_contenthash());

        $combinedblocks = array_values($DB->get_records(
            'block_exaportitemblock', ['itemid' => $combined->id], 'sortorder ASC'));
        $this->assertSame(['link', 'file'], array_column($combinedblocks, 'type'));
        $combinedcopy = get_file_storage()->get_file(\context_user::instance($owner->id)->id,
            'block_exaport', 'item_content_file', $combinedblocks[1]->id, '/one/two/', 'combined.txt');
        $this->assertNotFalse($combinedcopy);
        $this->assertSame($combinedfile->get_contenthash(), $combinedcopy->get_contenthash());

        $this->assertSame(1, $DB->count_records('block_exaportitemblock', ['itemid' => $structured->id]));
        $this->assertNotFalse(get_file_storage()->get_file_by_id($structuredfile->get_id()));
    }

    public function test_failed_restored_file_conversion_rolls_back_parent_and_blocks(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id, 'https://restore.example/rollback', 'must-remain');
        $source = $this->legacy_file($item, '/nested/', 'source.txt', 'source');

        try {
            \block_exaport_migrate_legacy_item_content($item, static function(string $stage): void {
                if ($stage === 'file_copied') {
                    throw new \coding_exception('Injected restored-file failure');
                }
            });
            $this->fail('Injected restoration failure was ignored');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString((string)$item->id, $exception->getMessage());
        }

        $parent = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
        $this->assertSame('https://restore.example/rollback', $parent->url);
        $this->assertSame('must-remain', $parent->attachment);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertNotFalse(get_file_storage()->get_file_by_id($source->get_id()));
    }
}
