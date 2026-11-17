<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../lib/item_competence_helpers.php');

use block_exaport\form\item_content;
use block_exaport\form\item_competences;
use block_exaport\local\item_content_edit;
use block_exaport\local\item_editor;

/** Exercise the actual modal classes through Moodle's dynamic-form external service. */
final class item_pending_modal_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('block_exaport_enable_interaction_competences', 0);
        set_config('block_exaport_multiple_files_in_item', 1);
        set_config('block_exaport_max_uploadfile_size', 1048576);
        set_config('block_exaport_userquota', 10485760);
    }

    /** Each call models a fresh AJAX request, including the real form's validation and processing. */
    private function request(string $class, array $args, bool $submit = true): array {
        global $PAGE, $OUTPUT;
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/blocks/exaport/item.php');
        $OUTPUT = $PAGE->get_renderer('core');
        if ($submit) {
            $args = $class::mock_ajax_submit($args);
        }
        $response = \core_form\external\dynamic_form::execute($class, http_build_query($args, '', '&'));
        if (!empty($response['submitted'])) {
            $response['result'] = json_decode($response['data'], true, 32, JSON_THROW_ON_ERROR);
        }
        return $response;
    }

    private function args(string $type, ?array $state = null): array {
        $state = $state ?? ['id' => 0, 'key' => 'new-' . $type, 'type' => $type,
            'title' => 'Pending ' . $type, 'modified' => true];
        return ['itemid' => 0, 'courseid' => SITEID, 'blockid' => $state['id'],
            'contenttype' => $type, 'operation' => 'save', 'pending' => 1,
            'pendingblock' => json_encode($state, JSON_THROW_ON_ERROR)];
    }

    private function value(string $html, string $name): string {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//input|//textarea') as $input) {
            if ($input->getAttribute('name') === $name) {
                return $input->tagName === 'textarea' ? $input->textContent : $input->getAttribute('value');
            }
        }
        $this->fail('Missing registered form field: ' . $name);
    }

    /** Serialize the rendered successful controls, including only draft IDs the browser actually receives. */
    private function submitted_fields(string $html): array {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $pairs = [];
        foreach ($xpath->query('//form//input|//form//textarea|//form//select') as $control) {
            $name = $control->getAttribute('name');
            $type = strtolower($control->getAttribute('type'));
            if ($name === '' || $control->hasAttribute('disabled') ||
                    in_array($type, ['submit', 'button', 'reset', 'file'], true) ||
                    (in_array($type, ['checkbox', 'radio'], true) && !$control->hasAttribute('checked'))) {
                continue;
            }
            if ($control->tagName === 'select') {
                $values = [];
                foreach ($control->getElementsByTagName('option') as $option) {
                    if ($option->hasAttribute('selected')) {
                        $values[] = $option->getAttribute('value');
                    }
                }
                if (!$values && !$control->hasAttribute('multiple')) {
                    $first = $control->getElementsByTagName('option')->item(0);
                    $values = $first ? [$first->getAttribute('value')] : [];
                }
            } else {
                $values = [$control->tagName === 'textarea' ? $control->textContent : $control->getAttribute('value')];
            }
            foreach ($values as $value) {
                $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
            }
        }
        parse_str(implode('&', $pairs), $fields);
        return $fields;
    }

    private function saved_item(): \stdClass {
        global $DB, $USER;
        $id = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'courseid' => SITEID,
            'name' => 'Text modal fixture', 'type' => 'note']);
        return $DB->get_record('block_exaportitem', ['id' => $id], '*', MUST_EXIST);
    }

    /** Keep metadata minimal while exercising the real final block/file commit. */
    private function commit_block(?\stdClass $item, array $state): int {
        $data = (object)['id' => (int)($item->id ?? 0), 'courseid' => SITEID, 'name' => 'Text modal fixture',
            'contentblocks' => json_encode(['blocks' => [$state], 'removed' => []], JSON_THROW_ON_ERROR),
            'competenceids' => ''];
        return item_editor::commit((int)$data->id, SITEID, $data, static function(\stdClass $metadata): int {
            global $DB, $USER;
            if (!$metadata->id) {
                $metadata->id = (int)$DB->insert_record('block_exaportitem', ['userid' => $USER->id,
                    'courseid' => SITEID, 'name' => $metadata->name, 'type' => 'note']);
            }
            return (int)$metadata->id;
        }, false);
    }

    private function upload(int $draftid, string $name = 'evidence.txt', string $bytes = 'Uploaded evidence'): \stored_file {
        global $USER;
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => $name, 'userid' => $USER->id,
            'source' => serialize((object)['source' => $name]),
        ], $bytes);
    }

    public function test_file_can_be_added_reopened_and_applied_before_the_item_exists(): void {
        global $DB, $USER;
        $args = $this->args('file');
        $opened = $this->request(item_content::class, $args, false);
        $draftid = (int)$this->value($opened['html'], 'files_filemanager');
        $this->assertGreaterThan(0, $draftid);
        $this->upload($draftid);
        $applied = $this->request(item_content::class, $args + ['title' => 'First title', 'files_filemanager' => $draftid]);
        $this->assertTrue($applied['submitted']);
        $state = $applied['result']['block'];
        $this->assertSame('First title', $state['title']);
        $this->assertSame(0, $state['id']);
        $this->assertStringContainsString('evidence.txt', $applied['result']['content']);

        $reopenargs = $this->args('file', $state);
        $reopened = $this->request(item_content::class, $reopenargs, false);
        $working = (int)$this->value($reopened['html'], 'files_filemanager');
        $this->assertNotSame($draftid, $working);
        $this->assertSame('First title', $this->value($reopened['html'], 'title'));
        $this->upload($working, 'second.txt', 'Second evidence');
        $again = $this->request(item_content::class, $reopenargs + ['title' => 'Updated title', 'files_filemanager' => $working]);
        $this->assertTrue($again['submitted']);
        $this->assertSame($state['key'], $again['result']['block']['key']);
        $this->assertSame('Updated title', $again['result']['block']['title']);
        $this->assertSame(1, file_get_draft_area_info($draftid)['filecount']);
        $this->assertSame(2, file_get_draft_area_info($working)['filecount']);
        $this->assertSame(0, $DB->count_records('block_exaportitem', ['userid' => $USER->id]));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_modal_cancel_does_not_change_accepted_files_or_create_rows(): void {
        global $DB;
        $draftid = file_get_unused_draft_itemid();
        $this->upload($draftid);
        $state = ['id' => 0, 'key' => 'new-cancel', 'type' => 'file', 'title' => 'Accepted',
            'modified' => true, 'files_filemanager' => $draftid];
        $args = $this->args('file', $state);
        $opened = $this->request(item_content::class, $args, false);
        $working = (int)$this->value($opened['html'], 'files_filemanager');
        $this->upload($working, 'cancelled.txt', 'Discarded modal upload');
        // Moodle's modal Cancel closes the dialog without submitting its working values.
        // The next opening must therefore start again from the accepted state.
        $reopened = $this->request(item_content::class, $args, false);
        $reopenedid = (int)$this->value($reopened['html'], 'files_filemanager');
        $this->assertSame('Accepted', $this->value($reopened['html'], 'title'));
        $this->assertSame(1, file_get_draft_area_info($reopenedid)['filecount']);
        $this->assertSame(1, file_get_draft_area_info($draftid)['filecount']);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_pending_delete_of_a_new_block_never_creates_an_orphan(): void {
        global $DB;
        $args = $this->args('text');
        $args['operation'] = 'delete';
        $opened = $this->request(item_content::class, $args, false);
        $this->assertStringContainsString(get_string('deletecontentblockpendingconfirm', 'block_exaport'), $opened['html']);
        $this->assertStringNotContainsString(get_string('deletecontentblockconfirm', 'block_exaport'), $opened['html']);
        $response = $this->request(item_content::class, $args);
        $this->assertTrue($response['submitted']);
        $this->assertSame(['key' => 'new-text', 'removed' => true], $response['result']);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_standalone_delete_retains_its_immediate_confirmation(): void {
        global $DB, $USER;
        $itemid = $DB->insert_record('block_exaportitem', [
            'userid' => $USER->id, 'courseid' => SITEID, 'name' => 'Saved item', 'type' => 'note',
        ]);
        $block = block_exaport_create_link_content_block((int)$itemid, 'Saved link', 'https://example.test/');
        $opened = $this->request(item_content::class, ['itemid' => $itemid, 'courseid' => SITEID,
            'blockid' => $block->id, 'contenttype' => 'link', 'operation' => 'delete'], false);
        $this->assertStringContainsString(get_string('deletecontentblockconfirm', 'block_exaport'), $opened['html']);
        $this->assertStringNotContainsString(get_string('deletecontentblockpendingconfirm', 'block_exaport'), $opened['html']);
        $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
    }

    public function test_text_link_and_personal_apply_use_existing_fields_without_persistence(): void {
        global $DB;
        $cases = [
            'text' => ['content_editor' => ['text' => '<p>Pending rich text</p>', 'format' => FORMAT_HTML, 'itemid' => 0]],
            'link' => ['url' => 'https://example.test/evidence'],
            'personal' => ['selection' => ['firstname']],
        ];
        foreach ($cases as $type => $fields) {
            $args = $this->args($type);
            $opened = $this->request(item_content::class, $args, false);
            $this->assertFalse($opened['submitted']);
            $response = $this->request(item_content::class, $args + ['title' => 'Applied ' . $type] + $fields);
            $this->assertTrue($response['submitted'], $type);
            $this->assertSame($type, $response['result']['block']['type']);
            $this->assertSame('Applied ' . $type, $response['result']['block']['title']);
            $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        }
    }

    public static function fileless_text_contexts(): array {
        return ['saved item' => [true], 'new item' => [false]];
    }

    /** @dataProvider fileless_text_contexts */
    public function test_fileless_text_apply_reopen_and_cancel_keep_the_accepted_text(bool $saved): void {
        global $DB;
        $item = $saved ? $this->saved_item() : null;
        $original = '<p>Original saved text</p>';
        $block = $item ? block_exaport_create_content_block((int)$item->id, 'text', 'Original', '', [
            'content' => $original, 'contentformat' => FORMAT_HTML]) : null;
        $args = $this->args('text', $block ? item_content_edit::from_record($block) : null);
        $args['itemid'] = (int)($item->id ?? 0);
        $opened = $this->request(item_content::class, $args, false);
        $fields = $this->submitted_fields($opened['html']);
        $fields['title'] = 'Accepted title';
        $fields['content_editor']['text'] = '<p>Accepted pending text</p>';
        $applied = $this->request(item_content::class, $fields);
        $this->assertTrue($applied['submitted']);
        $state = $applied['result']['block'];
        $args['pendingblock'] = json_encode($state, JSON_THROW_ON_ERROR);
        $reopened = $this->request(item_content::class, $args, false);
        $this->assertSame('<p>Accepted pending text</p>', $this->value($reopened['html'], 'content_editor[text]'));
        $working = $this->submitted_fields($reopened['html']);
        $this->assertGreaterThan(0, (int)$working['content_editor']['itemid']);
        $this->assertSame([], item_content_edit::draft_files((int)$working['content_editor']['itemid']));
        $working['content_editor']['text'] = '<p>Cancelled local edit</p>';
        // Closing the dialog never submits these controls; reopen from the last accepted block.
        $aftercancel = $this->request(item_content::class, $args, false);
        $this->assertSame('<p>Accepted pending text</p>', $this->value($aftercancel['html'], 'content_editor[text]'));
        if ($block) {
            $this->assertSame($original, $DB->get_field('block_exaportitemblock', 'content', ['id' => $block->id]));
        } else {
            $this->assertSame(0, $DB->count_records('block_exaportitem'));
            $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        }
        $itemid = $this->commit_block($item, $state);
        $savedblocks = block_exaport_get_item_content_blocks($itemid);
        $this->assertCount(1, $savedblocks);
        $this->assertSame('<p>Accepted pending text</p>', $savedblocks[0]->content);
    }

    public function test_rendered_text_form_preserves_nested_imported_media_through_apply_reopen_and_commit(): void {
        global $DB, $USER;
        $item = $this->saved_item();
        $html = '<p><img src="@@PLUGINFILE@@/nested/image.png"></p>';
        $block = block_exaport_create_content_block((int)$item->id, 'text', 'Imported media', '', [
            'content' => $html, 'contentformat' => FORMAT_HTML]);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'block_exaport',
            'filearea' => 'item_content_text', 'itemid' => $block->id, 'filepath' => '/nested/',
            'filename' => 'image.png', 'source' => 'image.png', 'userid' => $USER->id,
        ], 'Nested imported image bytes');
        $args = $this->args('text', item_content_edit::from_record($block));
        $args['itemid'] = (int)$item->id;
        $opened = $this->request(item_content::class, $args, false);
        $fields = $this->submitted_fields($opened['html']);
        $fields['content_editor']['text'] .= '<p>Accepted media edit</p>';
        $applied = $this->request(item_content::class, $fields);
        $this->assertTrue($applied['submitted']);
        $accepted = $applied['result']['block'];
        $args['pendingblock'] = json_encode($accepted, JSON_THROW_ON_ERROR);
        $reopened = $this->request(item_content::class, $args, false);
        $working = $this->submitted_fields($reopened['html']);
        $this->assertNotSame((int)$accepted['content_editor']['itemid'], (int)$working['content_editor']['itemid']);
        $this->assertStringContainsString('/' . $working['content_editor']['itemid'] . '/nested/image.png',
            $working['content_editor']['text']);
        $this->assertStringContainsString('Accepted media edit', $working['content_editor']['text']);
        $this->assertSame($html, $DB->get_field('block_exaportitemblock', 'content', ['id' => $block->id]));
        $working['content_editor']['text'] .= '<p>Second media edit</p>';
        $again = $this->request(item_content::class, $working);
        $this->assertTrue($again['submitted']);
        $this->commit_block($item, $again['result']['block']);
        $content = $DB->get_field('block_exaportitemblock', 'content', ['id' => $block->id]);
        $this->assertStringContainsString('@@PLUGINFILE@@/nested/image.png', $content);
        $this->assertStringContainsString('Accepted media edit', $content);
        $this->assertStringContainsString('Second media edit', $content);
        $this->assertStringNotContainsString('draftfile.php', $content);
        $retained = get_file_storage()->get_file($file->get_contextid(), 'block_exaport', 'item_content_text',
            $block->id, '/nested/', 'image.png');
        $this->assertSame((int)$file->get_id(), (int)$retained->get_id());
        $this->assertSame('Nested imported image bytes', $retained->get_content());
    }

    public function test_cv_apply_reads_current_source_and_rejects_a_deleted_selection(): void {
        global $DB, $USER;
        $resumeid = $DB->insert_record('block_exaportresume', ['user_id' => $USER->id]);
        $entry = $DB->insert_record('block_exaportresume_edu', ['resume_id' => $resumeid,
            'startdate' => '2020', 'institution' => 'University', 'qualname' => 'Original qualification', 'sorting' => 0]);
        $args = $this->args('cv');
        $DB->set_field('block_exaportresume_edu', 'qualname', 'Latest qualification', ['id' => $entry]);
        $response = $this->request(item_content::class, $args + ['title' => 'Education', 'selection' => ['edu:' . $entry]]);
        $this->assertTrue($response['submitted']);
        $this->assertSame(['edu:' . $entry], $response['result']['block']['selection']);
        $this->assertStringContainsString('Latest qualification', $response['result']['content']);
        $DB->delete_records('block_exaportresume_edu', ['id' => $entry]);
        $response = $this->request(item_content::class, $args + ['title' => 'Education', 'selection' => ['edu:' . $entry]]);
        $this->assertFalse($response['submitted']);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_earned_badge_can_be_applied_without_a_saved_parent(): void {
        global $DB, $USER;
        set_config('enablebadges', 1);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_badges');
        $badge = $generator->create_badge(['name' => 'Pending earned badge']);
        $generator->create_issued_badge(['badgeid' => $badge->id, 'userid' => $USER->id]);
        $response = $this->request(item_content::class, $this->args('badge') + [
            'title' => 'Achievement', 'selection' => [(string)$badge->id],
        ]);
        $this->assertTrue($response['submitted']);
        $this->assertStringContainsString('Pending earned badge', $response['result']['content']);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_another_users_file_draft_is_rejected(): void {
        global $DB;
        $draftid = file_get_unused_draft_itemid();
        $this->upload($draftid);
        $this->setUser($this->getDataGenerator()->create_user());
        $response = $this->request(item_content::class, $this->args('file') + [
            'title' => 'Forged upload', 'files_filemanager' => $draftid,
        ]);
        $this->assertFalse($response['submitted']);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_pending_content_processing_requires_a_valid_session_key(): void {
        $args = item_content::mock_ajax_submit($this->args('link') + [
            'title' => 'Invalid session', 'url' => 'https://example.test/',
        ]);
        $args['sesskey'] = $_POST['sesskey'] = 'invalid';
        $this->expectException(\moodle_exception::class);
        $this->request(item_content::class, $args, false);
    }

    public function test_new_competence_selection_is_pending_and_rejects_inaccessible_ids(): void {
        global $DB;
        set_config('block_exaport_enable_interaction_competences', 1);
        $args = ['itemid' => 0, 'courseid' => SITEID, 'pending' => 1,
            'selectedids' => ''];
        $accepted = $this->request(item_competences::class, $args + ['competenceids' => '']);
        $this->assertTrue($accepted['submitted']);
        $this->assertSame([], $accepted['result']['competenceids']);
        $id = $DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => 'Not available in this user tree']);
        $rejected = $this->request(item_competences::class, $args + ['competenceids' => (string)$id]);
        $this->assertFalse($rejected['submitted']);
        $this->assertSame(0, $DB->count_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, ['eportfolioitem' => 1]));
    }

    /**
     * Exercise a learner's real course tree, without borrowing assignments from a saved item.
     *
     * Exacomp caches descriptor records in PHP statics, so isolate this fixture from other trees.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_new_item_can_stage_visible_competencies_then_commit_their_real_parent(): void {
        global $DB;
        set_config('block_exaport_enable_interaction_competences', 1);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $generator->create_user();
        $generator->enrol_user($owner->id, $course->id, 'student');
        $generator->create_block('exacomp', ['parentcontextid' => \context_course::instance($course->id)->id]);
        $subjectid = $DB->insert_record('block_exacompsubjects', [
            'title' => 'Visible subject', 'sourceid' => 0, 'source' => 0,
        ]);
        $topicid = $DB->insert_record('block_exacomptopics', [
            'title' => 'Visible topic', 'subjid' => $subjectid, 'source' => 0,
        ]);
        $descriptorid = (int)$DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, [
            'title' => 'Visible competency', 'sourceid' => 0, 'source' => 0, 'parentid' => 0,
        ]);
        $DB->insert_record('block_exacompdescrtopic_mm', ['descrid' => $descriptorid, 'topicid' => $topicid]);
        $DB->insert_record('block_exacompcoutopi_mm', ['courseid' => $course->id, 'topicid' => $topicid]);
        // Exacomp's learner topic query requires an explicitly visible course-level topic.
        $DB->insert_record('block_exacomptopicvisibility', [
            'courseid' => $course->id, 'topicid' => $topicid, 'studentid' => 0, 'visible' => 1, 'niveauid' => null,
        ]);
        $DB->insert_record('block_exacompsettings', [
            'courseid' => $course->id, 'show_all_descriptors' => 1, 'uses_activities' => 0,
        ]);
        $this->setUser($owner);
        $this->assertContains($descriptorid, block_exaport_competence_tree_descriptorids(
            \block_exacomp\api::get_comp_tree_for_exaport($owner->id)));

        $args = ['itemid' => 0, 'courseid' => $course->id, 'pending' => 1, 'selectedids' => ''];
        $opened = $this->request(item_competences::class, $args, false);
        $this->assertStringContainsString('Visible competency', $opened['html']);
        $applied = $this->request(item_competences::class, $args + ['competenceids' => (string)$descriptorid]);
        $this->assertTrue($applied['submitted']);
        $this->assertSame([$descriptorid], $applied['result']['competenceids']);
        $this->assertStringContainsString('Visible competency', $applied['result']['content']);
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
        $this->assertSame(0, $DB->count_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, ['eportfolioitem' => 1]));
        $this->assertSame(0, $DB->count_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM, ['eportfolioitem' => 1]));

        $data = (object)['id' => 0, 'courseid' => $course->id, 'name' => 'New item with competencies',
            'contentblocks' => json_encode(item_editor::initial_state(null), JSON_THROW_ON_ERROR),
            'competenceids' => implode(',', $applied['result']['competenceids']), 'competencesmodified' => 1];
        $plan = item_editor::validate_request(0, (int)$course->id,
            $data->contentblocks, $data->competenceids, (array)$data, true);
        $this->assertNull($plan['item']);
        $this->assertSame([$descriptorid], $plan['competenceids']);
        $itemid = item_editor::commit(0, (int)$course->id, $data, static function(\stdClass $metadata): int {
            global $DB, $USER;
            // The parent first exists inside the final Save transaction, after all pending validation.
            $metadata->id = (int)$DB->insert_record('block_exaportitem', ['userid' => $USER->id,
                'courseid' => $metadata->courseid, 'name' => $metadata->name, 'type' => 'note']);
            return $metadata->id;
        }, true);
        $this->assertSame(1, $DB->count_records('block_exaportitem'));
        $assignment = $DB->get_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            ['activityid' => $itemid, 'eportfolioitem' => 1], '*', MUST_EXIST);
        $this->assertSame($descriptorid, (int)$assignment->compid);
        $this->assertSame($data->name, $assignment->activitytitle);
        $this->assertSame($course->shortname, $assignment->coursetitle);
        $review = $DB->get_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
            ['activityid' => $itemid, 'eportfolioitem' => 1], '*', MUST_EXIST);
        $this->assertSame($descriptorid, (int)$review->compid);
        $this->assertSame((int)$owner->id, (int)$review->userid);
        $this->assertSame((int)$owner->id, (int)$review->reviewerid);
        $this->assertSame(0, $DB->count_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            ['activityid' => 0, 'eportfolioitem' => 1]));
    }

    public function test_existing_competence_apply_and_validation_error_preserve_pending_selection(): void {
        global $DB, $USER;
        set_config('block_exaport_enable_interaction_competences', 1);
        set_config('block_exaport_app_alloweditdelete', 1);
        $itemid = $DB->insert_record('block_exaportitem', ['userid' => $USER->id,
            'courseid' => SITEID, 'name' => 'Pending competencies', 'type' => 'note']);
        $ids = [];
        foreach (['Former first', 'Former second'] as $title) {
            $id = (int)$DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => $title]);
            $ids[] = $id;
            $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                ['activityid' => $itemid, 'compid' => $id, 'eportfolioitem' => 1]);
        }
        $args = ['itemid' => $itemid, 'courseid' => SITEID,
            'pending' => 1, 'selectedids' => implode(',', $ids)];
        $accepted = $this->request(item_competences::class, $args + ['competenceids' => (string)$ids[0]]);
        $this->assertTrue($accepted['submitted']);
        $this->assertSame([$ids[0]], $accepted['result']['competenceids']);
        $this->assertEqualsCanonicalizing($ids, $DB->get_fieldset_select(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            'compid', 'activityid = ? AND eportfolioitem = 1', [$itemid]));

        $unavailable = (int)$DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => 'Inaccessible choice']);
        $rejected = $this->request(item_competences::class,
            $args + ['competenceids' => $ids[0] . ',' . $unavailable]);
        $this->assertFalse($rejected['submitted']);
        $dom = new \DOMDocument();
        @$dom->loadHTML($rejected['html']);
        $xpath = new \DOMXPath($dom);
        $first = $xpath->query('//input[@data-region="competence-checkbox"][@value="' . $ids[0] . '"]')->item(0);
        $second = $xpath->query('//input[@data-region="competence-checkbox"][@value="' . $ids[1] . '"]')->item(0);
        $this->assertTrue($first->hasAttribute('checked'));
        $this->assertFalse($second->hasAttribute('checked'));
        $this->assertStringNotContainsString('Inaccessible choice', $rejected['html']);
    }

    public static function pending_content_markers(): array {
        return ['explicit flag and state' => [1], 'block state alone' => [0]];
    }

    /** @dataProvider pending_content_markers */
    public function test_pending_content_markers_stage_existing_changes_until_main_save(int $pending): void {
        global $DB, $USER;
        $itemid = $DB->insert_record('block_exaportitem', ['userid' => $USER->id,
            'courseid' => SITEID, 'name' => 'Saved item', 'type' => 'note']);
        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $block = block_exaport_create_link_content_block((int)$itemid, 'Original', 'https://example.test/original');
        $state = item_content_edit::from_record($block);
        $args = array_replace($this->args('link', $state), ['itemid' => $itemid,
            'title' => 'Pending title', 'url' => 'https://example.test/changed', 'pending' => $pending]);
        $response = $this->request(item_content::class, $args);
        $this->assertTrue($response['submitted']);
        $this->assertSame('Pending title', $response['result']['block']['title']);
        $this->assertSame('Original', $DB->get_field('block_exaportitemblock', 'title', ['id' => $block->id]));
        $this->commit_block($item, $response['result']['block']);
        $this->assertSame('Pending title', $DB->get_field('block_exaportitemblock', 'title', ['id' => $block->id]));
        $this->assertSame('https://example.test/changed', $DB->get_field('block_exaportitemblock', 'url', ['id' => $block->id]));
    }

    public function test_explicit_pending_content_without_block_state_cannot_save_immediately(): void {
        global $DB, $USER;
        $itemid = $DB->insert_record('block_exaportitem', ['userid' => $USER->id,
            'courseid' => SITEID, 'name' => 'Saved item', 'type' => 'note']);
        $block = block_exaport_create_link_content_block((int)$itemid, 'Original', 'https://example.test/original');
        $args = ['itemid' => $itemid, 'courseid' => SITEID, 'blockid' => $block->id,
            'contenttype' => 'link', 'operation' => 'save', 'pending' => 1,
            'title' => 'Must not save', 'url' => 'https://example.test/changed'];
        try {
            $this->request(item_content::class, $args);
            $this->fail('A pending submit without block state was accepted');
        } catch (\moodle_exception $exception) {
            $this->assertSame('itemeditorinvalid', $exception->errorcode);
        }
        $this->assertSame('Original', $DB->get_field('block_exaportitemblock', 'title', ['id' => $block->id]));
    }

    public function test_pending_competence_markers_wait_for_main_save(): void {
        global $DB, $USER;
        set_config('block_exaport_enable_interaction_competences', 1);
        $itemid = $DB->insert_record('block_exaportitem', ['userid' => $USER->id,
            'courseid' => SITEID, 'name' => 'Saved item', 'type' => 'note']);
        $id = (int)$DB->insert_record(BLOCK_EXACOMP_DB_DESCRIPTORS, ['title' => 'Retained choice']);
        $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            ['activityid' => $itemid, 'compid' => $id, 'eportfolioitem' => 1]);
        $response = $this->request(item_competences::class, ['itemid' => $itemid, 'courseid' => SITEID,
            'pending' => 1, 'selectedids' => (string)$id, 'competenceids' => '']);
        $this->assertTrue($response['submitted']);
        $this->assertSame([], $response['result']['competenceids']);
        $this->assertTrue($DB->record_exists(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            ['activityid' => $itemid, 'compid' => $id, 'eportfolioitem' => 1]));
        $withoutflag = $this->request(item_competences::class, ['itemid' => $itemid, 'courseid' => SITEID,
            'pending' => 0, 'selectedids' => (string)$id, 'competenceids' => '']);
        $this->assertTrue($withoutflag['submitted']);
        $this->assertTrue($DB->record_exists(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            ['activityid' => $itemid, 'compid' => $id, 'eportfolioitem' => 1]));
        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $data = (object)['id' => $itemid, 'courseid' => SITEID, 'name' => $item->name,
            'contentblocks' => json_encode(item_editor::initial_state($item), JSON_THROW_ON_ERROR),
            'competenceids' => '', 'competencesmodified' => 1];
        item_editor::commit((int)$itemid, SITEID, $data, static function(\stdClass $metadata): int {
            return (int)$metadata->id;
        }, true);
        $this->assertFalse($DB->record_exists(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
            ['activityid' => $itemid, 'compid' => $id, 'eportfolioitem' => 1]));
    }
}
