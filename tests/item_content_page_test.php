<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/item_content_helpers.php');

/**
 * Exercise the public standalone pages through the shared Moodle form.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_content_page_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('block_exaport_enable_interaction_competences', 0);
    }

    /** @return array Supported standalone editors. */
    public static function content_types(): array {
        return [
            'text' => ['text'], 'link' => ['link'], 'file' => ['file'],
            'personal' => ['personal'], 'cv' => ['cv'], 'badge' => ['badge'],
        ];
    }

    /** Create an owned item and any centrally managed sources needed by the picker. */
    private function fixture(string $type): array {
        global $DB, $USER;

        $itemid = $DB->insert_record('block_exaportitem', [
            'userid' => $USER->id, 'courseid' => SITEID, 'name' => 'Standalone fixture', 'type' => 'note',
        ]);
        $data = ['courseid' => SITEID, 'itemid' => $itemid, 'blockid' => 0, 'operation' => 'save',
            'contenttype' => $type, 'title' => 'Created through the page'];
        if ($type === 'link') {
            $data['url'] = 'https://example.com/';
        } else if ($type === 'text') {
            $data['content_editor'] = ['text' => '<p>Original text</p>', 'format' => FORMAT_HTML, 'itemid' => 0];
        } else if ($type === 'file') {
            $draftid = file_get_unused_draft_itemid();
            get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
                'itemid' => $draftid, 'filepath' => '/', 'filename' => 'example.txt',
            ], 'uploaded file bytes');
            $data['files_filemanager'] = $draftid;
        } else if ($type === 'personal') {
            $data['selection'] = ['firstname'];
        } else if ($type === 'cv') {
            $DB->insert_record('block_exaportresume', ['user_id' => $USER->id, 'cover' => '<p>About me</p>']);
            $data['selection'] = ['cover'];
        } else if ($type === 'badge') {
            set_config('enablebadges', 1);
            $generator = $this->getDataGenerator()->get_plugin_generator('core_badges');
            $badge = $generator->create_badge(['name' => 'Earned badge']);
            $generator->create_issued_badge(['badgeid' => $badge->id, 'userid' => $USER->id]);
            $data['selection'] = [(string)$badge->id];
        }
        return [$itemid, $data];
    }

    /** Run the actual page; Moodle's CLI redirect exception indicates a completed submission. */
    private function submit(string $type, array $data, bool $validsesskey = true): void {
        global $CFG, $DB, $USER, $PAGE, $COURSE, $OUTPUT, $SESSION, $SCRIPT;

        $oldget = $_GET;
        $oldpost = $_POST;
        $oldscript = $SCRIPT;
        $oldpage = $PAGE;
        $oldoutput = $OUTPUT;
        $SCRIPT = '/blocks/exaport/item_content_' . $type . '.php';
        // Each include represents a fresh HTTP request, including Moodle's page/theme lifecycle.
        $PAGE = new \moodle_page();
        $PAGE->set_url($SCRIPT);
        $OUTPUT = new \bootstrap_renderer();
        $_GET = [];
        \block_exaport\form\item_content::mock_submit($data);
        if (!$validsesskey) {
            $_POST['sesskey'] = 'invalid';
        }
        ob_start();
        try {
            include(__DIR__ . '/../item_content_' . $type . '.php');
            $this->fail('The valid submission should redirect');
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'redirecterrordetected') {
                throw $exception;
            }
        } finally {
            ob_end_clean();
            $_GET = $oldget;
            $_POST = $oldpost;
            $SCRIPT = $oldscript;
            $PAGE = $oldpage;
            $OUTPUT = $oldoutput;
        }
    }

    /**
     * Creating, editing and deleting use the same persistence path for all six types.
     *
     * @dataProvider content_types
     */
    public function test_standalone_create_edit_and_delete(string $type): void {
        global $DB, $USER;

        [$itemid, $data] = $this->fixture($type);
        $this->submit($type, $data);
        $block = block_exaport_get_item_content_blocks($itemid)[0];
        $this->assertSame($type, $block->type);
        $this->assertSame('Created through the page', $block->title);

        $data['blockid'] = $block->id;
        $data['title'] = 'Edited through the page';
        if ($type === 'text') {
            // Inline uploads are disabled, but imported text can already have embedded files.
            get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($USER->id)->id, 'component' => 'block_exaport',
                'filearea' => 'item_content_text', 'itemid' => $block->id, 'filepath' => '/', 'filename' => 'image.png',
            ], 'embedded image bytes');
            $block->content .= '<img src="@@PLUGINFILE@@/image.png">';
            $DB->set_field('block_exaportitemblock', 'content', $block->content, ['id' => $block->id]);
            $draft = (object)['content' => $block->content, 'contentformat' => $block->contentformat];
            $draft = file_prepare_standard_editor($draft, 'content', block_exaport_item_content_editor_options(),
                \context_user::instance($USER->id), 'block_exaport', 'item_content_text', $block->id);
            $data['content_editor'] = $draft->content_editor;
            $data['content_editor']['text'] .= '<p>Edited text</p>';
        }
        $this->submit($type, $data);
        $updated = block_exaport_get_item_content_blocks($itemid)[0];
        $this->assertSame((int)$block->id, (int)$updated->id);
        $this->assertSame('Edited through the page', $updated->title);
        $context = \context_user::instance($USER->id);
        if ($type === 'text') {
            $this->assertStringContainsString('@@PLUGINFILE@@/image.png', $updated->content);
            $this->assertStringContainsString('Edited text', $updated->content);
            $file = get_file_storage()->get_file($context->id, 'block_exaport', 'item_content_text',
                $block->id, '/', 'image.png');
            $this->assertSame('embedded image bytes', $file->get_content());
        } else if ($type === 'file') {
            $files = block_exaport_get_item_content_files((int)$USER->id, (int)$block->id);
            $this->assertCount(1, $files);
            $this->assertSame('uploaded file bytes', $files[0]->get_content());
        }

        $this->submit($type, array_replace($data, ['operation' => 'delete']));
        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
        foreach (['item_content_text', 'item_content_file'] as $area) {
            $this->assertSame([], get_file_storage()->get_area_files($context->id, 'block_exaport',
                $area, $block->id, 'id', false));
        }
        $this->assertTrue($DB->record_exists('block_exaportitem', ['id' => $itemid]));
    }

    public function test_standalone_cannot_change_type_via_hidden_form_data(): void {
        global $DB;

        [$itemid, $data] = $this->fixture('link');
        try {
            $this->submit('link', array_replace($data, ['contenttype' => 'text']));
            $this->fail('The page fixes the type independently of submitted form data');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $itemid]));
        }
    }

    public function test_standalone_save_rejects_invalid_sesskey(): void {
        global $DB;

        [$itemid, $data] = $this->fixture('link');
        try {
            $this->submit('link', $data, false);
            $this->fail('Saving requires a valid session key');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidsesskey', $exception->errorcode);
            $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $itemid]));
        }
    }

    public function test_dynamic_delete_without_a_block_cannot_create_an_orphan(): void {
        global $DB;

        [$itemid, $data] = $this->fixture('link');
        $data['operation'] = 'delete';
        $data = \block_exaport\form\item_content::mock_ajax_submit($data);
        try {
            \core_form\external\dynamic_form::execute(\block_exaport\form\item_content::class,
                http_build_query($data, '', '&'));
            $this->fail('Deleting requires an existing content block');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $itemid]));
        }
    }
}
