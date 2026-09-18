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
require_once($CFG->dirroot . '/blocks/exaport/lib/externlib.php');

/**
 * Tests structured item content loading and read-only output.
 *
 * @package    block_exaport
 * @copyright 2026 gtn gmbh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_content_blocks_test extends \advanced_testcase {

    public function test_blocks_are_scoped_ordered_and_unsupported_types_are_ignored(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $otheritemid = $this->insert_item($owner->id, $course->id);

        $firstid = $this->insert_block($itemid, 'text', 20, 'Second');
        $secondid = $this->insert_block($itemid, 'text', 20, 'Third');
        $thirdid = $this->insert_block($itemid, 'text', 10, 'First');
        $this->insert_block($itemid, 'link', 0, 'Unsupported');
        $this->insert_block($otheritemid, 'text', 0, 'Other item');

        $blocks = block_exaport_get_item_content_text_blocks($itemid);

        $this->assertSame([$thirdid, $firstid, $secondid], array_keys($blocks));
        $this->assertSame(['First', 'Second', 'Third'], array_map(
            static function(\stdClass $block): string {
                return $block->title;
            },
            $blocks
        ));
        $this->assertSame(1, $DB->count_records('block_exaportitemblock', ['itemid' => $otheritemid]));
    }

    public function test_supported_loader_and_renderer_include_links_and_files(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $linkid = $this->insert_block($itemid, 'link', 0, 'Moodle', '', 'https://moodle.org/');
        $fileid = $this->insert_block($itemid, 'file', 1, 'Images', '');
        $this->insert_block($itemid, 'unsupported', 2, 'Unsupported');

        $context = \context_user::instance($owner->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'block_exaport',
            'filearea' => 'item_content_file',
            'itemid' => $fileid,
            'filepath' => '/',
            'filename' => 'picture.png',
        ], 'not-a-real-png');

        $blocks = block_exaport_get_item_content_blocks($itemid);
        $this->assertSame([$linkid, $fileid], array_keys($blocks));

        $renderer = $this->getMockBuilder(\renderer_base::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $renderer->method('pix_icon')->willReturn('icon');
        $addurls = [
            'text' => new \moodle_url('/blocks/exaport/item_content_text.php'),
            'link' => new \moodle_url('/blocks/exaport/item_content_link.php'),
            'file' => new \moodle_url('/blocks/exaport/item_content_file.php'),
        ];
        $data = (new \block_exaport\output\item_content_blocks($blocks, $addurls, $owner->id))
            ->export_for_template($renderer);

        $this->assertSame('https://moodle.org/', $data['blocks'][0]['linkurl']);
        $this->assertTrue($data['blocks'][1]['hasfiles']);
        $this->assertSame('picture.png', $data['blocks'][1]['files'][0]['name']);
        $this->assertStringContainsString(
            $CFG->wwwroot . '/pluginfile.php/' . $context->id . '/block_exaport/item_content_file/' .
                'itemid/' . $itemid . '/blockid/' . $fileid . '/picture.png',
            $data['blocks'][1]['files'][0]['url']
        );
        $this->assertCount(3, $data['addactions']);
    }

    public function test_text_is_formatted_with_owner_context_and_shared_output_has_no_add_control(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $blockid = $this->insert_block(
            $itemid,
            'text',
            0,
            'Owner title',
            '<script>alert(1)</script><p>Safe content</p>@@PLUGINFILE@@/owner.txt'
        );
        $this->setUser($viewer);
        global $DB;
        $block = $DB->get_record('block_exaportitemblock', ['id' => $blockid], '*', MUST_EXIST);

        $renderer = $this->getMockBuilder(\renderer_base::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $renderer->method('pix_icon')->willReturn('icon');

        $data = (new \block_exaport\output\item_content_blocks(
            [
                $blockid => $block,
            ],
            null,
            $owner->id,
            false
        ))->export_for_template($renderer);

        $this->assertFalse($data['showaddbutton']);
        $this->assertCount(1, $data['blocks']);
        $this->assertStringContainsString(
            '/pluginfile.php/' . \context_user::instance($owner->id)->id . '/block_exaport/item_content_text/' .
                $blockid . '/owner.txt',
            $data['blocks'][0]['content']
        );
        $this->assertStringContainsString('Safe content', $data['blocks'][0]['content']);
        $this->assertStringNotContainsString('<script', $data['blocks'][0]['content']);
        $this->assertStringContainsString('Owner title', $data['blocks'][0]['title']);
        $this->assertNotSame($owner->id, $viewer->id);

        global $OUTPUT;
        $html = $OUTPUT->render_from_template('block_exaport/item_content_blocks', $data);
        $this->assertStringNotContainsString('exaport-item-content-add-button', $html);

        $emptydata = (new \block_exaport\output\item_content_blocks([], null, $owner->id, false))
            ->export_for_template($renderer);
        $this->assertFalse($emptydata['hasblocks']);
        $this->assertSame([], $emptydata['blocks']);

        $embeddeddata = (new \block_exaport\output\item_content_blocks([], null, $owner->id, true, false))
            ->export_for_template($renderer);
        $this->assertFalse($embeddeddata['showheading']);
        $embeddedhtml = $OUTPUT->render_from_template('block_exaport/item_content_blocks', $embeddeddata);
        $this->assertStringNotContainsString('exaport-item-content-heading', $embeddedhtml);
        $this->assertStringContainsString('aria-label="Content"', $embeddedhtml);
    }

    /**
     * @param int $itemid
     * @param string $type
     * @param int $sortorder
     * @param string $title
     * @param string $content
     * @param string $url
     * @return int
     */
    private function insert_block(
        int $itemid,
        string $type,
        int $sortorder,
        string $title,
        string $content = 'Content',
        string $url = ''
    ): int {
        global $DB;

        return (int)$DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $itemid,
            'type' => $type,
            'sortorder' => $sortorder,
            'title' => $title,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'url' => $url,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    private function insert_item(int $userid, int $courseid): int {
        global $DB;

        return (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $userid,
            'type' => 'note',
            'categoryid' => 0,
            'name' => 'Test item',
            'url' => '',
            'intro' => '',
            'attachment' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'courseid' => $courseid,
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
        ]);
    }
}
