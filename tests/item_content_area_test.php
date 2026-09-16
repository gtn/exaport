<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests structured item content rendering data.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_content_area_test extends \advanced_testcase {
    private \stdClass $owner;
    private \stdClass $course;
    private \stdClass $item;

    protected function setUp(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        $this->setUser($this->owner);

        $itemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $this->owner->id,
            'type' => 'note',
            'categoryid' => 0,
            'name' => 'Structured item',
            'url' => '',
            'intro' => 'Legacy intro',
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
        ]);

        $this->item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
    }

    /**
     * Insert an item block.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function create_block(array $overrides = []): \stdClass {
        global $DB;

        $record = (object)array_merge([
            'itemid' => $this->item->id,
            'type' => 'text',
            'sortorder' => 0,
            'title' => '',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'url' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $overrides);

        $record->id = (int)$DB->insert_record('block_exaportitemblock', $record);
        return $record;
    }

    /**
     * Add a stored file to a block.
     *
     * @param int $blockid
     * @param string $filename
     * @param string $content
     * @param string $mimetype
     * @return void
     */
    private function add_block_file(int $blockid, string $filename, string $content, string $mimetype,
                                    string $filepath = '/'): void {
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($this->owner->id)->id,
            'component' => 'block_exaport',
            'filearea' => \block_exaport\item_content_helper::FILEAREA,
            'itemid' => $blockid,
            'filepath' => $filepath,
            'filename' => $filename,
            'mimetype' => $mimetype,
        ], $content);
    }

    private function export_area(): array {
        $renderer = $this->getMockBuilder(\renderer_base::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $area = new \block_exaport\output\item_content_area($this->item, 'portfolio/id/' . $this->owner->id);
        return $area->export_for_template($renderer);
    }

    public function test_empty_state_is_exported_when_item_has_no_blocks(): void {
        $data = $this->export_area();

        $this->assertFalse($data['hasblocks']);
        $this->assertSame([], $data['blocks']);
        $this->assertSame('No content blocks yet', $data['emptytitle']);
    }

    public function test_blocks_are_loaded_in_sort_order(): void {
        $this->create_block(['type' => 'text', 'sortorder' => 20, 'title' => 'Second']);
        $this->create_block(['type' => 'link', 'sortorder' => 10, 'title' => 'First', 'url' => 'https://example.com']);
        $this->create_block(['type' => 'media', 'sortorder' => 30, 'title' => 'Third']);

        $data = $this->export_area();

        $this->assertTrue($data['hasblocks']);
        $this->assertSame('First', $data['blocks'][0]['heading']);
        $this->assertSame('Second', $data['blocks'][1]['heading']);
        $this->assertSame('Third', $data['blocks'][2]['heading']);
    }

    public function test_blocks_are_scoped_to_the_current_item(): void {
        global $DB;

        $this->create_block(['type' => 'text', 'sortorder' => 10, 'title' => 'Current item block']);
        $otheritemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $this->owner->id,
            'type' => 'note',
            'categoryid' => 0,
            'name' => 'Other item',
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
        ]);
        $DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $otheritemid,
            'type' => 'text',
            'sortorder' => 0,
            'title' => 'Other item block',
            'content' => 'Should stay hidden',
            'contentformat' => FORMAT_HTML,
            'url' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $data = $this->export_area();

        $this->assertCount(1, $data['blocks']);
        $this->assertSame('Current item block', $data['blocks'][0]['heading']);
    }

    public function test_unknown_block_types_export_fallback_state(): void {
        $this->create_block(['type' => 'accordion', 'title' => 'Future block']);

        $data = $this->export_area();

        $this->assertTrue($data['blocks'][0]['isunknown']);
        $this->assertSame('This content block type is not supported yet.', $data['blocks'][0]['unsupportedtext']);
    }

    public function test_file_blocks_export_pluginfile_urls_without_filesystem_paths(): void {
        $block = $this->create_block(['type' => 'file', 'title' => 'Download']);
        $this->add_block_file($block->id, 'document.pdf', 'pdf-content', 'application/pdf', '/nested/');

        $data = $this->export_area();

        $this->assertTrue($data['blocks'][0]['hasfiles']);
        $this->assertStringContainsString('/pluginfile.php/', $data['blocks'][0]['files'][0]['url']);
        $this->assertStringContainsString('/itemblock_file/portfolio/id/' . $this->owner->id .
            '/itemid/' . $this->item->id . '/blockid/' . $block->id . '/nested/document.pdf',
            $data['blocks'][0]['files'][0]['url']);
        $this->assertStringNotContainsString('/filedir/', $data['blocks'][0]['files'][0]['url']);
    }

    public function test_block_file_argument_parser_supports_nested_filepaths(): void {
        $parsed = \block_exaport\item_content_helper::parse_block_file_args([
            'portfolio', 'id', (string)$this->owner->id,
            'itemid', (string)$this->item->id,
            'blockid', '44',
            'nested', 'folder', 'document.pdf',
        ]);

        $this->assertSame('portfolio/id/' . $this->owner->id, $parsed['access']);
        $this->assertSame($this->item->id, $parsed['itemid']);
        $this->assertSame(44, $parsed['blockid']);
        $this->assertSame('/nested/folder/', $parsed['filepath']);
        $this->assertSame('document.pdf', $parsed['filename']);
    }

    public function test_block_file_argument_parser_rejects_missing_markers(): void {
        $this->assertSame([], \block_exaport\item_content_helper::parse_block_file_args([
            'portfolio', 'id', (string)$this->owner->id,
            'blockid', '44',
            'document.pdf',
        ]));

        $this->assertSame([], \block_exaport\item_content_helper::parse_block_file_args([
            'portfolio', 'id', (string)$this->owner->id,
            'itemid', (string)$this->item->id,
            'document.pdf',
        ]));
    }

    public function test_block_file_argument_parser_rejects_empty_filename(): void {
        $this->assertSame([], \block_exaport\item_content_helper::parse_block_file_args([
            'portfolio', 'id', (string)$this->owner->id,
            'itemid', (string)$this->item->id,
            'blockid', '44',
            '',
        ]));
    }
}
