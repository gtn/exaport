<?php
// This file is part of Moodle - http://moodle.org/

namespace block_exaport;

use block_exaport\local\item_content_audit;
use block_exaport\local\item_content_audit_output;

defined('MOODLE_INTERNAL') || die();

/** Tests for the read-only item-content audit. */
final class item_content_audit_test extends \advanced_testcase {
    /** Insert a minimal item. */
    private function item(int $userid, string $url = '', string $attachment = ''): \stdClass {
        global $DB;
        $record = (object)['userid' => $userid, 'type' => 'note', 'categoryid' => 0,
            'name' => 'Private audit fixture', 'url' => $url, 'intro' => '', 'attachment' => $attachment,
            'timecreated' => 1, 'timemodified' => 2, 'courseid' => 0, 'shareall' => 0,
            'externaccess' => 0, 'externcomment' => 0, 'isoez' => 0];
        $record->id = (int)$DB->insert_record('block_exaportitem', $record);
        return $record;
    }

    /** Insert a block. */
    private function block(int $itemid, string $type, string $url = '', string $content = ''): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportitemblock', (object)['itemid' => $itemid,
            'type' => $type, 'sortorder' => 0, 'title' => '', 'content' => $content,
            'contentformat' => FORMAT_HTML, 'url' => $url, 'timecreated' => 1, 'timemodified' => 2]);
    }

    /** Create a real File API record. */
    private function file(int $userid, string $area, int $itemid, string $name): \stored_file {
        return get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($userid)->id,
            'component' => 'block_exaport', 'filearea' => $area, 'itemid' => $itemid,
            'filepath' => '/', 'filename' => $name, 'userid' => $userid], 'audit bytes');
    }

    /** Find one result finding. */
    private function finding(array $result, string $code, ?string $severity = null): ?array {
        foreach ($result['findings'] as $finding) {
            if ($finding['code'] === $code && ($severity === null || $finding['severity'] === $severity)) {
                return $finding;
            }
        }
        return null;
    }

    public function test_empty_database_is_clean_and_information_does_not_change_status(): void {
        $this->resetAfterTest(true);
        $result = (new item_content_audit())->run();
        $this->assertSame('clean', $result['status']);
        $this->assertSame(0, $result['counts']['total_items']);
        $this->assertSame([], $result['findings']);
    }

    public function test_valid_mixed_structured_content_is_clean(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->block($item->id, 'text', '', '<p>Body</p>');
        $this->block($item->id, 'link', 'https://example.test/');
        $fileblock = $this->block($item->id, 'file');
        $this->file($owner->id, 'item_content_file', $fileblock, 'nested.pdf');

        $result = (new item_content_audit())->run();
        $this->assertSame('clean', $result['status']);
        $this->assertSame(3, $result['counts']['total_structured_blocks']);
        $this->assertSame(1, $result['counts']['total_structured_files']);
    }

    public function test_legacy_columns_have_stable_severities_and_do_not_expose_values(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $privateurl = 'https://secret.example/private-token';
        $meaningful = $this->item($owner->id, $privateurl, 'not-a-file-id');
        $this->item($owner->id, '   ');
        $this->item($owner->id, 'false');

        $result = (new item_content_audit())->run();
        $this->assertSame('error', $result['status']);
        $this->assertSame('error', $this->finding($result, 'legacy_url')['severity']);
        $this->assertSame(2, $this->finding($result, 'legacy_url_sentinel')['count']);
        $this->assertSame('warning', $this->finding($result, 'legacy_attachment')['severity']);
        $this->assertContains($meaningful->id, $this->finding($result, 'legacy_url')['sampleids']);
        $this->assertStringNotContainsString($privateurl, json_encode($result));
    }

    public function test_file_findings_cover_legacy_type_and_context_integrity(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $legacy = $this->file($other->id, 'item_file', $item->id, 'legacy.txt');
        $textblock = $this->block($item->id, 'text', '', 'Text');
        $wrongtype = $this->file($owner->id, 'item_content_file', $textblock, 'wrong.txt');
        $fileblock = $this->block($item->id, 'file');
        $wrongcontext = $this->file($other->id, 'item_content_file', $fileblock, 'owner.txt');

        $result = (new item_content_audit())->run();
        $this->assertNotNull($this->finding($result, 'legacy_item_file'));
        $this->assertNotNull($this->finding($result, 'legacy_file_context_mismatch'));
        $this->assertNotNull($this->finding($result, 'structured_file_wrong_block_type'));
        $this->assertNotNull($this->finding($result, 'structured_file_context_mismatch'));
        $this->assertContains($legacy->get_id(), $this->finding($result, 'legacy_item_file')['secondarysampleids']);
        $this->assertContains($wrongtype->get_id(), $this->finding($result, 'structured_file_wrong_block_type')['sampleids']);
        $this->assertContains($wrongcontext->get_id(), $this->finding($result,
            'structured_file_context_mismatch')['sampleids']);
    }

    public function test_block_warnings_and_directory_only_file_block(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->block($item->id, 'link');
        $emptyfile = $this->block($item->id, 'file');
        $this->block($item->id, 'future');
        get_file_storage()->create_directory(\context_user::instance($owner->id)->id,
            'block_exaport', 'item_content_file', $emptyfile, '/empty/');

        $result = (new item_content_audit())->run();
        $this->assertSame('warning', $result['status']);
        $this->assertNotNull($this->finding($result, 'empty_link_block'));
        $this->assertNotNull($this->finding($result, 'empty_file_block'));
        $this->assertNotNull($this->finding($result, 'unsupported_block_type'));
    }

    public function test_text_files_require_text_blocks_and_correct_context(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $text = $this->block($item->id, 'text', '', '@@PLUGINFILE@@/ok.png');
        $file = $this->block($item->id, 'file');
        $this->file($owner->id, 'item_content_text', $text, 'ok.png');
        $this->file($other->id, 'item_content_text', $file, 'bad.png');

        $result = (new item_content_audit())->run();
        $this->assertNotNull($this->finding($result, 'text_file_wrong_block_type'));
        $this->assertNotNull($this->finding($result, 'text_file_context_mismatch'));
    }

    public function test_filter_samples_counts_and_validation(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $first = $this->item($owner->id, 'first:private');
        $second = $this->item($owner->id, 'second:private');
        $third = $this->item($owner->id, 'third:private');

        $all = (new item_content_audit())->run(null, 2);
        $this->assertSame(3, $this->finding($all, 'legacy_url')['count']);
        $this->assertCount(2, $this->finding($all, 'legacy_url')['sampleids']);
        $filtered = (new item_content_audit())->run($second->id, 20);
        $this->assertSame(1, $this->finding($filtered, 'legacy_url')['count']);
        $this->assertSame([$second->id], $this->finding($filtered, 'legacy_url')['sampleids']);
        $this->assertNotContains($first->id, $this->finding($filtered, 'legacy_url')['sampleids']);
        $this->assertNotContains($third->id, $this->finding($filtered, 'legacy_url')['sampleids']);

        $this->expectException(\invalid_parameter_exception::class);
        (new item_content_audit())->run(null, 1001);
    }

    public function test_audit_is_read_only_and_formatters_are_privacy_safe(): void {
        global $DB;
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id, 'secret:value');
        $fileblock = $this->block($item->id, 'file');
        $this->file($owner->id, 'item_content_file', $fileblock, 'private-name.txt');
        $beforeitems = $DB->get_records('block_exaportitem');
        $beforeblocks = $DB->get_records('block_exaportitemblock');
        $beforefiles = $DB->get_records_select('files', 'component = ?', ['block_exaport']);
        $beforecontexts = $DB->count_records('context');
        $beforeconfig = $DB->get_records('config_plugins', ['plugin' => 'block_exaport']);

        $result = (new item_content_audit())->run();
        $this->assertEquals($beforeitems, $DB->get_records('block_exaportitem'));
        $this->assertEquals($beforeblocks, $DB->get_records('block_exaportitemblock'));
        $this->assertEquals($beforefiles, $DB->get_records_select('files', 'component = ?', ['block_exaport']));
        $this->assertSame($beforecontexts, $DB->count_records('context'));
        $this->assertEquals($beforeconfig, $DB->get_records('config_plugins', ['plugin' => 'block_exaport']));

        $json = json_encode(item_content_audit_output::with_metadata($result, '2026092902', 0));
        $human = item_content_audit_output::human($result);
        $this->assertJson($json);
        $this->assertStringContainsString('"auditformatversion":1', $json);
        $this->assertStringContainsString('Status: ERROR', $human);
        $this->assertStringNotContainsString('secret:value', $json . $human);
        $this->assertStringNotContainsString('private-name.txt', $json . $human);
        $this->assertSame(2, item_content_audit_output::exit_code($result));
        $this->assertSame(1, item_content_audit_output::exit_code(['status' => 'warning']));
        $this->assertSame(0, item_content_audit_output::exit_code(['status' => 'clean']));
    }
}
