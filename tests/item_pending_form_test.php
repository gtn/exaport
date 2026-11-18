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
            'itemeditsections' => ['mainsettings' => true, 'content' => true, 'competences' => $competences]];
    }

    private function values(array $custom): array {
        $data = ['id' => (int)($custom['current']->id ?? 0), 'courseid' => SITEID, 'action' => $custom['action'],
            'name' => $custom['current']->name ?? 'New item title', 'categoryids' => [],
            'iconfile' => file_get_unused_draft_itemid(), 'pendingeditor' => 1, 'competencesmodified' => 0,
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

    private function sharing_fixture(bool $saved): array {
        global $DB, $USER;
        set_config('block_exaport_disable_shareall', 0);
        set_config('block_exaport_disable_sharegroups', 0);
        $recipient = $this->getDataGenerator()->create_user(['firstname' => 'New recipient']);
        $previous = $this->getDataGenerator()->create_user(['firstname' => 'Previous recipient']);
        $group = $this->getDataGenerator()->create_cohort();
        cohort_add_member($group->id, $USER->id);
        $item = null;
        if ($saved) {
            $id = $DB->insert_record('block_exaportitem', ['userid' => $USER->id, 'courseid' => SITEID,
                'name' => 'Saved sharing', 'type' => 'note', 'shareall' => 0]);
            $item = $DB->get_record('block_exaportitem', ['id' => $id], '*', MUST_EXIST);
            $DB->insert_record('block_exaportitemshar', ['itemid' => $id, 'userid' => $previous->id, 'notify' => 1]);
        }
        return [$this->customdata($item), $recipient, $previous, $group];
    }

    private function sharing_bridge(\DOMXPath $dom): array {
        $root = $dom->query('//*[@id="item-sharing"]')->item(0);
        return json_decode($root->getAttribute('data-submitted-sharing'), true, 32, JSON_THROW_ON_ERROR);
    }

    /** Serialize the sharing controls present before the group AJAX callback, keeping the other posted fields. */
    private function sharing_retry_fields(array $posted, \DOMXPath $dom): array {
        foreach (['shareenabled', 'shareall', 'shareusers', 'notifyusers', 'sharegroups',
                'exaport_userlist_ready', 'exaport_grouplist_ready'] as $name) {
            unset($posted[$name]);
        }
        $pairs = [];
        foreach ($dom->query('//*[@id="item-sharing"]//input[@name]') as $input) {
            if ($input->hasAttribute('disabled') ||
                    (in_array($input->getAttribute('type'), ['checkbox', 'radio'], true) && !$input->hasAttribute('checked'))) {
                continue;
            }
            $pairs[] = rawurlencode($input->getAttribute('name')) . '=' . rawurlencode($input->getAttribute('value'));
        }
        parse_str(implode('&', $pairs), $sharing);
        return array_replace($posted, $sharing);
    }

    public static function sharing_error_contexts(): array {
        return ['new name error' => [false, false], 'saved name error' => [true, false],
            'new quota error' => [false, true], 'saved quota error' => [true, true]];
    }

    /** @dataProvider sharing_error_contexts */
    public function test_failed_save_restores_submitted_users_notifications_and_groups(bool $saved, bool $quota): void {
        global $DB;
        [$custom, $recipient, $previous, $group] = $this->sharing_fixture($saved);
        $posted = $this->values($custom) + ['shareenabled' => 1, 'shareall' => 0,
            'exaport_userlist_ready' => 1, 'exaport_grouplist_ready' => 1,
            'shareusers' => [$recipient->id => $recipient->id], 'notifyusers' => [$recipient->id => $recipient->id],
            'sharegroups' => [$group->id => $group->id]];
        if ($quota) {
            set_config('block_exaport_userquota', 1);
            $posted['contentblocks'] = json_encode($this->file_state(), JSON_THROW_ON_ERROR);
        } else {
            $posted['name'] = '';
        }
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $dom = $this->dom($form->render());
        $this->assertSame(1, $dom->query('//input[@name="shareenabled"][@checked]')->length);
        $this->assertSame(1, $dom->query('//input[@name="shareall"][@value="0"][@checked]')->length);
        $this->assertSame($saved ? '1' : '0', $dom->query('//*[@id="item-sharing"]')->item(0)->getAttribute('data-configured'));
        $bridge = $this->sharing_bridge($dom);
        $this->assertSame([(int)$recipient->id], $bridge['users']);
        $this->assertSame([(int)$recipient->id], $bridge['notify']);
        $this->assertSame([(int)$group->id], $bridge['groups']);
        $rows = array_merge(...array_map(static function(array $course): array { return $course['users'] ?? []; }, $bridge['courses']));
        $this->assertSame([(int)$recipient->id], array_unique(array_column($rows, 'id')));
        $this->assertTrue($rows[0]['notify_user']);
        if ($saved) {
            $this->assertTrue($DB->record_exists('block_exaportitemshar',
                ['itemid' => $custom['current']->id, 'userid' => $previous->id]));
        } else {
            $this->assertSame(0, $DB->count_records('block_exaportitem'));
        }
    }

    public static function sharing_clear_contexts(): array {
        return ['new off' => [false, false], 'saved off' => [true, false],
            'new empty users' => [false, true], 'saved empty users' => [true, true]];
    }

    /** @dataProvider sharing_clear_contexts */
    public function test_failed_save_preserves_sharing_off_and_explicit_empty_users(bool $saved, bool $enabled): void {
        [$custom] = $this->sharing_fixture($saved);
        $posted = $this->values($custom) + ['exaport_userlist_ready' => 1, 'exaport_grouplist_ready' => 1];
        $posted['name'] = '';
        if ($enabled) {
            $posted['shareenabled'] = 1;
            $posted['shareall'] = 0;
        }
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $dom = $this->dom($form->render());
        $this->assertSame($enabled ? 1 : 0, $dom->query('//input[@name="shareenabled"][@checked]')->length);
        $bridge = $this->sharing_bridge($dom);
        $this->assertSame([], $bridge['users']);
        $this->assertSame([], $bridge['notify']);
        $this->assertSame([], $bridge['groups']);
        $this->assertSame(1, $dom->query('//*[@data-region="sharing-settings"]//*[@data-region="groups-settings"]'
            . '/*[@data-region="submitted-group-selection"]')->length);
        foreach ($bridge['courses'] as $course) {
            $this->assertEmpty($course['users'] ?? []);
        }
        if (!$enabled) {
            $retry = $this->sharing_retry_fields($posted, $dom);
            $form = $this->form($custom, $retry);
            $this->assertNull($form->get_data());
            $retrydom = $this->dom($form->render());
            $this->assertSame(0, $retrydom->query('//input[@name="shareenabled"][@checked]')->length);
            $this->assertSame([], $this->sharing_bridge($retrydom)['groups']);
        }
    }

    public static function sharing_unknown_user_modes(): array {
        return ['users' => [0], 'everyone' => [1], 'groups' => [2]];
    }

    /** @dataProvider sharing_unknown_user_modes */
    public function test_incomplete_user_list_restores_controls_without_claiming_an_empty_selection(int $mode): void {
        [$custom, $recipient, , $group] = $this->sharing_fixture(true);
        $posted = $this->values($custom) + ['shareenabled' => 1, 'shareall' => $mode,
            'exaport_userlist_ready' => 0, 'exaport_grouplist_ready' => 1,
            'shareusers' => [$recipient->id => $recipient->id], 'sharegroups' => [$group->id => $group->id]];
        $posted['name'] = '';
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $dom = $this->dom($form->render());
        $this->assertSame(1, $dom->query('//input[@name="shareenabled"][@checked]')->length);
        $this->assertSame(1, $dom->query('//input[@name="shareall"][@value="' . $mode . '"][@checked]')->length);
        $bridge = $this->sharing_bridge($dom);
        $this->assertNull($bridge['users']);
        $this->assertNull($bridge['notify']);
        $this->assertNull($bridge['courses']);
        $this->assertSame([(int)$group->id], $bridge['groups']);
    }

    public static function sharing_unknown_group_contexts(): array {
        $cases = [];
        foreach (['users' => 0, 'everyone' => 1, 'groups' => 2] as $name => $mode) {
            $cases[$name . ' missing marker'] = [$mode, null];
            $cases[$name . ' loading marker'] = [$mode, 0];
        }
        return $cases;
    }

    /** @dataProvider sharing_unknown_group_contexts */
    public function test_incomplete_group_list_is_unknown_in_every_audience(int $mode, ?int $ready): void {
        global $DB;
        [$custom, , , $group] = $this->sharing_fixture(true);
        $DB->insert_record('block_exaportitemgroupshar', ['itemid' => $custom['current']->id, 'groupid' => $group->id]);
        $posted = $this->values($custom) + ['shareenabled' => 1, 'shareall' => $mode, 'exaport_userlist_ready' => 1,
            'sharegroups' => [$group->id => $group->id]];
        $posted['name'] = '';
        if ($ready !== null) {
            $posted['exaport_grouplist_ready'] = $ready;
        }
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $dom = $this->dom($form->render());
        $this->assertSame(1, $dom->query('//input[@name="shareall"][@value="' . $mode . '"][@checked]')->length);
        $bridge = $this->sharing_bridge($dom);
        $this->assertNull($bridge['groups']);
        $this->assertSame('0', $dom->query('//input[@name="exaport_grouplist_ready"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $dom->query('//*[@data-region="submitted-group-selection"]')->length);
        $this->assertSame([], $bridge['users']);
        $this->assertTrue($DB->record_exists('block_exaportitemgroupshar',
            ['itemid' => $custom['current']->id, 'groupid' => $group->id]));
    }

    public static function group_retry_contexts(): array {
        $cases = [];
        foreach ([false, true] as $saved) {
            foreach (['users' => 0, 'everyone' => 1, 'groups' => 2] as $name => $mode) {
                foreach ([false, true] as $empty) {
                    $cases[($saved ? 'saved ' : 'new ') . $name . ($empty ? ' clear' : ' selected')] = [$saved, $mode, $empty];
                }
            }
        }
        return $cases;
    }

    /** @dataProvider group_retry_contexts */
    public function test_group_selection_survives_a_second_error_before_ajax_finishes(bool $saved, int $mode, bool $empty): void {
        global $DB;
        [$custom, , , $group] = $this->sharing_fixture($saved);
        if ($saved) {
            $previous = $this->getDataGenerator()->create_cohort();
            $DB->insert_record('block_exaportitemgroupshar',
                ['itemid' => $custom['current']->id, 'groupid' => $previous->id]);
        }
        $selected = $empty ? [] : [(int)$group->id];
        $posted = $this->values($custom) + ['shareenabled' => 1, 'shareall' => $mode,
            'exaport_userlist_ready' => 1, 'exaport_grouplist_ready' => 1, 'sharegroups' => $selected];
        $posted['name'] = '';
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $first = $this->dom($form->render());
        $retry = $this->sharing_retry_fields($posted, $first);
        $retry['name'] = 'Retry blocked by quota';
        set_config('block_exaport_userquota', 1);
        $retry['contentblocks'] = json_encode($this->file_state(), JSON_THROW_ON_ERROR);
        $form = $this->form($custom, $retry);
        $this->assertNull($form->get_data());
        $second = $this->dom($form->render());
        $this->assertSame($selected, $this->sharing_bridge($second)['groups']);
        $this->assertSame('1', $first->query('//input[@name="exaport_grouplist_ready"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $first->query('//*[@data-region="groups-settings"]/*[@data-region="submitted-group-selection"]')->length);
        $this->assertSame(count($selected), $first->query('//*[@data-region="submitted-group-selection"]/input')->length);
        $this->assertSame(0, $first->query('//*[@id="sharing-grouplist"]//*[@name]')->length);
        if ($saved) {
            $this->assertTrue($DB->record_exists('block_exaportitemgroupshar',
                ['itemid' => $custom['current']->id, 'groupid' => $previous->id]));
        } else {
            $this->assertSame(0, $DB->count_records('block_exaportitem'));
        }
    }

    public function test_sharing_restore_does_not_fill_missing_post_controls_from_query_parameters(): void {
        [$custom, $recipient, , $group] = $this->sharing_fixture(false);
        $_GET = ['shareenabled' => 1, 'shareall' => 2, 'exaport_userlist_ready' => 1, 'exaport_grouplist_ready' => 1,
            'shareusers' => [$recipient->id => $recipient->id], 'notifyusers' => [$recipient->id => $recipient->id],
            'sharegroups' => [$group->id => $group->id]];
        $posted = $this->values($custom);
        $posted['name'] = '';
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $dom = $this->dom($form->render());
        $this->assertSame(0, $dom->query('//input[@name="shareenabled"][@checked]')->length);
        $this->assertSame(1, $dom->query('//input[@name="shareall"][@value="0"][@checked]')->length);
        $bridge = $this->sharing_bridge($dom);
        $this->assertNull($bridge['users']);
        $this->assertNull($bridge['notify']);
        $this->assertNull($bridge['groups']);

        // Completed but omitted checkbox sets mean an explicit empty selection, even with stale URL values.
        $posted['exaport_userlist_ready'] = $posted['exaport_grouplist_ready'] = 1;
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $bridge = $this->sharing_bridge($this->dom($form->render()));
        $this->assertSame([], $bridge['users']);
        $this->assertSame([], $bridge['notify']);
        $this->assertSame([], $bridge['groups']);
    }

    public function test_unsubmitted_sharing_parameters_do_not_replace_saved_defaults(): void {
        $custom = $this->customdata();
        $form = new \block_exaport_item_edit_form('/blocks/exaport/item.php', $custom, 'post', '', [], true,
            ['shareenabled' => 1, 'shareall' => 1, 'exaport_userlist_ready' => 1]);
        $dom = $this->dom($form->render());
        $this->assertSame(0, $dom->query('//input[@name="shareenabled"][@checked]')->length);
        $this->assertSame('', $dom->query('//*[@id="item-sharing"]')->item(0)->getAttribute('data-submitted-sharing'));
        $this->assertSame('0', $dom->query('//input[@name="exaport_grouplist_ready"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $dom->query('//*[@data-region="submitted-group-selection"]')->length);
    }

    public function test_content_has_its_own_expanded_section_and_normal_form_row_with_native_help(): void {
        $form = $this->form($this->customdata());
        $dom = $this->dom($form->render());
        $row = $dom->query('//*[@id="fitem_id_editorcontent"]')->item(0);
        $this->assertNotNull($row);
        $label = $dom->query('./div[contains(@class, "col-form-label")]', $row)->item(0);
        $controls = $dom->query('./div[contains(@class, "felement")]', $row)->item(0);
        $this->assertNotNull($label);
        $this->assertNotNull($controls);
        $this->assertStringContainsString(get_string('viewcontent', 'block_exaport'), $label->textContent);
        $icons = $dom->query('.//a[@data-toggle="popover" or @data-bs-toggle="popover"]', $label);
        $this->assertSame(1, $icons->length);
        $this->assertSame(1, $dom->query('.//section[contains(@class, "exaport-item-content-section")]', $controls)->length);
        $section = $dom->query('ancestor::fieldset[@id="id_itemcontent"]', $row)->item(0);
        $this->assertNotNull($section);
        $this->assertStringNotContainsString('collapsed', $section->getAttribute('class'));
        $this->assertSame(0, $dom->query('ancestor::fieldset[@id="id_mainsettings"]', $row)->length);
        $this->assertSame(0, $dom->query('.//*[@id="fitem_id_iconfile"]', $section)->length);
        $this->assertSame(0, $dom->query('.//h2 | .//h3', $row)->length);

        // Moodle 4.5 and 5.2 use different Bootstrap attribute names for the same native help icon.
        $icon = $icons->item(0);
        $content = $icon->getAttribute('data-bs-content') ?: $icon->getAttribute('data-content');
        $help = get_formatted_help_string('itemeditorpendinghint', 'block_exaport', false);
        $this->assertSame(trim($help->text . ' ' . ($help->completedoclink ?? '')), trim($content));
        $this->assertSame(0, $dom->query('//*[@id="fitem_id_editorpendinghint"]'
            . ' | //*[@id="id_editorpendinghint"] | //*[@name="editorpendinghint"]')->length);
        $this->assertStringNotContainsString(trim(strip_tags($help->text)), $controls->textContent);
    }

    public function test_thumbnail_help_uses_the_actual_field_label(): void {
        $dom = $this->dom($this->form($this->customdata())->render());
        $icons = $dom->query('//*[@id="fitem_id_iconfile"]//div[contains(@class, "col-form-label")]'
            . '//a[@data-toggle="popover" or @data-bs-toggle="popover"]');
        $this->assertSame(1, $icons->length);
        $help = get_formatted_help_string('iconfile', 'block_exaport', false);
        $content = $icons->item(0)->getAttribute('data-bs-content') ?: $icons->item(0)->getAttribute('data-content');
        $this->assertSame(trim($help->text . ' ' . ($help->completedoclink ?? '')), trim($content));
    }

    public function test_creation_offers_content_and_competencies_before_an_item_exists(): void {
        global $DB;
        set_config('block_exaport_enable_interaction_competences', 1);
        if (!block_exaport_check_competence_interaction()) {
            $this->markTestSkipped('The competency section requires Exacomp.');
        }
        $form = $this->form($this->customdata(null, true));
        $dom = $this->dom($form->render());
        $this->assertSame(1, $dom->query('//*[@id="fitem_id_editorcontent"]')->length);
        $this->assertSame(1, $dom->query('//*[@id="id_itemcompetences"]')->length);
        $this->assertSame(1, $dom->query('//button[contains(@class,"exaport-item-content-add-button")]')->length);
        $this->assertSame(1, $dom->query('//*[@data-action="open-competence-picker"]')->length);
        foreach (['name', 'categoryids[]', 'iconfile', 'contentblocks', 'competenceids',
                'pendingeditor', 'competencesmodified'] as $name) {
            $this->assertGreaterThan(0, $dom->query('//*[@name="' . $name . '"]')->length, $name);
        }
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_normal_validation_error_keeps_pending_json_and_upload_references(): void {
        $custom = $this->customdata();
        $posted = $this->values($custom);
        $posted['name'] = '';
        $posted['competencesmodified'] = 1;
        $state = $this->file_state();
        $posted['contentblocks'] = json_encode($state, JSON_THROW_ON_ERROR);
        $form = $this->form($custom, $posted);
        $this->assertNull($form->get_data());
        $dom = $this->dom($form->render());
        $this->assertSame($posted['contentblocks'],
            $dom->query('//input[@name="contentblocks"]')->item(0)->getAttribute('value'));
        $this->assertSame('1', $dom->query('//input[@name="pendingeditor"]')->item(0)->getAttribute('value'));
        $this->assertSame('1', $dom->query('//input[@name="competencesmodified"]')->item(0)->getAttribute('value'));
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
