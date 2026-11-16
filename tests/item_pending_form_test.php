<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../lib/item_edit_form.php');

use block_exaport\local\item_editor;

/** Check pending values through the real Moodle main form, including redisplay after errors. */
final class item_pending_form_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG, $PAGE;
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $CFG->usetags = false;
        set_config('block_exaport_enable_interaction_competences', 0);
        set_config('block_exaport_multiple_files_in_item', 1);
        set_config('block_exaport_max_uploadfile_size', 1048576);
        set_config('block_exaport_userquota', 1048576);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/blocks/exaport/item.php', ['action' => 'add', 'courseid' => SITEID]);
    }

    private function customdata(?\stdClass $item = null, bool $competences = false): array {
        global $USER;
        return ['current' => $item, 'course' => get_course(SITEID), 'type' => 'note',
            'action' => $item ? 'edit' : 'add', 'allowedit' => true, 'allowresubmission' => false,
            'cattype' => '', 'catid' => 0, 'useTextareas' => ['intro' => false, 'project_description' => false,
                'project_process' => false, 'project_result' => false],
            'textfieldoptions' => ['trusttext' => true, 'subdirs' => true, 'maxfiles' => 99,
                'context' => \context_user::instance($USER->id)],
            'pendingeditor' => true, 'editorstate' => item_editor::initial_state($item),
            'editorbaseline' => item_editor::baseline($item, SITEID, $competences),
            'itemeditsections' => ['mainsettings' => true, 'content' => true, 'competences' => $competences]];
    }

    private function values(array $custom): array {
        $data = ['id' => (int)($custom['current']->id ?? 0), 'courseid' => SITEID, 'action' => $custom['action'],
            'name' => $custom['current']->name ?? 'New item title', 'categoryids' => [],
            'iconfile' => file_get_unused_draft_itemid(), 'editorbaseline' => $custom['editorbaseline'],
            'contentblocks' => json_encode($custom['editorstate'], JSON_THROW_ON_ERROR), 'competenceids' => ''];
        foreach (['intro', 'project_description', 'project_process', 'project_result'] as $field) {
            $data[$field . '_editor'] = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        }
        return $data;
    }

    private function form(array $custom, ?array $posted = null): \block_exaport_item_edit_form {
        $ajax = $posted === null ? null : \block_exaport_item_edit_form::mock_ajax_submit($posted);
        $form = new \block_exaport_item_edit_form('/blocks/exaport/item.php', $custom, 'post', '', [], true, $ajax);
        $form->set_data((object)$this->values($custom));
        return $form;
    }

    private function dom(string $html): \DOMXPath {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        return new \DOMXPath($dom);
    }

    private function file_state(): array {
        global $USER;
        $draft = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draft, 'filepath' => '/',
            'filename' => 'pending.txt', 'source' => serialize((object)['source' => 'pending.txt'])], 'Pending bytes');
        return ['blocks' => [['key' => 'new-file', 'id' => 0, 'type' => 'file', 'title' => 'Pending upload',
            'modified' => true, 'files_filemanager' => $draft]], 'removed' => []];
    }

    public function test_creation_offers_content_and_competencies_before_an_item_exists(): void {
        global $DB;
        set_config('block_exaport_enable_interaction_competences', 1);
        if (!block_exaport_check_competence_interaction()) {
            $this->markTestSkipped('The competency section requires Exacomp.');
        }
        $form = $this->form($this->customdata(null, true));
        $dom = $this->dom($form->render());
        $this->assertSame(1, $dom->query('//*[@id="id_itemcontent"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="id_itemcompetences"]')->length);
        $this->assertSame(1, $dom->query('//button[contains(@class,"exaport-item-content-add-button")]')->length);
        $this->assertSame(1, $dom->query('//*[@data-action="open-competence-picker"]')->length);
        foreach (['name', 'categoryids[]', 'iconfile', 'contentblocks', 'competenceids', 'editorbaseline'] as $name) {
            $this->assertGreaterThan(0, $dom->query('//*[@name="' . $name . '"]')->length, $name);
        }
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_normal_validation_error_keeps_pending_json_and_upload_references(): void {
        $custom = $this->customdata();
        $posted = $this->values($custom);
        $posted['name'] = '';
        $state = $this->file_state();
        $posted['contentblocks'] = json_encode($state, JSON_THROW_ON_ERROR);
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $dom = $this->dom($form->render());
        $this->assertSame($posted['contentblocks'],
            $dom->query('//input[@name="contentblocks"]')->item(0)->getAttribute('value'));
        $this->assertSame($posted['editorbaseline'],
            $dom->query('//input[@name="editorbaseline"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $dom->query('//div[@data-block-key="new-file"]')->length);
        $files = \block_exaport\local\item_content_edit::draft_files($state['blocks'][0]['files_filemanager']);
        $this->assertSame('Pending bytes', reset($files)->get_content());
    }

    public function test_missing_content_payload_is_rejected_but_explicit_empty_collection_is_valid(): void {
        $custom = $this->customdata();
        $posted = $this->values($custom);
        unset($posted['contentblocks']);
        $this->assertNull($this->form($custom, $posted)->get_data());
        $posted['contentblocks'] = json_encode(['blocks' => [], 'removed' => []]);
        $data = $this->form($custom, $posted)->get_data();
        $this->assertNotNull($data);
        $this->assertSame($posted['contentblocks'], $data->contentblocks);
    }

    public function test_missing_competency_selection_is_rejected_but_explicit_empty_is_valid(): void {
        set_config('block_exaport_enable_interaction_competences', 1);
        if (!block_exaport_check_competence_interaction()) {
            $this->markTestSkipped('The competency section requires Exacomp.');
        }
        $custom = $this->customdata(null, true);
        $posted = $this->values($custom);
        unset($posted['competenceids']);
        $this->assertNull($this->form($custom, $posted)->get_data());
        $posted['competenceids'] = '';
        $this->assertNotNull($this->form($custom, $posted)->get_data());
    }

    public function test_submitted_hidden_json_is_escaped_while_preserving_the_original_value(): void {
        $custom = $this->customdata();
        $posted = $this->values($custom);
        $posted['name'] = '';
        $posted['contentblocks'] = json_encode(['blocks' => [['key' => 'new-link', 'id' => 0,
            'type' => 'link', 'title' => '"><script>window.pendingFormAttack=1</script>', 'modified' => true,
            'url' => 'https://example.test/?a=1&b=2']], 'removed' => []], JSON_THROW_ON_ERROR);
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $html = $form->render();
        $dom = $this->dom($html);
        $this->assertSame($posted['contentblocks'],
            $dom->query('//input[@name="contentblocks"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $dom->query('//script[contains(text(), "pendingFormAttack")]')->length);
    }

    public function test_posted_pending_preview_survives_a_new_teacher_lock(): void {
        global $DB, $USER;
        $itemid = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'courseid' => SITEID,
            'name' => 'Saved item', 'type' => 'note', 'url' => '', 'attachment' => '']);
        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $custom = $this->customdata($item);
        $posted = $this->values($custom);
        $posted['contentblocks'] = json_encode(['blocks' => [['key' => 'new-link', 'id' => 0,
            'type' => 'link', 'title' => 'Unsaved reference', 'modified' => true,
            'url' => 'https://example.test/pending']], 'removed' => []]);
        set_config('block_exaport_app_alloweditdelete', 0);
        set_config('block_exaport_enable_interaction_competences', 1);
        if (!block_exaport_check_competence_interaction()) {
            $this->markTestSkipped('Teacher-review locking requires Exacomp.');
        }
        $DB->insert_record(BLOCK_EXACOMP_DB_ITEM_MM, ['competence_type' => BLOCK_EXACOMP_TYPE_EXAMPLE,
            'exacomp_record_id' => 1, 'itemid' => $itemid, 'timecreated' => time(), 'teachervalue' => 1]);
        $custom['allowedit'] = false;
        $form = $this->form($custom, $posted);
        $html = $form->render();
        $dom = $this->dom($html);
        $this->assertSame($posted['contentblocks'],
            $dom->query('//input[@name="contentblocks"]')->item(0)->getAttribute('value'));
        $this->assertStringContainsString('Unsaved reference', $html);
        $this->assertSame(0, $dom->query('//*[@data-action="open-competence-picker"]')->length);
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }
}
