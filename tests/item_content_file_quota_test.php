<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/item_content_helpers.php');
require_once(__DIR__ . '/../lib/item_content_form.php');
require_once(__DIR__ . '/../db/upgradelib.php');

/**
 * Quota regressions through the standalone controller and dynamic-form service.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_content_file_quota_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('block_exaport_enable_interaction_competences', 0);
        set_config('block_exaport_userquota', 100);
        set_config('block_exaport_max_uploadfile_size', 100);
        set_config('block_exaport_multiple_files_in_item', 0);
    }

    /** Create an owned parent item. */
    private function item(int $userid): \stdClass {
        global $DB;
        $item = (object)['userid' => $userid, 'courseid' => SITEID, 'name' => 'Quota fixture',
            'type' => 'note', 'intro' => '<p>Description</p>', 'url' => '', 'attachment' => '',
            'categoryid' => 0, 'shareall' => 0, 'externaccess' => 0, 'externcomment' => 0,
            'timecreated' => 100, 'timemodified' => 101];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
    }

    /** Store deterministic bytes, including subdirectories when supplied. */
    private function file(int $userid, string $component, string $area, int $itemid,
            string $path, int $size): \stored_file {
        $filename = basename($path);
        $dirname = dirname($path);
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id, 'component' => $component,
            'filearea' => $area, 'itemid' => $itemid,
            'filepath' => $dirname === '.' ? '/' : '/' . $dirname . '/',
            'filename' => $filename, 'userid' => $userid,
        ], str_repeat(substr($filename, 0, 1), $size));
    }

    /** Read a block's content without including directory placeholders. */
    private function contents(\stdClass $item, int $blockid): array {
        $result = [];
        foreach (block_exaport_get_item_content_files((int)$item->userid, $blockid) as $file) {
            $result[ltrim($file->get_filepath(), '/') . $file->get_filename()] = $file->get_content();
        }
        ksort($result);
        return $result;
    }

    public static function submission_provider(): array {
        $cases = [
            'empty new draft' => [[], [], false],
            'below limit' => [[], ['new.txt' => 40], true],
            'exact limit' => [[], ['new.txt' => 50], true],
            'over limit addition' => [[], ['new.txt' => 51], false],
            'unchanged files are not counted twice' => [['old.txt' => 40], ['old.txt' => 40], true],
            'replacement at limit' => [['old.txt' => 40], ['old.txt' => 50], true],
            'replacement over limit' => [['old.txt' => 40], ['old.txt' => 51], false],
            'removal plus addition at limit' => [
                ['keep.txt' => 20, 'remove.txt' => 30], ['keep.txt' => 20, 'new.txt' => 30], true,
            ],
            'removal plus addition over limit' => [
                ['keep.txt' => 20, 'remove.txt' => 30], ['keep.txt' => 20, 'new.txt' => 31], false,
            ],
            'already over quota unchanged' => [['old.txt' => 60], ['old.txt' => 60], true],
            'already over quota reduced' => [['old.txt' => 60], ['old.txt' => 59], true],
            'already over quota equal replacement' => [['old.txt' => 60], ['new.txt' => 60], true],
            'already over quota growth' => [['old.txt' => 60], ['old.txt' => 61], false],
            'remove final file while over quota' => [['old.txt' => 60], [], true],
            'grandfathered oversized nested file' => [
                ['nested/old.txt' => 200], ['nested/old.txt' => 200], true,
            ],
            'grandfathered file count above current configuration' => [
                ['a.txt' => 5, 'b.txt' => 5, 'c.txt' => 5], ['a.txt' => 5, 'b.txt' => 5, 'c.txt' => 5], true,
            ],
            'new oversized replacement rejected without discarding old file' => [
                ['old.txt' => 200], ['old.txt' => 150], false,
            ],
        ];
        $result = [];
        foreach (['standalone', 'dynamic'] as $flow) {
            foreach ($cases as $name => $case) {
                $result[$flow . ': ' . $name] = array_merge([$flow], $case);
            }
        }
        return $result;
    }

    /**
     * @dataProvider submission_provider
     */
    public function test_submission_accounting_and_preservation(string $flow, array $existing,
            array $submitted, bool $accepted): void {
        global $DB, $PAGE;
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/blocks/exaport/item_content_file.php');
        $item = $this->item((int)$owner->id);
        $otheritem = $this->item((int)$owner->id);
        $otherblock = block_exaport_create_file_content_block((int)$otheritem->id, 'Other block');
        // The quota is across all Exaport areas, not just the edited block or item.
        $this->file($owner->id, 'block_exaport', 'item_content_file', $otherblock->id, 'other.txt', 20);
        $this->file($owner->id, 'block_exaport', 'item_content_text', 9001, 'embedded.txt', 10);
        $this->file($owner->id, 'block_exaport', 'item_file', $otheritem->id, 'legacy.txt', 10);
        $this->file($owner->id, 'block_exaport', 'item_iconfile', $otheritem->id, 'thumbnail.txt', 10);
        $this->file($owner->id, 'user', 'private', 0, 'not-exaport.txt', 1000);
        $block = $existing ? block_exaport_create_file_content_block((int)$item->id, 'Original title') : null;
        foreach ($existing as $name => $size) {
            $this->file($owner->id, 'block_exaport', 'item_content_file', $block->id, $name, $size);
        }
        $beforeblocks = $DB->get_records('block_exaportitemblock');
        $beforefiles = $DB->get_records('files', ['component' => 'block_exaport']);
        $draftid = 0;
        $options = block_exaport_item_content_file_options($item, $block);
        file_prepare_draft_area($draftid, \context_user::instance($owner->id)->id,
            'block_exaport', 'item_content_file', $block->id ?? 0, $options);
        foreach (get_file_storage()->get_area_files(\context_user::instance($owner->id)->id,
                'user', 'draft', $draftid, 'id', false) as $file) {
            $name = ltrim($file->get_filepath(), '/') . $file->get_filename();
            if (!isset($submitted[$name]) || $submitted[$name] !== (int)$file->get_filesize()) {
                $file->delete();
            }
        }
        foreach ($submitted as $name => $size) {
            if (!isset($existing[$name]) || $existing[$name] !== $size) {
                $this->file($owner->id, 'user', 'draft', $draftid, $name, $size);
            }
        }
        $data = ['courseid' => SITEID, 'itemid' => $item->id, 'blockid' => $block->id ?? 0,
            'title' => 'Edited title', 'files_filemanager' => $draftid,
            'contenttype' => 'file', 'operation' => 'save'];

        if ($flow === 'standalone') {
            $result = $this->submit_standalone($data);
        } else {
            $data = \block_exaport\form\item_content::mock_ajax_submit($data);
            $response = \core_form\external\dynamic_form::execute(
                \block_exaport\form\item_content::class, http_build_query($data)
            );
            $result = ['accepted' => $response['submitted'], 'html' => $response['html'] ?? ''];
        }
        $this->assertSame($accepted, $result['accepted']);
        $this->assertEquals($item, $DB->get_record('block_exaportitem', ['id' => $item->id]));
        if (!$accepted) {
            $this->assertEquals($beforeblocks, $DB->get_records('block_exaportitemblock'), 'No orphan or altered blocks');
            $this->assertEquals($beforefiles, $DB->get_records('files', ['component' => 'block_exaport']),
                'Rejected submissions preserve every stored file and its ID');
            $error = block_exaport_item_content_file_submission_error($item, $block, $draftid);
            $this->assertNotNull($error);
            $this->assertStringContainsString(get_string($error[0], $error[1], $error[2]), $result['html']);
            return;
        }
        $blocks = block_exaport_get_item_content_blocks((int)$item->id);
        if (!$submitted) {
            $this->assertSame([], $blocks, 'Removing the final file removes its block');
            $this->assertSame([], $this->contents($item, (int)$block->id));
            return;
        }
        $this->assertCount(1, $blocks);
        $this->assertSame('Edited title', $blocks[0]->title);
        if ($block) {
            $this->assertSame((int)$block->id, (int)$blocks[0]->id);
        }
        $expected = [];
        foreach ($submitted as $name => $size) {
            $expected[$name] = str_repeat(substr(basename($name), 0, 1), $size);
        }
        ksort($expected);
        $this->assertSame($expected, $this->contents($item, (int)$blocks[0]->id));
    }

    /** Execute the actual standalone controller; CLI redirects throw after commit. */
    private function submit_standalone(array $data): array {
        global $CFG, $DB, $USER, $PAGE, $COURSE, $OUTPUT, $SESSION;
        $oldget = $_GET;
        $oldpost = $_POST;
        $_GET = [];
        \block_exaport_item_content_file_form::mock_submit($data);
        $accepted = false;
        ob_start();
        try {
            include(__DIR__ . '/../item_content_file.php');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode);
            $accepted = true;
        } finally {
            $html = ob_get_clean();
            $_GET = $oldget;
            $_POST = $oldpost;
        }
        return ['accepted' => $accepted, 'html' => $html];
    }

    public function test_accounting_uses_destination_context_and_submitters_draft(): void {
        $owner = $this->getDataGenerator()->create_user();
        $submitter = $this->getDataGenerator()->create_user();
        $this->setUser($submitter);
        $item = $this->item($owner->id);
        $block = block_exaport_create_file_content_block((int)$item->id, 'Original');
        $this->file($owner->id, 'block_exaport', 'item_content_file', $block->id, 'old.txt', 20);
        $this->file($owner->id, 'block_exaport', 'item_file', $item->id, 'other.txt', 50);
        $this->file($submitter->id, 'block_exaport', 'item_file', $item->id, 'wrong-owner.txt', 1000);
        $draftid = file_get_unused_draft_itemid();
        $this->file($owner->id, 'user', 'draft', $draftid, 'wrong-draft.txt', 1000);
        $this->file($submitter->id, 'user', 'draft', $draftid, 'new.txt', 50);
        $this->assertNull(block_exaport_item_content_file_submission_error($item, $block, $draftid));
        $this->file($submitter->id, 'user', 'draft', $draftid, 'extra.txt', 1);
        $error = block_exaport_item_content_file_submission_error($item, $block, $draftid);
        $this->assertSame('userquotalimit', $error[0]);
    }

    public function test_dynamic_save_rechecks_quota_before_creating_a_block(): void {
        global $DB, $PAGE;
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/blocks/exaport/item_content_file.php');
        $item = $this->item($owner->id);
        $draftid = file_get_unused_draft_itemid();
        $this->file($owner->id, 'user', 'draft', $draftid, 'new.txt', 50);
        $data = \block_exaport\form\item_content::mock_ajax_submit([
            'courseid' => SITEID, 'itemid' => $item->id, 'blockid' => 0, 'title' => 'New block',
            'files_filemanager' => $draftid, 'contenttype' => 'file', 'operation' => 'save',
        ]);
        $form = new \block_exaport\form\item_content(null, null, 'post', '', [], true, $data, true);
        $this->assertNotNull($form->get_data(), 'The original draft passes form validation');
        // Another upload consumed quota after validation; the save guard must see it.
        $this->file($owner->id, 'block_exaport', 'item_file', $item->id, 'other.txt', 60);
        $beforefiles = $DB->get_records('files', ['component' => 'block_exaport']);
        try {
            $form->process_dynamic_submission();
            $this->fail('The pre-save check must reject the now-over-limit addition');
        } catch (\moodle_exception $exception) {
            $this->assertSame('userquotalimit', $exception->errorcode);
        }
        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertEquals($beforefiles, $DB->get_records('files', ['component' => 'block_exaport']));
    }

    public function test_zero_quota_allows_zero_bytes_but_no_new_bytes(): void {
        set_config('block_exaport_userquota', 0);
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $item = $this->item($owner->id);
        $draftid = file_get_unused_draft_itemid();
        $this->file($owner->id, 'user', 'draft', $draftid, 'empty.txt', 0);
        $this->assertNull(block_exaport_item_content_file_submission_error($item, null, $draftid));
        $this->file($owner->id, 'user', 'draft', $draftid, 'extra.txt', 1);
        $error = block_exaport_item_content_file_submission_error($item, null, $draftid);
        $this->assertSame('userquotalimit', $error[0]);
    }

    public function test_migration_does_not_apply_upload_quota_or_size_restrictions(): void {
        set_config('block_exaport_userquota', 1);
        set_config('block_exaport_max_uploadfile_size', 1);
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $original = $this->file($owner->id, 'block_exaport', 'item_file', $item->id, 'legacy.txt', 200);
        block_exaport_migrate_legacy_item_content($item);
        $blocks = block_exaport_get_item_content_blocks((int)$item->id);
        $this->assertCount(1, $blocks);
        $this->assertSame(['legacy.txt' => $original->get_content()], $this->contents($item, (int)$blocks[0]->id));
    }
}
