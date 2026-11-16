<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');

use block_exaport\local\item_content_edit;
use block_exaport\local\item_linked_content;

/** Exercise pending block edits through the shared helpers and Moodle's real File API. */
final class item_content_edit_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('block_exaport_enable_interaction_competences', 0);
        set_config('block_exaport_multiple_files_in_item', 1);
        set_config('block_exaport_max_uploadfile_size', 1024 * 1024);
        set_config('block_exaport_userquota', 1024 * 1024);
    }

    private function item(): \stdClass {
        global $DB, $USER;
        $id = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'courseid' => SITEID,
            'name' => 'Pending helper fixture', 'type' => 'note', 'url' => '', 'attachment' => '']);
        return $DB->get_record('block_exaportitem', ['id' => $id], '*', MUST_EXIST);
    }

    private function draft(string $filename = 'document.txt', string $bytes = 'Original bytes', ?int $draftid = null): \stored_file {
        global $USER;
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid ?? file_get_unused_draft_itemid(), 'filepath' => '/', 'filename' => $filename,
            'userid' => $USER->id, 'source' => serialize((object)['source' => $filename]),
        ], $bytes);
    }

    private function state(string $type = 'file'): array {
        return ['key' => 'new-fixture', 'id' => 0, 'type' => $type, 'title' => 'Pending block', 'modified' => true];
    }

    private function draft_files(int $draftid): array {
        return item_content_edit::draft_files($draftid);
    }

    private function cv(): int {
        global $DB, $USER;
        $resumeid = $DB->insert_record('block_exaportresume', ['user_id' => $USER->id, 'cover' => 'Introduction']);
        return (int)$DB->insert_record('block_exaportresume_edu', ['resume_id' => $resumeid,
            'qualname' => 'Original qualification', 'startdate' => '2020', 'institution' => 'University', 'sorting' => 0]);
    }

    public function test_unsaved_file_block_can_be_applied_reopened_and_cancelled_without_database_rows(): void {
        global $DB;
        $file = $this->draft();
        $accepted = item_content_edit::normalise($this->state() + ['files_filemanager' => (int)$file->get_itemid()], null, null);
        $working = item_content_edit::prepare($accepted, null, null);
        $this->assertNotSame($accepted['files_filemanager'], $working['files_filemanager']);
        $this->assertSame($accepted['key'], $working['key']);
        $this->draft('second.txt', 'Second bytes', $working['files_filemanager']);
        $this->assertCount(1, $this->draft_files($accepted['files_filemanager']));
        $this->assertCount(2, $this->draft_files($working['files_filemanager']));
        $formdata = (object)($working + ['blockid' => 0, 'contenttype' => 'file']);
        $formdata->title = 'Edited before saving item';
        $applied = item_content_edit::normalise(item_content_edit::from_form($formdata, $accepted['key']), null, null);
        $this->assertSame('Edited before saving item', $applied['title']);
        $this->assertSame($accepted['key'], $applied['key']);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
    }

    public function test_initial_empty_modal_can_be_prepared_before_required_validation(): void {
        $seed = item_content_edit::normalise($this->state(), null, null, false, false);
        $working = item_content_edit::prepare($seed, null, null);
        $this->assertGreaterThan(0, $working['files_filemanager']);
        $this->expectException(\moodle_exception::class);
        item_content_edit::normalise($working, null, null);
    }

    public function test_file_edit_keeps_block_id_file_id_and_original_source_metadata(): void {
        global $USER;
        $item = $this->item();
        $upload = $this->draft();
        $created = item_content_edit::save($item, $this->state() + ['files_filemanager' => (int)$upload->get_itemid()]);
        $files = block_exaport_get_item_content_files((int)$USER->id, (int)$created->id);
        $original = reset($files);
        $savedstate = item_content_edit::from_record($created);
        $this->assertFalse($savedstate['modified']);
        $first = item_content_edit::prepare($savedstate, $item, $created);
        $first['modified'] = true;
        $accepted = item_content_edit::normalise($first, $item, $created);
        $fork = item_content_edit::prepare($accepted, $item, $created);
        $acceptedfiles = $this->draft_files($accepted['files_filemanager']);
        $forkfiles = $this->draft_files($fork['files_filemanager']);
        $this->assertSame(reset($acceptedfiles)->get_source(), reset($forkfiles)->get_source());
        $fork['title'] = 'Changed title';
        $updated = item_content_edit::save($item, $fork, $created);
        $this->assertSame((int)$created->id, (int)$updated->id);
        $files = block_exaport_get_item_content_files((int)$USER->id, (int)$created->id);
        $saved = reset($files);
        $this->assertSame($original->get_id(), $saved->get_id());
        $this->assertSame('document.txt', $saved->get_source());
        $this->assertSame('Original bytes', $saved->get_content());
    }

    public function test_foreign_draft_reference_cannot_supply_a_new_file_block(): void {
        $file = $this->draft();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        item_content_edit::normalise($this->state() + ['files_filemanager' => (int)$file->get_itemid()], null, null);
    }

    public function test_missing_draft_does_not_mean_remove_every_saved_file(): void {
        $item = $this->item();
        $upload = $this->draft();
        $block = item_content_edit::save($item, $this->state() + ['files_filemanager' => (int)$upload->get_itemid()]);
        $state = item_content_edit::from_record($block);
        $state['modified'] = true;
        $state['files_filemanager'] = file_get_unused_draft_itemid();
        $this->expectException(\moodle_exception::class);
        item_content_edit::normalise($state, $item, $block);
    }

    public function test_clearing_a_real_working_draft_removes_only_that_block_on_save(): void {
        global $DB;
        $item = $this->item();
        $upload = $this->draft();
        $block = item_content_edit::save($item, $this->state() + ['files_filemanager' => (int)$upload->get_itemid()]);
        $working = item_content_edit::prepare(item_content_edit::from_record($block), $item, $block);
        foreach ($this->draft_files($working['files_filemanager']) as $file) {
            $file->delete();
        }
        $this->assertNull(item_content_edit::save($item, $working, $block));
        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
        $this->assertTrue($DB->record_exists('block_exaportitem', ['id' => $item->id]));
    }

    public function test_existing_nested_text_attachments_survive_repeated_pending_edits(): void {
        global $DB, $USER;
        $item = $this->item();
        $block = block_exaport_create_content_block((int)$item->id, 'text', 'Imported text', '', [
            'content' => '<p><img src="@@PLUGINFILE@@/nested/image.png"></p>', 'contentformat' => FORMAT_HTML]);
        $original = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'block_exaport',
            'filearea' => 'item_content_text', 'itemid' => $block->id, 'filepath' => '/nested/',
            'filename' => 'image.png', 'source' => 'image.png', 'userid' => $USER->id,
        ], 'Imported image bytes');
        $first = item_content_edit::prepare(item_content_edit::from_record($block), $item, $block);
        $first['modified'] = true;
        $accepted = item_content_edit::normalise($first, $item, $block);
        $fork = item_content_edit::prepare($accepted, $item, $block);
        $this->assertStringContainsString('/' . $fork['content_editor']['itemid'] . '/nested/image.png',
            $fork['content_editor']['text']);
        $saved = item_content_edit::save($item, $fork, $block);
        $this->assertStringContainsString('@@PLUGINFILE@@/nested/image.png', $saved->content);
        $this->assertStringNotContainsString('draftfile.php', $saved->content);
        $files = get_file_storage()->get_area_files($original->get_contextid(), 'block_exaport',
            'item_content_text', $block->id, 'id', false);
        $retained = reset($files);
        $this->assertSame((int)$original->get_id(), (int)$retained->get_id());
        $this->assertSame('Imported image bytes', $retained->get_content());
    }

    public function test_text_editor_cannot_attach_an_unrelated_draft_file(): void {
        $file = $this->draft();
        $state = $this->state('text') + ['content_editor' => ['text' => '<p>Text</p>',
            'format' => FORMAT_HTML, 'itemid' => (int)$file->get_itemid()]];
        $this->expectException(\moodle_exception::class);
        item_content_edit::normalise($state, null, null);
    }

    public function test_pasting_another_draft_url_cannot_bypass_editor_attachment_validation(): void {
        global $USER;
        $file = $this->draft('pasted.txt', 'Pasted owned file');
        $url = \moodle_url::make_draftfile_url((int)$file->get_itemid(), '/', $file->get_filename())->out(false);
        $state = $this->state('text') + ['content_editor' => ['text' => '<a href="' . $url . '">Pasted file</a>',
            'format' => FORMAT_HTML, 'itemid' => 0]];
        $this->expectException(\moodle_exception::class);
        item_content_edit::normalise($state, null, null);
    }

    public function test_cv_block_keeps_live_selection_when_source_changes_before_save(): void {
        global $DB, $USER;
        $entryid = $this->cv();
        $state = item_content_edit::normalise($this->state('cv') + ['selection' => ['edu:' . $entryid]], null, null);
        $DB->set_field('block_exaportresume_edu', 'qualname', 'Updated qualification', ['id' => $entryid]);
        $saved = item_content_edit::save($this->item(), $state);
        $this->assertStringNotContainsString('Original qualification', $saved->content);
        $this->assertStringNotContainsString('Updated qualification', $saved->content);
        $this->assertStringContainsString('Updated qualification', item_linked_content::resolve($saved, (int)$USER->id)['content']);
        $this->assertSame(['edu:' . $entryid], item_linked_content::selection($saved, (int)$USER->id)['selection']);
    }

    public function test_cv_selection_deleted_before_save_is_rejected(): void {
        global $DB;
        $entryid = $this->cv();
        $state = item_content_edit::normalise($this->state('cv') + ['selection' => ['edu:' . $entryid]], null, null);
        $DB->delete_records('block_exaportresume_edu', ['id' => $entryid]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('linkedcontentselectionrequired', 'block_exaport'));
        item_content_edit::save($this->item(), $state);
    }

    public function test_url_type_and_identifier_validation_rejects_malformed_values(): void {
        foreach ([['id' => 'not-an-id'], ['key' => 'block-77'], ['type' => 'unknown'],
                ['url' => 'javascript:alert(1)'], ['title' => ['nested' => 'object']],
                ['modified' => ['nested' => 'object']]] as $changes) {
            try {
                item_content_edit::normalise(array_replace($this->state('link') + ['url' => 'https://example.test/'],
                    $changes), null, null);
                $this->fail('Malformed block values were accepted');
            } catch (\moodle_exception $error) {
                $this->assertSame('itemeditorinvalid', $error->errorcode);
            }
        }
        $item = $this->item();
        $block = block_exaport_create_content_block((int)$item->id, 'text');
        $state = item_content_edit::from_record($block);
        $state['type'] = 'link';
        $state['url'] = 'https://example.test/';
        $this->expectException(\moodle_exception::class);
        item_content_edit::normalise($state, $item, $block);
    }

    public function test_pending_validation_defers_combined_quota_but_keeps_file_count_limits(): void {
        set_config('block_exaport_userquota', 5);
        $upload = $this->draft('large.txt', str_repeat('x', 10));
        $state = $this->state() + ['files_filemanager' => (int)$upload->get_itemid()];
        $this->assertSame((int)$upload->get_itemid(), item_content_edit::normalise($state, null, null)['files_filemanager']);
        try {
            item_content_edit::normalise($state, null, null, true);
            $this->fail('Standalone quota was not checked');
        } catch (\moodle_exception $error) {
            $this->assertSame('userquotalimit', $error->errorcode);
        }
        set_config('block_exaport_multiple_files_in_item', 0);
        $this->draft('second.txt', 'Second bytes', (int)$upload->get_itemid());
        $this->expectException(\moodle_exception::class);
        item_content_edit::normalise($state, null, null);
    }
}
