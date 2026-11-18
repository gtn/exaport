<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');

use block_exaport\local\item_content_edit;
use block_exaport\output\pending_item_content_blocks;

/** Pending previews use owned drafts and live selected sources without writing item blocks. */
final class pending_item_content_blocks_test extends \advanced_testcase {
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('block_exaport_enable_interaction_competences', 0);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/blocks/exaport/item.php', ['action' => 'add', 'courseid' => SITEID]);
    }

    /** A minimal canonical block; no database row is created by the preview. */
    private function state(string $type = 'file'): array {
        return ['key' => 'new-preview', 'id' => 0, 'type' => $type, 'title' => 'Pending title',
            'modified' => true, 'url' => '', 'files_filemanager' => 0,
            'content_editor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'selection' => [], 'withfiles' => false];
    }

    private function renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('block_exaport');
    }

    public function test_empty_section_keeps_an_insertion_target_and_safe_modal_controls(): void {
        global $DB;
        $html = pending_item_content_blocks::render_section(['blocks' => [], 'removed' => []], null, SITEID);
        $this->assertStringContainsString('exaport-item-content-rows border-top" hidden', $html);
        $this->assertStringContainsString('exaport-item-content-add-button', $html);
        $this->assertStringContainsString('data-content-type="file"', $html);
        $this->assertStringNotContainsString('item_content_file.php?', $html);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_only_embedded_content_omits_standalone_section_margins(): void {
        global $USER;
        $pending = pending_item_content_blocks::render_section(['blocks' => [], 'removed' => []], null, SITEID);
        $this->assertStringContainsString('class="exaport-item-content-section"', $pending);
        $saved = new \block_exaport\output\item_content_blocks([], null, (int)$USER->id, false);
        $standalone = $this->renderer()->render($saved);
        $this->assertStringContainsString('class="exaport-item-content-section mt-4 mb-4"', $standalone);
    }

    public function test_pending_file_preview_uses_owned_draft_bytes_and_stable_key(): void {
        global $DB, $USER;
        $draftid = file_get_unused_draft_itemid();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user',
            'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
            'filename' => 'upload.pdf', 'userid' => $USER->id,
        ], 'pending file bytes');
        $state = $this->state();
        $state['files_filemanager'] = $draftid;
        $section = new pending_item_content_blocks([$state], null, SITEID);
        $data = $section->export_for_template($this->renderer());
        $this->assertSame('new-preview', $data['blocks'][0]['key']);
        $this->assertSame('#', $data['blocks'][0]['editurl']);
        $this->assertSame('#', $data['blocks'][0]['deleteurl']);
        $this->assertStringContainsString('draftfile.php', $data['blocks'][0]['files'][0]['url']);
        $this->assertSame('upload.pdf', $data['blocks'][0]['files'][0]['name']);
        $this->assertSame('pending file bytes', $file->get_content());
        $html = pending_item_content_blocks::render_row($state, null, SITEID);
        $this->assertStringContainsString('data-block-key="new-preview"', $html);
        $this->assertStringContainsString(get_string('edit'), $html);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_unchanged_saved_rows_ignore_submitted_presentation_overrides(): void {
        global $DB, $USER;
        $id = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'courseid' => SITEID,
            'name' => 'Existing', 'type' => 'note']);
        $item = $DB->get_record('block_exaportitem', ['id' => $id]);
        $block = block_exaport_create_link_content_block((int)$id, 'Stored title', 'https://example.test/stored');
        $state = item_content_edit::from_record($block);
        $state['title'] = 'Unapplied title';
        $state['url'] = 'https://example.test/unapplied';
        $data = (new pending_item_content_blocks([$state], $item, SITEID))->export_for_template($this->renderer());
        $this->assertSame('Stored title', $data['blocks'][0]['title']);
        $this->assertSame('https://example.test/stored', $data['blocks'][0]['linkurl']);
        $this->assertSame((int)$block->id, $data['blocks'][0]['id']);
    }

    public function test_pending_text_is_formatted_and_hidden_values_cannot_inject_script(): void {
        $state = $this->state('text');
        $state['title'] = '"><script>window.previewTitleAttack=1</script>';
        $state['content_editor']['text'] = '<p>Visible content</p><script>window.previewBodyAttack=1</script>';
        $html = pending_item_content_blocks::render_row($state, null, SITEID);
        $this->assertStringContainsString('Visible content', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_cv_preview_is_live_and_selected_attachments_use_private_draft_urls(): void {
        global $DB, $USER;
        $resumeid = $DB->insert_record('block_exaportresume', ['user_id' => $USER->id]);
        $selected = $DB->insert_record('block_exaportresume_edu', ['resume_id' => $resumeid,
            'startdate' => '2020', 'institution' => 'University', 'qualname' => 'Selected qualification',
            'qualdescription' => '<p>Selected description</p>', 'sorting' => 0]);
        $DB->insert_record('block_exaportresume_edu', ['resume_id' => $resumeid,
            'institution' => 'Private institution', 'qualname' => 'Unselected private qualification', 'sorting' => 1]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'block_exaport',
            'filearea' => 'resume_edu', 'itemid' => $selected, 'filepath' => '/',
            'filename' => 'CV evidence.pdf', 'userid' => $USER->id,
        ], 'selected attachment bytes');
        $state = $this->state('cv');
        $state['selection'] = ['edu:' . $selected];
        $state['withfiles'] = true;
        $html = pending_item_content_blocks::render_row($state, null, SITEID);
        $this->assertStringContainsString('Selected qualification', $html);
        $this->assertStringContainsString('CV evidence.pdf', $html);
        $this->assertStringContainsString('draftfile.php', $html);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $html);
        $this->assertStringNotContainsString('Unselected private qualification', $html);
        $this->assertStringNotContainsString('/itemid/0/blockid/0', $html);
        $DB->set_field('block_exaportresume_edu', 'qualname', 'Changed in another tab', ['id' => $selected]);
        $this->assertStringContainsString('Changed in another tab',
            pending_item_content_blocks::render_row($state, null, SITEID));
        $DB->delete_records('block_exaportresume_edu', ['id' => $selected]);
        $this->assertStringContainsString(get_string('linkedcontentmissing', 'block_exaport'),
            pending_item_content_blocks::render_row($state, null, SITEID));
        $this->assertSame(['edu:' . $selected], $state['selection']);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_a_pending_preview_cannot_load_a_block_from_another_item(): void {
        global $DB, $USER;
        $one = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'name' => 'One']);
        $two = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'name' => 'Two']);
        $block = block_exaport_create_link_content_block((int)$two, 'Other block', 'https://example.test');
        $state = item_content_edit::from_record($block);
        $this->expectException(\invalid_parameter_exception::class);
        new pending_item_content_blocks([$state], $DB->get_record('block_exaportitem', ['id' => $one]), SITEID);
    }

    public function test_concurrently_deleted_dirty_block_keeps_its_pending_preview(): void {
        global $DB, $USER;
        $id = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'name' => 'Existing']);
        $item = $DB->get_record('block_exaportitem', ['id' => $id]);
        $block = block_exaport_create_link_content_block((int)$id, 'Old link', 'https://example.test/original');
        $state = item_content_edit::from_record($block);
        $state['modified'] = true;
        $state['title'] = 'My pending link';
        $state['url'] = 'https://example.test/pending';
        $DB->delete_records('block_exaportitemblock', ['id' => $block->id]);
        $html = pending_item_content_blocks::render_row($state, $item, SITEID);
        $this->assertStringContainsString('My pending link', $html);
        $this->assertStringContainsString('https://example.test/pending', $html);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_readonly_pending_preview_keeps_uploads_without_edit_controls(): void {
        global $USER;
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user',
            'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
            'filename' => 'pending-evidence.pdf', 'userid' => $USER->id,
        ], 'unsaved evidence');
        $state = $this->state();
        $state['files_filemanager'] = $draftid;
        $html = pending_item_content_blocks::render_section(['blocks' => [$state], 'removed' => []], null, SITEID, false);
        $this->assertStringContainsString('pending-evidence.pdf', $html);
        $this->assertStringContainsString('draftfile.php', $html);
        $this->assertStringNotContainsString('exaport-item-content-edit', $html);
        $this->assertStringNotContainsString('exaport-item-content-delete', $html);
        $this->assertStringNotContainsString('exaport-item-content-add-button', $html);
    }
}
