<?php
// This file is part of Moodle - http://moodle.org/.

namespace block_exaport\tests;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/lib.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

/**
 * Tests for the central item deletion service.
 *
 * @package block_exaport
 * @covers ::block_exaport_delete_item_content
 * @covers ::block_exaport_delete_item
 */
final class item_deletion_test extends \advanced_testcase {
    use exaport_test_helpers_trait;

    /**
     * Create a minimal item owned by a user.
     *
     * @param \stdClass $user Owner.
     * @return \stdClass
     */
    private function create_item(\stdClass $user): \stdClass {
        global $DB;
        $item = (object)[
            'userid' => $user->id,
            'categoryid' => 0,
            'name' => 'Deletion test',
            'type' => 'note',
            'url' => '',
            'intro' => '',
            'attachment' => '',
            'timemodified' => time(),
            'courseid' => SITEID,
        ];
        $item->id = $DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    /**
     * Create a stored file.
     *
     * @param int $contextid Context id.
     * @param string $area File area.
     * @param int $itemid File item id.
     * @param string $name File name.
     */
    private function create_file(int $contextid, string $area, int $itemid, string $name): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'block_exaport',
            'filearea' => $area,
            'itemid' => $itemid,
            'filepath' => '/nested/',
            'filename' => $name,
        ], 'content');
    }

    public function test_delete_structured_content_uses_owner_context_and_preserves_other_item(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $item = $this->create_item($owner);
        $otheritem = $this->create_item($owner);
        $blockid = $DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $item->id, 'type' => 'text', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $otherblockid = $DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $otheritem->id, 'type' => 'file', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $context = \context_user::instance($owner->id);
        $this->create_file($context->id, 'item_content_text', $blockid, 'image.png');
        $this->create_file($context->id, 'item_content_file', $blockid, 'document.pdf');
        $this->create_file($context->id, 'item_content_file', $otherblockid, 'keep.pdf');

        block_exaport_delete_item_content($item);

        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['id' => $blockid]));
        $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $otherblockid]));
        $this->assertEmpty(get_file_storage()->get_area_files(
            $context->id, 'block_exaport', 'item_content_text', $blockid, 'id', false));
        $this->assertCount(1, get_file_storage()->get_area_files(
            $context->id, 'block_exaport', 'item_content_file', $otherblockid, 'id', false));
    }

    public function test_complete_deletion_removes_files_comments_and_relationships(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner);
        $context = \context_user::instance($owner->id);
        foreach (['item_file', 'item_content', 'item_iconfile', 'item_content_project_description',
                     'item_content_project_process', 'item_content_project_result'] as $area) {
            $this->create_file($context->id, $area, $item->id, $area . '.txt');
        }
        $blockid = $DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $item->id, 'type' => 'file', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->create_file($context->id, 'item_content_file', $blockid, 'block.txt');
        $commentid = $DB->insert_record('block_exaportitemcomm', (object)[
            'itemid' => $item->id, 'userid' => $owner->id, 'entry' => 'Comment', 'timemodified' => time(),
        ]);
        $this->create_file(\context_system::instance()->id, 'item_comment_file', $commentid, 'comment.txt');
        $DB->insert_record('block_exaportitemshar', (object)['itemid' => $item->id, 'userid' => $owner->id]);
        $DB->insert_record('block_exaportitemgroupshar', (object)['itemid' => $item->id, 'groupid' => 1]);
        $categoryid = $this->create_category($owner);
        $DB->insert_record('block_exaportitemcate', (object)['itemid' => $item->id, 'cateid' => $categoryid]);
        $viewid = $this->create_view($owner);
        $DB->insert_record('block_exaportviewblock', (object)['viewid' => $viewid, 'itemid' => $item->id]);

        block_exaport_delete_item($item);

        $this->assertFalse($DB->record_exists('block_exaportitem', ['id' => $item->id]));
        $this->assertFalse($DB->record_exists('block_exaportitemblock', ['itemid' => $item->id]));
        $this->assertFalse($DB->record_exists('block_exaportitemcomm', ['itemid' => $item->id]));
        $this->assertFalse($DB->record_exists('block_exaportitemshar', ['itemid' => $item->id]));
        $this->assertFalse($DB->record_exists('block_exaportitemgroupshar', ['itemid' => $item->id]));
        $this->assertFalse($DB->record_exists('block_exaportitemcate', ['itemid' => $item->id]));
        $this->assertFalse($DB->record_exists('block_exaportviewblock', ['itemid' => $item->id]));
        $this->assertEmpty(get_file_storage()->get_area_files(
            \context_system::instance()->id, 'block_exaport', 'item_comment_file', $commentid, 'id', false));
    }

    public function test_legacy_file_cleanup_preserves_structured_content(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner);
        $blockid = $DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $item->id, 'type' => 'file', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $context = \context_user::instance($owner->id);
        $this->create_file($context->id, 'item_file', $item->id, 'legacy.txt');
        $this->create_file($context->id, 'item_content_file', $blockid, 'keep.txt');

        block_exaport_delete_legacy_item_file($item);

        $this->assertTrue($DB->record_exists('block_exaportitemblock', ['id' => $blockid]));
        $this->assertCount(1, get_file_storage()->get_area_files(
            $context->id, 'block_exaport', 'item_content_file', $blockid, 'id', false));
    }

    /**
     * User cleanup removes attachments from comments on retained items.
     */
    public function test_user_cleanup_removes_comment_attachment_from_other_users_item(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $commenter = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner);
        $commentid = $DB->insert_record('block_exaportitemcomm', (object)[
            'itemid' => $item->id,
            'userid' => $commenter->id,
            'entry' => 'Delete my comment',
            'timemodified' => time(),
        ]);
        $systemcontext = \context_system::instance();
        $this->create_file($systemcontext->id, 'item_comment_file', $commentid, 'personal.txt');

        \block_exaport\api::delete_user_data($commenter->id);

        $this->assertTrue($DB->record_exists('block_exaportitem', ['id' => $item->id]));
        $this->assertFalse($DB->record_exists('block_exaportitemcomm', ['id' => $commentid]));
        $this->assertEmpty(get_file_storage()->get_area_files(
            $systemcontext->id, 'block_exaport', 'item_comment_file', $commentid, 'id', false));
    }
}
