<?php
// This file is part of Moodle - http://moodle.org/

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../lib/item_content_helpers.php');
require_once(__DIR__ . '/../lib/item_content_form.php');

/** Tests the legacy file limits at the structured-content form and save boundaries. */
final class item_content_file_limits_test extends \advanced_testcase {

    /** Create an editable item and configure byte-sized limits. */
    private function item(): \stdClass {
        global $CFG, $DB, $PAGE;

        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $CFG->block_exaport_app_alloweditdelete = 1;
        $CFG->block_exaport_userquota = 10;
        $CFG->block_exaport_max_uploadfile_size = 10;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/blocks/exaport/item.php');
        $item = (object)['userid' => $user->id, 'type' => 'note', 'categoryid' => 0,
            'name' => 'File limits', 'url' => '', 'intro' => '', 'attachment' => '',
            'timecreated' => 1, 'timemodified' => 2, 'courseid' => SITEID, 'shareall' => 0,
            'externaccess' => 0, 'externcomment' => 0, 'isoez' => 0];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    /** Create a real draft or permanent file with a known size. */
    private function file(\stdClass $item, string $area, int $areaid, int $bytes): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($item->userid)->id,
            'component' => $area === 'draft' ? 'user' : 'block_exaport',
            'filearea' => $area, 'itemid' => $areaid, 'filepath' => '/',
            'filename' => $area . '.txt', 'userid' => $item->userid,
        ], str_repeat('x', $bytes));
    }

    /** Build file submission data shared by both form types. */
    private function data(\stdClass $item, int $draftid, int $blockid = 0): array {
        return ['itemid' => $item->id, 'courseid' => SITEID, 'blockid' => $blockid,
            'contenttype' => 'file', 'operation' => 'save', 'title' => 'Changed title',
            'files_filemanager' => $draftid];
    }

    /** Assert both forms return the original legacy exception message. */
    private function assert_form_error(array $data, ?\moodle_exception $error): void {
        $forms = [
            new \block_exaport_item_content_file_form(null, [
                'fileoptions' => block_exaport_item_content_file_options(), 'blockid' => $data['blockid'],
            ]),
            new \block_exaport\form\item_content(null, null, 'post', '', [], true, $data),
        ];
        foreach ($forms as $form) {
            $errors = $form->validation($data, []);
            if ($error) {
                $this->assertSame($error->getMessage(), $errors['files_filemanager']);
            } else {
                $this->assertArrayNotHasKey('files_filemanager', $errors);
            }
        }
    }

    /** Exercise the dynamic save guard independently of form validation. */
    private function save(array $data): array {
        $form = $this->getMockBuilder(\block_exaport\form\item_content::class)
            ->disableOriginalConstructor()->onlyMethods(['get_data'])->getMock();
        $form->method('get_data')->willReturn((object)$data);
        $post = $_POST;
        $_POST['sesskey'] = sesskey();
        try {
            return $form->process_dynamic_submission();
        } finally {
            $_POST = $post;
        }
    }

    /** Quota and individual-size failures must both be rejected before writes. */
    public function test_over_limit_additions_leave_no_blocks_or_permanent_files(): void {
        global $CFG, $DB;

        $item = $this->item();
        $draftid = file_get_unused_draft_itemid();
        $this->file($item, 'draft', $draftid, 11);
        foreach (['userquotalimit', 'maxbytes'] as $code) {
            $CFG->block_exaport_userquota = $code === 'maxbytes' ? 100 : 10;
            $error = block_exaport_validate_item_content_files($draftid, $item->id, SITEID);
            $this->assertInstanceOf(\moodle_exception::class, $error);
            $this->assertSame($code, $error->errorcode);
            $data = $this->data($item, $draftid);
            $this->assert_form_error($data, $error);
            try {
                $this->save($data);
                $this->fail('The pre-write guard must reject an over-limit draft');
            } catch (\moodle_exception $exception) {
                $this->assertSame($code, $exception->errorcode);
            }
            $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $item->id]));
            $this->assertFalse($DB->record_exists('files', [
                'contextid' => \context_user::instance($item->userid)->id, 'component' => 'block_exaport',
            ]));
        }
    }

    /** Equal-to-limit drafts succeed, including edits which would otherwise double-count. */
    public function test_boundary_add_and_edit_save_without_double_counting(): void {
        global $DB;

        $item = $this->item();
        $draftid = file_get_unused_draft_itemid();
        $this->file($item, 'draft', $draftid, 10);
        $data = $this->data($item, $draftid);
        $this->assert_form_error($data, null);
        $this->assertArrayHasKey('content', $this->save($data));
        $block = $DB->get_record('block_exaportitemblock', ['itemid' => $item->id], '*', MUST_EXIST);
        $this->assertCount(1, block_exaport_get_item_content_files($item->userid, $block->id));

        $data['blockid'] = $block->id;
        $data['title'] = 'Successful edit';
        $this->assertNull(block_exaport_validate_item_content_files($draftid, $item->id, SITEID, $block->id));
        $this->assert_form_error($data, null);
        $this->save($data);
        $this->assertSame('Successful edit', $DB->get_field('block_exaportitemblock', 'title', ['id' => $block->id]));
        $this->assertSame(1, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
    }

    /** Failed edits preserve the original block metadata and file content. */
    public function test_failed_edits_leave_original_metadata_and_files(): void {
        global $CFG, $DB;

        $item = $this->item();
        $block = block_exaport_create_file_content_block($item->id, 'Original');
        $file = $this->file($item, 'item_content_file', $block->id, 5);
        $before = $DB->get_record('block_exaportitemblock', ['id' => $block->id]);
        $draftid = file_get_unused_draft_itemid();
        $this->file($item, 'draft', $draftid, 11);
        foreach (['userquotalimit', 'maxbytes'] as $code) {
            $CFG->block_exaport_userquota = $code === 'maxbytes' ? 100 : 10;
            $error = block_exaport_validate_item_content_files($draftid, $item->id, SITEID, $block->id);
            $this->assertSame($code, $error->errorcode);
            $data = $this->data($item, $draftid, $block->id);
            $this->assert_form_error($data, $error);
            try {
                $this->save($data);
                $this->fail('The pre-write guard must reject an over-limit edit');
            } catch (\moodle_exception $exception) {
                $this->assertSame($code, $exception->errorcode);
            }
            $this->assertEquals($before, $DB->get_record('block_exaportitemblock', ['id' => $block->id]));
            $files = block_exaport_get_item_content_files($item->userid, $block->id);
            $this->assertCount(1, $files);
            $this->assertSame($file->get_id(), $files[0]->get_id());
            $this->assertSame('xxxxx', $files[0]->get_content());
        }
    }

    /** Only this block's file area is replaceable, not siblings, editors, or legacy files. */
    public function test_edit_discount_excludes_other_permanent_file_areas(): void {
        global $CFG;

        $item = $this->item();
        $block = block_exaport_create_file_content_block($item->id, 'Edited');
        $sibling = block_exaport_create_file_content_block($item->id, 'Sibling');
        $this->file($item, 'item_content_file', $block->id, 5);
        $this->file($item, 'item_content_file', $sibling->id, 2);
        $this->file($item, 'item_content_text', $block->id, 2);
        $this->file($item, 'item_file', $item->id, 2);
        $draftid = file_get_unused_draft_itemid();
        $this->file($item, 'draft', $draftid, 5);
        $error = block_exaport_validate_item_content_files($draftid, $item->id, SITEID, $block->id);
        $this->assertSame('userquotalimit', $error->errorcode);
        $this->assert_form_error($this->data($item, $draftid, $block->id), $error);
        $CFG->block_exaport_userquota = 11;
        $this->assertNull(block_exaport_validate_item_content_files($draftid, $item->id, SITEID, $block->id));
    }

    /** A request cannot use another item's block to obtain a quota discount. */
    public function test_foreign_block_cannot_be_discounted(): void {
        global $DB;

        $item = $this->item();
        $other = clone $item;
        unset($other->id);
        $other->id = (int)$DB->insert_record('block_exaportitem', $other);
        $block = block_exaport_create_file_content_block($other->id, 'Other item');
        $this->expectException(\invalid_parameter_exception::class);
        block_exaport_validate_item_content_files(0, $item->id, SITEID, $block->id);
    }

    /** Empty edits can remove the final file, but additions still require a file. */
    public function test_empty_edit_can_remove_the_final_file(): void {
        global $DB;

        $item = $this->item();
        $block = block_exaport_create_file_content_block($item->id, 'Remove me');
        $this->file($item, 'item_content_file', $block->id, 10);
        $draftid = file_get_unused_draft_itemid();
        $forms = [
            new \block_exaport_item_content_file_form(null, [
                'fileoptions' => block_exaport_item_content_file_options(),
            ]),
            new \block_exaport\form\item_content(null, null, 'post', '', [], true,
                $this->data($item, $draftid)),
        ];
        foreach ($forms as $form) {
            $errors = $form->validation($this->data($item, $draftid), []);
            $this->assertSame(get_string('required'), $errors['files_filemanager']);
        }
        $data = $this->data($item, $draftid, $block->id);
        $this->assert_form_error($data, null);
        $this->save($data);
        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['id' => $block->id]));
        $this->assertSame([], block_exaport_get_item_content_files($item->userid, $block->id));
    }

    /** Preserve zero quota and nonpositive individual-size limit semantics. */
    public function test_zero_quota_is_not_unlimited_and_nonpositive_maximum_is(): void {
        global $CFG;

        $item = $this->item();
        $draftid = file_get_unused_draft_itemid();
        $this->file($item, 'draft', $draftid, 11);
        $CFG->block_exaport_userquota = 0;
        $this->assertSame('userquotalimit',
            block_exaport_validate_item_content_files($draftid, $item->id, SITEID)->errorcode);
        $CFG->block_exaport_userquota = 11;
        foreach ([0, -1] as $maximum) {
            $CFG->block_exaport_max_uploadfile_size = $maximum;
            $this->assertNull(block_exaport_validate_item_content_files($draftid, $item->id, SITEID));
        }
    }

    /** Delete, text, and link validation must not gain file-upload restrictions. */
    public function test_dynamic_validation_skips_delete_text_and_link(): void {
        $item = $this->item();
        foreach (['delete', 'text', 'link'] as $mode) {
            $data = $this->data($item, 0);
            $data['operation'] = $mode === 'delete' ? 'delete' : 'save';
            $data['contenttype'] = $mode === 'delete' ? 'file' : $mode;
            $form = new \block_exaport\form\item_content(null, null, 'post', '', [], true, $data);
            $this->assertSame([], $form->validation($data, []));
        }
    }

    /** The standalone controller cannot be included without executing a complete page. */
    public function test_standalone_guard_precedes_all_permanent_writes(): void {
        $source = file_get_contents(__DIR__ . '/../item_content_file.php');
        $save = substr($source, strpos($source, '} else if ($fromform = $form->get_data())'));
        $guard = strpos($save, 'block_exaport_validate_item_content_files(');
        $this->assertNotFalse($guard);
        foreach (['start_delegated_transaction(', 'block_exaport_create_file_content_block(',
            'update_record(', 'file_postupdate_standard_filemanager('] as $write) {
            $this->assertLessThan(strpos($save, $write), $guard);
        }
        $this->assertStringContainsString('throw $error;', $save);
    }
}
