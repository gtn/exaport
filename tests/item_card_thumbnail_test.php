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

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');

/**
 * Tests thumbnail selection in modern item card output.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_card_thumbnail_test extends \advanced_testcase {
    private \stdClass $owner;
    private \stdClass $recipient;
    private \stdClass $course;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        $this->recipient = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        $this->setUser($this->recipient);
    }

    private function create_item(string $type = 'file', string $name = 'Test item'): \stdClass {
        global $DB;

        $itemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $this->owner->id,
            'type' => $type,
            'categoryid' => 0,
            'name' => $name,
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

        $DB->insert_record('block_exaportitemshar', (object)[
            'itemid' => $itemid,
            'userid' => $this->recipient->id,
        ]);

        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $item->comments = 0;
        $item->flatcategories = [];

        return $item;
    }

    private function add_item_file(\stdClass $item, string $filearea, string $filename, string $content,
                                   string $mimetype): void {
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($item->userid)->id,
            'component' => 'block_exaport',
            'filearea' => $filearea,
            'itemid' => $item->id,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ], $content);
    }

    private function export_item_card(\stdClass $item, string $type = 'shared'): array {
        $renderer = $this->getMockBuilder(\renderer_base::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $card = new \block_exaport\output\item_card(
            $item,
            $this->course->id,
            $type,
            0,
            (object)['id' => 0],
            true,
            false
        );

        return $card->export_for_template($renderer);
    }

    private function get_png_content(): string {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg=='
        );
    }

    public function test_shared_image_file_item_exports_thumbnail(): void {
        $item = $this->create_item();
        $this->add_item_file($item, 'item_file', 'photo.png', $this->get_png_content(), 'image/png');

        $data = $this->export_item_card($item);

        $this->assertTrue($data['hasthumbnail']);
        $this->assertStringContainsString('/pluginfile.php/', $data['thumbnailurl']);
        $this->assertStringContainsString('/item_file/portfolio/id/' . $this->owner->id . '/itemid/' . $item->id . '/photo.png',
            $data['thumbnailurl']);
        $this->assertSame('Test item', $data['thumbnailalt']);
    }

    public function test_non_image_file_item_exports_no_thumbnail(): void {
        $item = $this->create_item();
        $this->add_item_file($item, 'item_file', 'document.txt', 'not an image', 'text/plain');

        $data = $this->export_item_card($item);

        $this->assertFalse($data['hasthumbnail']);
        $this->assertSame('', $data['thumbnailurl']);
        $this->assertSame('', $data['thumbnailalt']);
    }

    public function test_note_item_exports_no_thumbnail(): void {
        $item = $this->create_item('note');

        $data = $this->export_item_card($item);

        $this->assertFalse($data['hasthumbnail']);
    }

    public function test_custom_item_icon_takes_precedence_over_file_image(): void {
        $item = $this->create_item();
        $this->add_item_file($item, 'item_file', 'photo.png', $this->get_png_content(), 'image/png');
        $this->add_item_file($item, 'item_iconfile', 'custom.png', $this->get_png_content(), 'image/png');

        $data = $this->export_item_card($item);

        $this->assertTrue($data['hasthumbnail']);
        $this->assertStringContainsString('/item_iconfile/portfolio/id/' . $this->owner->id . '/itemid/' . $item->id . '/custom.png',
            $data['thumbnailurl']);
    }

    public function test_external_category_item_uses_external_thumbnail_access(): void {
        $item = $this->create_item();
        $item->thumbnail_access = 'category/hash/' . $this->owner->id . '-abcdef12';
        $item->extern_item_url = 'https://example.invalid/shared-item';
        $this->add_item_file($item, 'item_file', 'photo.png', $this->get_png_content(), 'image/png');

        $data = $this->export_item_card($item, 'extern_category');

        $this->assertTrue($data['hasthumbnail']);
        $this->assertStringContainsString(
            '/item_file/category/hash/' . $this->owner->id . '-abcdef12/itemid/' . $item->id . '/photo.png',
            $data['thumbnailurl']
        );
    }

    public function test_external_category_custom_icon_uses_item_iconfile_path(): void {
        $item = $this->create_item();
        $item->thumbnail_access = 'category/hash/' . $this->owner->id . '-abcdef12';
        $item->extern_item_url = 'https://example.invalid/shared-item';
        $this->add_item_file($item, 'item_file', 'photo.png', $this->get_png_content(), 'image/png');
        $this->add_item_file($item, 'item_iconfile', 'custom.png', $this->get_png_content(), 'image/png');

        $data = $this->export_item_card($item, 'extern_category');

        $this->assertTrue($data['hasthumbnail']);
        $this->assertStringContainsString(
            '/item_iconfile/category/hash/' . $this->owner->id . '-abcdef12/itemid/' . $item->id . '/custom.png',
            $data['thumbnailurl']
        );
    }

    public function test_missing_file_item_exports_no_thumbnail(): void {
        $item = $this->create_item();

        $data = $this->export_item_card($item);

        $this->assertFalse($data['hasthumbnail']);
    }
}
