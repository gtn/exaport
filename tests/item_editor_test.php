<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');

use block_exaport\local\item_content_edit;
use block_exaport\local\item_editor;

/** Integration coverage for committing one item form while keeping temporary work out of item tables. */
final class item_editor_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->preventResetByRollback();
        $this->setAdminUser();
        set_config('block_exaport_enable_interaction_competences', 0);
        set_config('block_exaport_multiple_files_in_item', 1);
        set_config('block_exaport_max_uploadfile_size', 1024 * 1024);
        set_config('block_exaport_userquota', 1024 * 1024);
    }

    private function item(string $name = 'Saved item'): \stdClass {
        global $DB, $USER;
        $id = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'courseid' => SITEID,
            'name' => $name, 'type' => 'note', 'url' => '', 'attachment' => '', 'timemodified' => 100]);
        return $DB->get_record('block_exaportitem', ['id' => $id], '*', MUST_EXIST);
    }

    private function upload(string $filename, string $bytes, string $mimetype = 'text/plain'): \stored_file {
        global $USER;
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(), 'filepath' => '/', 'filename' => $filename,
            'userid' => $USER->id, 'mimetype' => $mimetype, 'source' => serialize((object)['source' => $filename]),
        ], $bytes);
    }

    private function file_state(\stored_file $file, string $key = 'new-file'): array {
        return ['id' => 0, 'key' => $key, 'type' => 'file', 'title' => 'Pending file',
            'modified' => true, 'files_filemanager' => (int)$file->get_itemid()];
    }

    private function request(?\stdClass $item, ?array $state = null, array $metadata = []): \stdClass {
        return (object)array_replace([
            'id' => (int)($item->id ?? 0), 'courseid' => SITEID,
            'name' => $item->name ?? 'Created item',
            'editorbaseline' => item_editor::baseline($item, SITEID, false),
            'contentblocks' => json_encode($state ?? item_editor::initial_state($item), JSON_THROW_ON_ERROR),
            'competenceids' => '',
        ], $metadata);
    }

    /** A small metadata callback makes transaction behavior observable without invoking the page controller. */
    private function metadata_save(): callable {
        return static function(\stdClass $data): int {
            global $DB, $USER;
            if (empty($data->id)) {
                $data->id = (int)$DB->insert_record('block_exaportitem', ['userid' => $USER->id,
                    'courseid' => SITEID, 'name' => $data->name, 'type' => 'note', 'url' => '', 'attachment' => '']);
            } else {
                $DB->update_record('block_exaportitem', ['id' => $data->id, 'name' => $data->name]);
            }
            if (isset($data->iconfile)) {
                file_save_draft_area_files((int)$data->iconfile, \context_user::instance($USER->id)->id,
                    'block_exaport', 'item_iconfile', (int)$data->id, ['maxfiles' => 1, 'maxbytes' => 0]);
            }
            return (int)$data->id;
        };
    }

    private function validate(\stdClass $data): array {
        return item_editor::validate_request((int)$data->id, SITEID, $data->editorbaseline,
            $data->contentblocks, $data->competenceids, (array)$data, false);
    }

    public function test_one_commit_creates_metadata_and_multiple_content_types(): void {
        global $DB, $USER;
        $upload = $this->upload('document.txt', 'Submitted file bytes');
        $state = ['blocks' => [
            $this->file_state($upload),
            ['id' => 0, 'key' => 'new-link', 'type' => 'link', 'title' => 'Reference', 'modified' => true,
                'url' => 'https://example.test/'],
            ['id' => 0, 'key' => 'new-text', 'type' => 'text', 'title' => 'Description', 'modified' => true,
                'content_editor' => ['text' => '<p>Pending text</p>', 'format' => FORMAT_HTML, 'itemid' => 0]],
        ], 'removed' => []];
        $data = $this->request(null, $state, ['name' => 'Complete new item']);
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        $itemid = item_editor::commit(0, SITEID, $data, $this->metadata_save(), false);
        $this->assertSame('Complete new item', $DB->get_field('block_exaportitem', 'name', ['id' => $itemid]));
        $blocks = block_exaport_get_item_content_blocks($itemid);
        $this->assertCount(3, $blocks);
        $this->assertSame(['file', 'link', 'text'], array_column($blocks, 'type'));
        $files = block_exaport_get_item_content_files((int)$USER->id, (int)$blocks[0]->id);
        $this->assertSame('Submitted file bytes', reset($files)->get_content());
    }

    public function test_combined_quota_includes_new_thumbnail_and_new_block(): void {
        global $DB;
        set_config('block_exaport_userquota', 100);
        $file = $this->upload('document.txt', str_repeat('x', 60));
        $png = $this->upload('thumbnail.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfmcAAAAASUVORK5CYII='), 'image/png');
        $data = $this->request(null, ['blocks' => [$this->file_state($file)], 'removed' => []],
            ['iconfile' => (int)$png->get_itemid()]);
        try {
            item_editor::commit(0, SITEID, $data, $this->metadata_save(), false);
            $this->fail('Combined quota was exceeded');
        } catch (\moodle_exception $error) {
            $this->assertSame('userquotalimit', $error->errorcode);
        }
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_normal_moodle_metadata_editor_triples_pass_final_validation(): void {
        $data = $this->request(null, null, [
            'intro_editor' => ['text' => '<p>Item description</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'project_description_editor' => ['text' => '', 'format' => (string)FORMAT_HTML, 'itemid' => '0'],
            'project_process_editor' => ['text' => '', 'format' => (int)FORMAT_HTML, 'itemid' => 0],
            'project_result_editor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        ]);
        $plan = $this->validate($data);
        $this->assertSame([], $plan['changes']);
        $this->assertNull($plan['item']);
    }

    public function test_removal_frees_quota_for_file_and_thumbnail_in_the_same_submission(): void {
        global $DB, $USER;
        set_config('block_exaport_userquota', 130);
        $item = $this->item();
        $old = $this->upload('old.txt', str_repeat('o', 100));
        $oldblock = item_content_edit::save($item, $this->file_state($old));
        $next = $this->upload('next.txt', str_repeat('n', 60));
        $png = $this->upload('thumbnail.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfmcAAAAASUVORK5CYII='), 'image/png');
        $data = $this->request($item, ['blocks' => [$this->file_state($next)], 'removed' => [(int)$oldblock->id]],
            ['iconfile' => (int)$png->get_itemid()]);
        $this->assertSame((int)$item->id, item_editor::commit((int)$item->id, SITEID, $data, $this->metadata_save(), false));
        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['id' => $oldblock->id]));
        $bytes = (int)$DB->get_field_sql('SELECT SUM(filesize) FROM {files}
            WHERE contextid = ? AND component = ? AND filename <> ?',
            [\context_user::instance($USER->id)->id, 'block_exaport', '.']);
        $this->assertSame(60 + (int)$png->get_filesize(), $bytes);
    }

    public function test_an_app_added_block_is_preserved_when_a_known_block_is_edited(): void {
        global $DB;
        $item = $this->item();
        $a = block_exaport_create_content_block((int)$item->id, 'text', 'A', '', ['content' => '<p>A</p>']);
        $b = block_exaport_create_link_content_block((int)$item->id, 'B', 'https://example.test/b');
        $state = item_editor::initial_state($item);
        $state['blocks'][0]['modified'] = true;
        $state['blocks'][0]['content_editor']['text'] = '<p>Updated A</p>';
        $data = $this->request($item, $state);
        $c = block_exaport_create_link_content_block((int)$item->id, 'App C', 'https://example.test/c');
        $DB->set_field('block_exaportitem', 'timemodified', time(), ['id' => $item->id]);
        item_editor::commit((int)$item->id, SITEID, $data, $this->metadata_save(), false);
        $this->assertSame('<p>Updated A</p>', $DB->get_field('block_exaportitemblock', 'content', ['id' => $a->id]));
        $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $b->id]));
        $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $c->id]));
        $this->assertCount(3, block_exaport_get_item_content_blocks((int)$item->id));
    }

    public function test_a_concurrently_changed_dirty_block_is_rejected_without_overwrite(): void {
        global $DB;
        $item = $this->item();
        $block = block_exaport_create_link_content_block((int)$item->id, 'A', 'https://example.test/original');
        $state = item_editor::initial_state($item);
        $state['blocks'][0]['modified'] = true;
        $state['blocks'][0]['url'] = 'https://example.test/browser';
        $data = $this->request($item, $state);
        $DB->set_field('block_exaportitemblock', 'url', 'https://example.test/app', ['id' => $block->id]);
        try {
            item_editor::commit((int)$item->id, SITEID, $data, $this->metadata_save(), false);
            $this->fail('Concurrent block edit was overwritten');
        } catch (\moodle_exception $error) {
            $this->assertSame('itemeditorconflict', $error->errorcode);
        }
        $this->assertSame('https://example.test/app', $DB->get_field('block_exaportitemblock', 'url', ['id' => $block->id]));
        $pending = json_decode($data->contentblocks, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame('https://example.test/browser', $pending['blocks'][0]['url']);
    }

    public function test_a_concurrently_changed_parent_is_rejected_without_overwrite(): void {
        global $DB;
        $item = $this->item();
        $data = $this->request($item, null, ['name' => 'Browser title']);
        $DB->set_field('block_exaportitem', 'name', 'App title', ['id' => $item->id]);
        try {
            item_editor::commit((int)$item->id, SITEID, $data, $this->metadata_save(), false);
            $this->fail('Concurrent item title was overwritten');
        } catch (\moodle_exception $error) {
            $this->assertSame('itemeditorconflict', $error->errorcode);
        }
        $this->assertSame('App title', $DB->get_field('block_exaportitem', 'name', ['id' => $item->id]));
        $this->assertSame('Browser title', $data->name);
    }

    public function test_missing_malformed_duplicate_and_unknown_id_state_is_rejected(): void {
        $item = $this->item();
        $block = block_exaport_create_link_content_block((int)$item->id, 'A', 'https://example.test/');
        $data = $this->request($item);
        $known = item_content_edit::from_record($block);
        foreach (['', '{broken', '{}', json_encode(['blocks' => [$known, $known], 'removed' => []]),
                json_encode(['blocks' => [], 'removed' => [999999]])] as $json) {
            $data->contentblocks = $json;
            try {
                $this->validate($data);
                $this->fail('Invalid pending collection was accepted');
            } catch (\moodle_exception $error) {
                $this->assertSame('itemeditorinvalid', $error->errorcode);
            }
        }
    }

    public function test_a_concurrently_changed_item_course_is_rejected(): void {
        global $DB;
        $item = $this->item();
        $data = $this->request($item);
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('block_exaportitem', 'courseid', $course->id, ['id' => $item->id]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('itemeditorconflict', 'block_exaport'));
        $this->validate($data);
    }

    public function test_baseline_cannot_be_reused_for_another_item_or_user(): void {
        $item = $this->item();
        $other = $this->item('Other');
        $data = $this->request($item);
        try {
            item_editor::read_baseline($data->editorbaseline, (int)$other->id, SITEID);
            $this->fail('A baseline from another item was accepted');
        } catch (\moodle_exception $error) {
            $this->assertSame('itemeditorinvalid', $error->errorcode);
        }
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        $this->validate($data);
    }

    public function test_new_teacher_review_rejects_an_already_open_editor(): void {
        global $DB;
        $item = $this->item();
        $data = $this->request($item);
        set_config('block_exaport_app_alloweditdelete', 0);
        set_config('block_exaport_enable_interaction_competences', 1);
        if (!block_exaport_check_competence_interaction()) {
            $this->markTestSkipped('Exacomp is needed for the teacher-review fixture.');
        }
        $DB->insert_record(BLOCK_EXACOMP_DB_ITEM_MM, ['competence_type' => BLOCK_EXACOMP_TYPE_EXAMPLE,
            'exacomp_record_id' => 1, 'itemid' => $item->id, 'timecreated' => time(), 'teachervalue' => 1]);
        $this->expectException(\moodle_exception::class);
        $this->validate($data);
    }

    public function test_guarded_replacement_rolls_back_to_readable_original_bytes(): void {
        global $DB, $USER;
        $item = $this->item();
        $old = $this->upload('original.txt', 'Original permanent bytes');
        $block = item_content_edit::save($item, $this->file_state($old));
        get_file_storage()->delete_area_files($old->get_contextid(), 'user', 'draft', $old->get_itemid());
        $replacement = $this->upload('replacement.txt', 'New replacement bytes');
        $state = item_editor::initial_state($item);
        $state['blocks'][0]['modified'] = true;
        $state['blocks'][0]['files_filemanager'] = (int)$replacement->get_itemid();
        $data = $this->request($item, $state);
        try {
            item_editor::commit((int)$item->id, SITEID, $data, static function() use ($item, $block, $state): void {
                item_content_edit::save($item, $state['blocks'][0], $block, false);
                throw new \RuntimeException('Injected replacement failure');
            }, false);
            $this->fail('Injected failure was ignored');
        } catch (\RuntimeException $error) {
            $this->assertSame('Injected replacement failure', $error->getMessage());
        }
        $files = block_exaport_get_item_content_files((int)$USER->id, (int)$block->id);
        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertSame('original.txt', $file->get_filename());
        $this->assertSame('Original permanent bytes', $file->get_content());
        $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
    }

    public function test_guarded_removal_of_an_unopened_block_rolls_back_readable_bytes(): void {
        global $DB, $USER;
        $item = $this->item();
        $upload = $this->upload('remove.txt', 'Unopened original bytes');
        $block = item_content_edit::save($item, $this->file_state($upload));
        get_file_storage()->delete_area_files($upload->get_contextid(), 'user', 'draft', $upload->get_itemid());
        $data = $this->request($item, ['blocks' => [], 'removed' => [(int)$block->id]]);
        try {
            item_editor::commit((int)$item->id, SITEID, $data, static function() use ($item, $block): void {
                block_exaport_delete_item_content_block($item, $block);
                throw new \RuntimeException('Injected removal failure');
            }, false);
            $this->fail('Injected failure was ignored');
        } catch (\RuntimeException $error) {
            $this->assertSame('Injected removal failure', $error->getMessage());
        }
        $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
        $files = block_exaport_get_item_content_files((int)$USER->id, (int)$block->id);
        $this->assertSame('Unopened original bytes', reset($files)->get_content());
    }

    public function test_repeated_identical_post_returns_the_committed_item_without_duplicate_blocks(): void {
        global $DB;
        $upload = $this->upload('once.txt', 'Saved once');
        $data = $this->request(null, ['blocks' => [$this->file_state($upload)], 'removed' => []]);
        $retry = clone $data;
        $first = item_editor::commit(0, SITEID, $data, $this->metadata_save(), false);
        // A real browser retry passes main-form validation before the controller calls commit again.
        $this->assertIsArray($this->validate($retry));
        $this->assertSame($first, item_editor::commit(0, SITEID, $retry, $this->metadata_save(), false));
        $this->assertSame(1, $DB->count_records('block_exaportitem'));
        $this->assertSame(1, $DB->count_records('block_exaportitemblock'));
    }

    public function test_reusing_a_completed_token_for_different_changes_is_a_conflict(): void {
        global $DB;
        $data = $this->request(null);
        $retry = clone $data;
        item_editor::commit(0, SITEID, $data, $this->metadata_save(), false);
        $retry->name = 'Different changes after the first Save';
        try {
            $this->validate($retry);
            $this->fail('A completed token was reused for different changes');
        } catch (\moodle_exception $error) {
            $this->assertSame('itemeditorconflict', $error->errorcode);
        }
        $this->assertSame(1, $DB->count_records('block_exaportitem'));
    }

    /** Exercise native editor saves as well as the ordinary metadata fixture. */
    private function metadata_and_editors_save(): callable {
        $save = $this->metadata_save();
        return static function(\stdClass $data) use ($save): int {
            global $USER;
            $id = $save($data);
            foreach (['intro', 'project_description', 'project_process', 'project_result'] as $field) {
                $editor = $data->{$field . '_editor'} ?? null;
                if (!empty($editor['itemid'])) {
                    file_save_draft_area_files((int)$editor['itemid'], \context_user::instance($USER->id)->id,
                        'block_exaport', 'item_content_' . $field, $id,
                        ['subdirs' => true, 'maxfiles' => 99, 'maxbytes' => 0], $editor['text']);
                }
            }
            return $id;
        };
    }

    public function test_shared_block_and_parent_editor_draft_is_counted_after_all_pasted_merges(): void {
        global $DB, $USER;
        $shared = $this->upload('shared.txt', str_repeat('s', 50));
        $pasted = $this->upload('pasted.txt', str_repeat('p', 50));
        $url = \moodle_url::make_draftfile_url((int)$pasted->get_itemid(), '/', 'pasted.txt')->out(false);
        $data = $this->request(null, ['blocks' => [$this->file_state($shared)], 'removed' => []], [
            'intro_editor' => ['text' => '<a href="' . $url . '">Pasted file</a>',
                'format' => FORMAT_HTML, 'itemid' => (int)$shared->get_itemid()],
        ]);
        set_config('block_exaport_userquota', 175);
        try {
            item_editor::commit(0, SITEID, $data, $this->metadata_and_editors_save(), false);
            $this->fail('The later parent merge enlarged a block draft after it was counted');
        } catch (\moodle_exception $error) {
            $this->assertSame('userquotalimit', $error->errorcode);
        }
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
        // Each permanent destination receives both files: 100 bytes for the block and 100 for intro.
        set_config('block_exaport_userquota', 200);
        item_editor::commit(0, SITEID, $data, $this->metadata_and_editors_save(), false);
        $bytes = $DB->get_field_sql('SELECT SUM(filesize) FROM {files}
            WHERE contextid = ? AND component = ? AND filename <> ?',
            [\context_user::instance($USER->id)->id, 'block_exaport', '.']);
        $this->assertSame(200, (int)$bytes);
    }

    public function test_two_parent_editors_sharing_a_draft_are_counted_after_both_merges(): void {
        global $DB, $USER;
        $shared = $this->upload('shared.txt', str_repeat('s', 50));
        $first = $this->upload('first.txt', str_repeat('a', 50));
        $second = $this->upload('second.txt', str_repeat('b', 50));
        $firsturl = \moodle_url::make_draftfile_url((int)$first->get_itemid(), '/', 'first.txt')->out(false);
        $secondurl = \moodle_url::make_draftfile_url((int)$second->get_itemid(), '/', 'second.txt')->out(false);
        $data = $this->request(null, null, [
            'intro_editor' => ['text' => '<a href="' . $firsturl . '">First</a>',
                'format' => FORMAT_HTML, 'itemid' => (int)$shared->get_itemid()],
            'project_description_editor' => ['text' => '<a href="' . $secondurl . '">Second</a>',
                'format' => FORMAT_HTML, 'itemid' => (int)$shared->get_itemid()],
        ]);
        set_config('block_exaport_userquota', 275);
        try {
            item_editor::commit(0, SITEID, $data, $this->metadata_and_editors_save(), false);
            $this->fail('Both editor destinations must count the final 150-byte shared draft');
        } catch (\moodle_exception $error) {
            $this->assertSame('userquotalimit', $error->errorcode);
        }
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
        set_config('block_exaport_userquota', 300);
        item_editor::commit(0, SITEID, $data, $this->metadata_and_editors_save(), false);
        $bytes = $DB->get_field_sql('SELECT SUM(filesize) FROM {files}
            WHERE contextid = ? AND component = ? AND filename <> ?',
            [\context_user::instance($USER->id)->id, 'block_exaport', '.']);
        $this->assertSame(300, (int)$bytes);
    }

    public function test_parent_merge_cannot_enlarge_an_already_validated_thumbnail_draft(): void {
        global $DB;
        $png = $this->upload('thumbnail.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfmcAAAAASUVORK5CYII='), 'image/png');
        $pasted = $this->upload('not-an-image.txt', 'Unexpected thumbnail file');
        $url = \moodle_url::make_draftfile_url((int)$pasted->get_itemid(), '/', 'not-an-image.txt')->out(false);
        $data = $this->request(null, null, ['iconfile' => (int)$png->get_itemid(),
            'intro_editor' => ['text' => '<a href="' . $url . '">Pasted</a>',
                'format' => FORMAT_HTML, 'itemid' => (int)$png->get_itemid()],
        ]);
        try {
            item_editor::commit(0, SITEID, $data, $this->metadata_and_editors_save(), false);
            $this->fail('A parent merge bypassed thumbnail count/type validation');
        } catch (\moodle_exception $error) {
            $this->assertSame('itemeditorinvalid', $error->errorcode);
        }
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
    }

    public function test_completed_save_rejects_changed_thumbnail_bytes_under_the_same_draft_id(): void {
        global $DB, $USER;
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfmcAAAAASUVORK5CYII=');
        $png = $this->upload('thumbnail.png', $bytes, 'image/png');
        $data = $this->request(null, null, ['iconfile' => (int)$png->get_itemid()]);
        $retry = clone $data;
        $id = item_editor::commit(0, SITEID, $data, $this->metadata_save(), false);
        $png->delete();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => (int)$png->get_itemid(), 'filepath' => '/', 'filename' => 'thumbnail.png',
            'userid' => $USER->id, 'mimetype' => 'image/png',
            'source' => serialize((object)['source' => 'thumbnail.png']),
        ], $bytes . "\n");
        foreach (['validation', 'commit'] as $stage) {
            try {
                if ($stage === 'validation') {
                    $this->validate($retry);
                } else {
                    item_editor::commit(0, SITEID, $retry, $this->metadata_save(), false);
                }
                $this->fail('A changed draft was mistaken for an identical completed POST');
            } catch (\moodle_exception $error) {
                $this->assertSame('itemeditorconflict', $error->errorcode);
            }
        }
        $this->assertSame(1, $DB->count_records('block_exaportitem'));
        $saved = get_file_storage()->get_file(\context_user::instance($USER->id)->id,
            'block_exaport', 'item_iconfile', $id, '/', 'thumbnail.png');
        $this->assertSame($bytes, $saved->get_content());
    }

    public function test_completed_save_with_pasted_editor_files_still_accepts_an_identical_retry(): void {
        global $DB;
        $primary = $this->upload('primary.txt', 'Primary');
        $pasted = $this->upload('pasted.txt', 'Pasted');
        $url = \moodle_url::make_draftfile_url((int)$pasted->get_itemid(), '/', 'pasted.txt')->out(false);
        $data = $this->request(null, null, [
            'intro_editor' => ['text' => '<a href="' . $url . '">Pasted</a>',
                'format' => FORMAT_HTML, 'itemid' => (int)$primary->get_itemid()],
        ]);
        $retry = clone $data;
        $id = item_editor::commit(0, SITEID, $data, $this->metadata_and_editors_save(), false);
        $this->assertIsArray($this->validate($retry));
        $this->assertSame($id, item_editor::commit(0, SITEID, $retry, $this->metadata_and_editors_save(), false));
        $this->assertSame(1, $DB->count_records('block_exaportitem'));
    }

    public function test_opening_state_and_baseline_share_the_original_block_snapshot(): void {
        global $DB;
        $item = $this->item();
        $block = block_exaport_create_link_content_block((int)$item->id, 'A', 'https://example.test/original');
        $records = block_exaport_get_item_content_blocks((int)$item->id);
        $state = item_editor::initial_state($item, $records);
        $DB->set_field('block_exaportitemblock', 'url', 'https://example.test/concurrent', ['id' => $block->id]);
        $baseline = item_editor::baseline($item, SITEID, false, $records);
        $this->assertSame('https://example.test/original', $state['blocks'][0]['url']);
        try {
            item_editor::pending_seed(json_encode($state['blocks'][0], JSON_THROW_ON_ERROR),
                $baseline, (int)$item->id, SITEID);
            $this->fail('A newer fingerprint authenticated the stale opening state');
        } catch (\moodle_exception $error) {
            $this->assertSame('itemeditorconflict', $error->errorcode);
        }
    }
}
