<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');

/**
 * Tests for structured item blocks.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_block_test extends \advanced_testcase {
    private \stdClass $user;
    private \stdClass $course;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        item_block::reset_cache();
        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        $this->setUser($this->user);
    }

    private function create_item(string $type = 'mixed'): \stdClass {
        global $DB;

        $itemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $this->user->id,
            'type' => $type,
            'categoryid' => 0,
            'name' => 'Structured item',
            'url' => '',
            'intro' => '',
            'attachment' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'courseid' => $this->course->id,
            'shareall' => 0,
            'externaccess' => 0,
            'externcomment' => 0,
            'sortorder' => 0,
            'isoez' => 0,
            'langid' => 0,
            'source' => 0,
            'sourceid' => 0,
            'iseditable' => 1,
            'parentid' => 0,
            'project_description' => '',
            'project_process' => '',
            'project_result' => '',
        ]);

        return $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
    }

    private function create_block(\stdClass $item, string $type, int $sortorder, string $title = '', string $content = '',
                                  string $url = ''): \stdClass {
        global $DB;

        $blockid = (int)$DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $item->id,
            'type' => $type,
            'sortorder' => $sortorder,
            'title' => $title,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'url' => $url,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return $DB->get_record('block_exaportitemblock', ['id' => $blockid], '*', MUST_EXIST);
    }

    private function add_block_file(\stdClass $block, string $filename, string $mimetype = 'text/plain'): void {
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($this->user->id)->id,
            'component' => 'block_exaport',
            'filearea' => 'itemblock_file',
            'itemid' => $block->id,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ], 'filecontent');
    }

    public function test_display_blocks_prefer_structured_blocks(): void {
        $item = $this->create_item();
        $textblock = $this->create_block($item, item_block::TYPE_TEXT, 1, 'Intro', '<p>Hello world</p>');
        $fileblock = $this->create_block($item, item_block::TYPE_FILE, 2, 'Attachment');
        $this->add_block_file($fileblock, 'document.txt');

        $blocks = item_block::get_display_blocks($item, 'portfolio/id/' . $this->user->id);

        $this->assertCount(2, $blocks);
        $this->assertSame($textblock->id, $blocks[0]->id);
        $this->assertSame(item_block::TYPE_TEXT, $blocks[0]->type);
        $this->assertStringContainsString('Hello world', $blocks[0]->contenthtml);
        $this->assertSame(item_block::TYPE_FILE, $blocks[1]->type);
        $this->assertSame('document.txt', $blocks[1]->filename);
    }

    public function test_table_exists_cache_can_be_reset_between_calls(): void {
        $item = $this->create_item();
        $this->assertFalse(item_block::item_uses_blocks($item->id));

        item_block::reset_cache();

        $block = $this->create_block($item, item_block::TYPE_TEXT, 1, 'Intro', '<p>Hello world</p>');
        $this->assertNotEmpty($block->id);
        $this->assertTrue(item_block::item_uses_blocks($item->id));
    }

    public function test_save_order_reorders_blocks(): void {
        $item = $this->create_item();
        $first = $this->create_block($item, item_block::TYPE_TEXT, 1, 'First', '<p>One</p>');
        $second = $this->create_block($item, item_block::TYPE_LINK, 2, 'Second', '<p>Two</p>', 'https://example.com');

        item_block::save_order($item->id, [$second->id, $first->id]);
        $reloaded = array_values(item_block::get_blocks($item->id));

        $this->assertSame($second->id, $reloaded[0]->id);
        $this->assertSame(1, (int)$reloaded[0]->sortorder);
        $this->assertSame($first->id, $reloaded[1]->id);
        $this->assertSame(2, (int)$reloaded[1]->sortorder);
    }

    public function test_save_order_rejects_invalid_block_sets(): void {
        $item = $this->create_item();
        $first = $this->create_block($item, item_block::TYPE_TEXT, 1, 'First', '<p>One</p>');
        $second = $this->create_block($item, item_block::TYPE_LINK, 2, 'Second', '<p>Two</p>', 'https://example.com');

        $invalidorders = [
            [],
            [$first->id],
            [$first->id, $second->id, 999999],
            [$first->id, $first->id],
        ];

        foreach ($invalidorders as $invalidorder) {
            try {
                item_block::save_order($item->id, $invalidorder);
                $this->fail('Expected invalidblockorder exception was not thrown.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('invalidblockorder', $exception->errorcode);
            }
        }
    }

    public function test_legacy_display_blocks_remain_available_without_structured_blocks(): void {
        global $DB;
        $item = $this->create_item('file');
        $item->url = 'https://example.com';
        $item->intro = 'Legacy description';
        $DB->update_record('block_exaportitem', $item);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($this->user->id)->id,
            'component' => 'block_exaport',
            'filearea' => 'item_file',
            'itemid' => $item->id,
            'filepath' => '/',
            'filename' => 'legacy.txt',
            'mimetype' => 'text/plain',
        ], 'legacy');

        $blocks = item_block::get_display_blocks($item, 'portfolio/id/' . $this->user->id);

        $this->assertNotEmpty($blocks);
        $this->assertContains(item_block::TYPE_FILE, array_map(function($block) {
            return $block->type;
        }, $blocks));
        $this->assertContains(item_block::TYPE_LINK, array_map(function($block) {
            return $block->type;
        }, $blocks));
    }
}
