<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../lib/item_content_helpers.php');

/**
 * Regression coverage for the individual shared-item copy controller.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_copytoself_test extends \advanced_testcase {
    public function test_controller_copies_structured_content_to_recipient_without_mutating_source(): void {
        global $CFG, $DB, $USER, $PAGE, $COURSE, $OUTPUT, $SESSION;

        $this->resetAfterTest(true);
        // Exercise the standalone Exaport path without optional competence interaction.
        set_config('block_exaport_enable_interaction_competences', 0);
        set_config('block_exaport_teachercanseeartifactsofstudents', 0);
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $categoryid = $DB->insert_record('block_exaportcate', (object)[
            'userid' => $owner->id, 'name' => 'Private source category',
        ]);
        $sourceid = $DB->insert_record('block_exaportitem', (object)[
            'userid' => $owner->id, 'name' => 'Individual copy', 'type' => 'link',
            'intro' => '<p>Parent description</p>', 'url' => 'https://legacy.example/residual',
            'attachment' => 'legacy.txt', 'categoryid' => $categoryid,
            'shareall' => 1, 'externaccess' => 1, 'externcomment' => 1,
            'timecreated' => 100, 'timemodified' => 101, 'courseid' => SITEID,
        ]);
        item_category_helper::sync_item_categories($sourceid, [$categoryid]);
        $DB->insert_record('block_exaportitemshar', (object)[
            'itemid' => $sourceid, 'userid' => $recipient->id, 'original' => $owner->id,
            'courseid' => SITEID, 'notify' => 0,
        ]);
        // Insert out of display order, with equal sortorders to exercise the ID tie-break.
        $fileblock = \block_exaport_create_file_content_block($sourceid, 'Files', ['sortorder' => 20]);
        \block_exaport_create_content_block($sourceid, 'link', 'First link', 'https://example.test/first',
            ['sortorder' => 10]);
        $textblock = \block_exaport_create_content_block($sourceid, 'text', 'Text', '', [
            'sortorder' => 10, 'content' => '<p>Body <img src="@@PLUGINFILE@@/embedded.txt"></p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $otherfileblock = \block_exaport_create_file_content_block($sourceid, 'Other files', ['sortorder' => 30]);
        \block_exaport_create_content_block($sourceid, 'link', 'Last link', 'https://example.test/last',
            ['sortorder' => 40]);

        $fs = get_file_storage();
        $ownercontext = \context_user::instance($owner->id)->id;
        $recipientcontext = \context_user::instance($recipient->id)->id;
        $sourcefiles = [];
        foreach ([
            ['item_content_file', $fileblock->id, '/nested/', 'one.txt', 'one'],
            ['item_content_file', $fileblock->id, '/', 'two.txt', 'two'],
            ['item_content_file', $otherfileblock->id, '/', 'three.txt', 'three'],
            ['item_content_text', $textblock->id, '/', 'embedded.txt', 'embedded'],
            ['item_file', $sourceid, '/', 'legacy.txt', 'residual'],
        ] as [$area, $blockid, $path, $name, $bytes]) {
            $sourcefiles[] = $fs->create_file_from_string([
                'contextid' => $ownercontext, 'component' => 'block_exaport', 'filearea' => $area,
                'itemid' => $blockid, 'filepath' => $path, 'filename' => $name, 'userid' => $owner->id,
            ], $bytes);
        }
        $sourcebefore = $DB->get_record('block_exaportitem', ['id' => $sourceid], '*', MUST_EXIST);
        $blocks = \block_exaport_get_item_content_blocks($sourceid);
        $sharesbefore = $DB->get_records('block_exaportitemshar', ['itemid' => $sourceid]);
        $categoriesbefore = $DB->get_records('block_exaportitemcate', ['itemid' => $sourceid]);
        $this->setUser($recipient);
        $oldget = $_GET;
        $oldpost = $_POST;
        $_GET = ['courseid' => SITEID, 'id' => $sourceid, 'action' => 'copytoself', 'sesskey' => sesskey()];
        $_POST = [];
        try {
            // Execute the real caller, including access checks, destination construction,
            // content copying and transaction commit. Moodle's CLI redirect throws after commit.
            include(__DIR__ . '/../item.php');
            $this->fail('The controller must reach its redirect');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode);
        } finally {
            $_GET = $oldget;
            $_POST = $oldpost;
        }

        $destination = $DB->get_record('block_exaportitem', ['userid' => $recipient->id], '*', MUST_EXIST);
        $this->assertNotSame((int)$sourceid, (int)$destination->id);
        $this->assertEquals($sourcebefore, $sourceitem, 'The trusted source object must keep its ID and owner');
        $this->assertEquals($sourcebefore, $DB->get_record('block_exaportitem', ['id' => $sourceid], '*', MUST_EXIST));
        $this->assertSame($sourcebefore->intro, $destination->intro);
        $this->assertSame('', $destination->url);
        $this->assertSame('', $destination->attachment);
        foreach (['categoryid', 'shareall', 'externaccess', 'externcomment'] as $field) {
            $this->assertSame(0, (int)$destination->$field);
        }
        $this->assertFalse($DB->record_exists('block_exaportitemcate', ['itemid' => $destination->id]));
        $this->assertFalse($DB->record_exists('block_exaportitemshar', ['itemid' => $destination->id]));
        $copies = \block_exaport_get_item_content_blocks($destination->id);
        $this->assertCount(count($blocks), $copies);
        $blockmap = [];
        foreach ($blocks as $index => $block) {
            $copied = $copies[$index];
            $this->assertNotSame((int)$block->id, (int)$copied->id);
            $this->assertSame((int)$destination->id, (int)$copied->itemid);
            $blockmap[$block->id] = $copied->id;
            foreach (['type', 'sortorder', 'title', 'content', 'contentformat', 'url', 'timecreated'] as $field) {
                $this->assertSame($block->$field, $copied->$field);
            }
        }
        foreach ($sourcefiles as $file) {
            $original = $fs->get_file_by_id($file->get_id());
            $this->assertNotFalse($original);
            $this->assertSame($file->get_content(), $original->get_content());
            if ($file->get_filearea() === 'item_file') {
                continue;
            }
            $copied = $fs->get_file($recipientcontext, 'block_exaport', $file->get_filearea(),
                $blockmap[$file->get_itemid()], $file->get_filepath(), $file->get_filename());
            $this->assertNotFalse($copied);
            $this->assertNotSame($file->get_id(), $copied->get_id());
            $this->assertSame($file->get_content(), $copied->get_content());
            $this->assertSame($recipientcontext, (int)$copied->get_contextid());
            $this->assertSame((int)$recipient->id, (int)$copied->get_userid());
            $this->assertSame((int)$blockmap[$file->get_itemid()], (int)$copied->get_itemid());
            $this->assertFalse($fs->get_file($ownercontext, 'block_exaport', $file->get_filearea(),
                $copied->get_itemid(), $file->get_filepath(), $file->get_filename()));
        }
        $this->assertEmpty($fs->get_area_files($recipientcontext, 'block_exaport', 'item_file',
            $destination->id, 'id', false));
        $this->assertEquals($blocks, \block_exaport_get_item_content_blocks($sourceid));
        $this->assertEquals($sharesbefore, $DB->get_records('block_exaportitemshar', ['itemid' => $sourceid]));
        $this->assertEquals($categoriesbefore, $DB->get_records('block_exaportitemcate', ['itemid' => $sourceid]));
        $this->assertStringContainsString('userid=' . $owner->id, $returnurl);
    }

    public function test_category_copy_keeps_its_separate_source_and_destination(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $categoryid = $DB->insert_record('block_exaportcate', (object)[
            'userid' => $owner->id, 'name' => 'Shared category', 'description' => '',
        ]);
        $sourceid = $DB->insert_record('block_exaportitem', (object)[
            'userid' => $owner->id, 'name' => 'Category copy', 'type' => 'link',
            'intro' => 'Description', 'url' => 'https://legacy.example/residual', 'attachment' => 'legacy.txt',
        ]);
        item_category_helper::sync_item_categories($sourceid, [$categoryid]);
        \block_exaport_create_content_block($sourceid, 'link', 'Link', 'https://example.test/category');
        $fileblock = \block_exaport_create_file_content_block($sourceid, 'File');
        $fs = get_file_storage();
        $file = $fs->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id, 'component' => 'block_exaport',
            'filearea' => 'item_content_file', 'itemid' => $fileblock->id, 'filepath' => '/',
            'filename' => 'category.txt', 'userid' => $owner->id,
        ], 'category bytes');
        $sourcebefore = $DB->get_record('block_exaportitem', ['id' => $sourceid], '*', MUST_EXIST);
        $blocks = \block_exaport_get_item_content_blocks($sourceid);
        $this->setUser($recipient);

        $categorycopy = copy_category_to_myself($categoryid);

        $destination = $DB->get_record('block_exaportitem', ['userid' => $recipient->id], '*', MUST_EXIST);
        $this->assertNotSame((int)$categoryid, (int)$categorycopy->id);
        $this->assertSame((int)$recipient->id, (int)$categorycopy->userid);
        $this->assertTrue($DB->record_exists('block_exaportitemcate', [
            'itemid' => $destination->id, 'cateid' => $categorycopy->id,
        ]));
        $this->assertSame('', $destination->url);
        $this->assertSame('', $destination->attachment);
        $copies = \block_exaport_get_item_content_blocks($destination->id);
        $this->assertCount(2, $copies);
        $this->assertSame($blocks[0]->url, $copies[0]->url);
        $this->assertNotSame((int)$fileblock->id, (int)$copies[1]->id);
        $copiedfile = $fs->get_file(\context_user::instance($recipient->id)->id,
            'block_exaport', 'item_content_file', $copies[1]->id, '/', 'category.txt');
        $this->assertNotFalse($copiedfile);
        $this->assertSame($file->get_content(), $copiedfile->get_content());
        $this->assertSame((int)$recipient->id, (int)$copiedfile->get_userid());
        $this->assertEquals($sourcebefore, $DB->get_record('block_exaportitem', ['id' => $sourceid], '*', MUST_EXIST));
        $this->assertEquals($blocks, \block_exaport_get_item_content_blocks($sourceid));
    }
}
