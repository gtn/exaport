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
require_once($CFG->dirroot . '/blocks/exaport/lib/item_content_helpers.php');
require_once($CFG->dirroot . '/blocks/exaport/db/upgradelib.php');

/**
 * Tests structured item content loading and read-only output.
 *
 * @package    block_exaport
 * @copyright 2026 gtn gmbh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_content_blocks_test extends \advanced_testcase {

    public function test_empty_item_helpers_return_empty_defaults(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);

        $this->assertSame([], block_exaport_get_item_content_blocks($itemid));
        $this->assertSame(0, block_exaport_get_next_item_content_sortorder($itemid));
        $this->assertFalse(block_exaport_item_has_structured_link_or_file_content($itemid));
        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame([], block_exaport_get_item_content_export_data($item));
    }

    public function test_export_projection_preserves_order_fields_and_file_areas(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $fileid = $this->insert_block($itemid, 'file', 20, 'Files');
        $linkid = $this->insert_block($itemid, 'link', 10, 'Link', '', 'https://example.test/link');
        $textid = $this->insert_block($itemid, 'text', 10, 'Text', '<p>Body</p>');
        $context = \context_user::instance($owner->id);
        foreach ([
            [$fileid, 'item_content_file', '/nested/', 'document.pdf'],
            [$textid, 'item_content_text', '/', 'embedded.png'],
        ] as [$blockid, $filearea, $filepath, $filename]) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'block_exaport',
                'filearea' => $filearea,
                'itemid' => $blockid,
                'filepath' => $filepath,
                'filename' => $filename,
            ], 'content');
        }

        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $projection = block_exaport_get_item_content_export_data($item);

        $this->assertSame([$linkid, $textid, $fileid], array_column($projection, 'blockid'));
        $this->assertSame(['link', 'text', 'file'], array_column($projection, 'type'));
        $this->assertSame('https://example.test/link', $projection[0]['url']);
        $this->assertSame('<p>Body</p>', $projection[1]['content']);
        $this->assertSame((int)FORMAT_HTML, $projection[1]['contentformat']);
        $this->assertSame('embedded.png', $projection[1]['editorfiles'][0]->get_filename());
        $this->assertSame('document.pdf', $projection[2]['files'][0]->get_filename());
        $this->assertSame($itemid, $projection[2]['itemid']);
        $this->assertSame((int)$owner->id, $projection[2]['ownerid']);
        $this->assertNotSame($projection[2]['itemid'], $projection[2]['blockid']);
    }

    public function test_file_block_relationship_cannot_be_substituted(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $otheritemid = $this->insert_item($owner->id, $course->id);
        $blockid = $this->insert_block($itemid, 'file', 0, 'Private file');

        $this->assertNotFalse(block_exaport_get_item_content_file_block($itemid, $blockid));
        $this->assertFalse(block_exaport_get_item_content_file_block($otheritemid, $blockid));
    }

    public function test_record_creation_helpers_append_without_form_data(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $this->insert_block($itemid, 'text', 4, 'Existing');

        $text = block_exaport_create_content_block($itemid, 'text', 'Imported text', '', [
            'sortorder' => 12,
            'content' => 'Imported content',
            'contentformat' => FORMAT_PLAIN,
            'timecreated' => 123,
            'timemodified' => 456,
        ]);
        $link = block_exaport_create_link_content_block($itemid, 'Example', 'https://example.com/');
        $file = block_exaport_create_file_content_block($itemid, 'Documents');

        $this->assertIsInt($text->id);
        $this->assertSame('text', $text->type);
        $this->assertSame(12, $text->sortorder);
        $this->assertSame('Imported content', $text->content);
        $this->assertSame(FORMAT_PLAIN, $text->contentformat);
        $this->assertSame(123, $text->timecreated);
        $this->assertSame(456, $text->timemodified);
        $this->assertGreaterThan(0, $link->id);
        $this->assertIsInt($link->id);
        $this->assertSame('link', $link->type);
        $this->assertSame('https://example.com/', $link->url);
        $this->assertSame(13, $link->sortorder);
        $this->assertIsInt($file->id);
        $this->assertSame('file', $file->type);
        $this->assertSame(14, $file->sortorder);
        $this->assertTrue(block_exaport_item_has_structured_link_or_file_content($itemid));
        $this->assertSame(15, block_exaport_get_next_item_content_sortorder($itemid));

        $this->expectException(\coding_exception::class);
        block_exaport_create_content_block($itemid, 'unsupported');
    }

    public function test_item_content_modal_uses_moodle_dynamic_form(): void {
        $this->assertTrue(function_exists('block_exaport_item_is_editable'));
        $this->assertTrue(is_subclass_of(
            \block_exaport\form\item_content::class,
            \core_form\dynamic_form::class
        ));
    }

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

        $this->assertSame([0, 1, 2], array_keys($blocks));
        $this->assertSame([$thirdid, $firstid, $secondid], array_map(
            static function(\stdClass $block): int {
                return (int)$block->id;
            },
            $blocks
        ));
        $this->assertSame(['First', 'Second', 'Third'], array_map(
            static function(\stdClass $block): string {
                return $block->title;
            },
            $blocks
        ));
        $this->assertSame(1, $DB->count_records('block_exaportitemblock', ['itemid' => $otheritemid]));
    }

    public function test_supported_loader_and_renderer_include_links_and_files(): void {
        global $CFG, $OUTPUT;

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
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'block_exaport',
            'filearea' => 'item_content_file',
            'itemid' => $fileid,
            'filepath' => '/',
            'filename' => 'alpha.txt',
        ], 'text');

        $files = block_exaport_get_item_content_files($owner->id, $fileid);
        $this->assertSame(['alpha.txt', 'picture.png'], array_map(
            static function(\stored_file $file): string {
                return $file->get_filename();
            },
            $files
        ));

        $blocks = block_exaport_get_item_content_blocks($itemid);
        $this->assertSame([$linkid, $fileid], array_map(
            static function(\stdClass $block): int {
                return (int)$block->id;
            },
            $blocks
        ));

        $renderer = $this->getMockBuilder(\core_renderer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pix_icon'])
            ->getMock();
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
        $this->assertSame(['alpha.txt', 'picture.png'], array_column($data['blocks'][1]['files'], 'name'));
        $this->assertStringContainsString(
            $CFG->wwwroot . '/pluginfile.php/' . $context->id . '/block_exaport/item_content_file/' .
                'itemid/' . $itemid . '/blockid/' . $fileid . '/picture.png',
            $data['blocks'][1]['files'][1]['url']
        );
        $this->assertCount(3, $data['addactions']);
        $this->assertSame(['text', 'link', 'file'], array_column($data['addactions'], 'type'));
        $this->assertStringContainsString('>T</span>', $data['addactions'][0]['icon']);
        $this->assertSame('icon', $data['addactions'][1]['icon']);
        $this->assertSame('icon', $data['addactions'][2]['icon']);

        $html = $OUTPUT->render_from_template('block_exaport/item_content_blocks', $data);
        $this->assertStringContainsString(
            'divider d-flex justify-content-center align-items-center my-3',
            $html
        );
        $this->assertStringContainsString('<div class="divider-content px-3">', $html);
        $this->assertStringContainsString('btn add-content exaport-item-content-add-button', $html);
        $this->assertStringNotContainsString('dropdown-toggle', $html);
        $this->assertStringContainsString('data-content-type="text"', $html);
        $this->assertStringContainsString('data-content-url=', $html);
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

        $renderer = $this->getMockBuilder(\core_renderer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['pix_icon'])
            ->getMock();
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

    public function test_mixed_content_is_safe_media_aware_and_available_to_pdf(): void {
        global $OUTPUT;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $this->insert_block($itemid, 'text', 0, '<img src=x onerror=alert(1)>', '<p>First</p>');
        $this->insert_block($itemid, 'link', 1, 'Safe link', '', 'https://example.com/path?a=1&b=2');
        $fileid = $this->insert_block($itemid, 'file', 2, 'Media');
        $context = \context_user::instance($owner->id);
        foreach ([
            'photo.png' => ['image/png', 'image'],
            'movie.webm' => ['video/webm', 'video'],
            'notes<script>.txt' => ['text/plain', 'text'],
        ] as $filename => [$mimetype, $content]) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'block_exaport',
                'filearea' => 'item_content_file',
                'itemid' => $fileid,
                'filepath' => '/',
                'filename' => $filename,
                'mimetype' => $mimetype,
            ], $content);
        }

        $renderable = new \block_exaport\output\item_content_blocks(
            block_exaport_get_item_content_blocks($itemid), null, $owner->id, false, false, 'view/public-token'
        );
        $data = $renderable->export_for_template($OUTPUT);

        $this->assertTrue(\block_exaport\output\item_content_blocks::has_displayable_content($data));
        $this->assertSame(['text', 'link', 'file'], array_column($data['blocks'], 'type'));
        $this->assertCount(3, $data['blocks'][2]['files']);
        $videofiles = array_values(array_filter($data['blocks'][2]['files'], static function(array $file): bool {
            return $file['isvideo'];
        }));
        $this->assertCount(1, $videofiles);
        $this->assertStringContainsString('/item_content_file/view/public-token/itemid/' . $itemid .
            '/blockid/' . $fileid . '/', $data['blocks'][2]['files'][0]['url']);

        $html = $OUTPUT->render_from_template('block_exaport/item_content_blocks', $data);
        $pdf = $renderable->render_for_pdf($OUTPUT, $data);
        $this->assertStringContainsString('<video controls', $html);
        $this->assertStringContainsString('First', $pdf);
        $this->assertStringContainsString('https://example.com/path?a=1&amp;b=2', $pdf);
        $this->assertStringContainsString('photo.png', $pdf);
        $this->assertStringNotContainsString('<img src=x onerror=', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_empty_file_block_does_not_mask_missing_content(): void {
        global $OUTPUT;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $this->insert_block($itemid, 'file', 0, 'Empty');
        $this->insert_block($itemid, 'text', 1, 'Also empty', '');
        $data = (new \block_exaport\output\item_content_blocks(
            block_exaport_get_item_content_blocks($itemid), null, $owner->id, false
        ))->export_for_template($OUTPUT);

        $this->assertFalse(\block_exaport\output\item_content_blocks::has_displayable_content($data));
    }

    public function test_nested_file_block_url_preserves_filepath(): void {
        global $OUTPUT;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $fileblockid = $this->insert_block($itemid, 'file', 0, 'Nested file');
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id,
            'component' => 'block_exaport',
            'filearea' => 'item_content_file',
            'itemid' => $fileblockid,
            'filepath' => '/folder1/folder2/',
            'filename' => 'evidence image.png',
            'mimetype' => 'image/png',
        ], 'image');

        $data = (new \block_exaport\output\item_content_blocks(
            block_exaport_get_item_content_blocks($itemid), null, $owner->id, false, false, 'view/public-token'
        ))->export_for_template($OUTPUT);

        $this->assertStringContainsString(
            '/item_content_file/view/public-token/itemid/' . $itemid . '/blockid/' . $fileblockid .
                '/folder1/folder2/evidence%20image.png',
            $data['blocks'][0]['files'][0]['url']
        );
    }

    public function test_external_item_renders_migrated_and_existing_structured_content(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $DB->set_field('block_exaportitem', 'type', 'file', ['id' => $itemid]);
        $DB->set_field('block_exaportitem', 'url', 'https://legacy.example/', ['id' => $itemid]);
        $this->insert_block($itemid, 'text', 0, 'Structured text', '<p>Structured body</p>');
        $fileid = $this->insert_block($itemid, 'file', 1, 'Structured files');
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id,
            'component' => 'block_exaport',
            'filearea' => 'item_content_file',
            'itemid' => $fileid,
            'filepath' => '/',
            'filename' => 'structured.pdf',
        ], 'pdf');
        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        block_exaport_migrate_legacy_item_content($item);
        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);

        ob_start();
        block_exaport_print_extern_item($item, '');
        $html = ob_get_clean();

        $this->assertStringContainsString('https://legacy.example/', $html);
        $this->assertStringContainsString('Structured body', $html);
        $this->assertStringContainsString('structured.pdf', $html);
        $this->assertStringNotContainsString(block_exaport_get_string('filenotfound'), $html);
    }

    public function test_view_editor_block_data_contains_structured_content(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $this->insert_block($itemid, 'text', 0, 'Editor content', '<p>Visible in editor</p>');
        $fileblockid = $this->insert_block($itemid, 'file', 1, 'Editor file');
        $ownercontext = \context_user::instance($owner->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $ownercontext->id,
            'component' => 'block_exaport',
            'filearea' => 'item_content_file',
            'itemid' => $fileblockid,
            'filepath' => '/',
            'filename' => 'editor.pdf',
        ], 'pdf');
        $viewid = $DB->insert_record('block_exaportview', (object)[
            'userid' => $owner->id,
            'creatorid' => $owner->id,
            'name' => 'Editor preview',
            'timemodified' => time(),
        ]);
        $viewblockid = $DB->insert_record('block_exaportviewblock', (object)[
            'viewid' => $viewid,
            'positionx' => 1,
            'positiony' => 1,
            'type' => 'item',
            'itemid' => $itemid,
            'width' => 320,
            'height' => 240,
        ]);

        $blocks = block_exaport_get_view_blocks((object)['id' => $viewid, 'userid' => $owner->id]);

        $this->assertArrayHasKey($viewblockid, $blocks);
        $this->assertStringContainsString('Visible in editor', $blocks[$viewblockid]->item->intro);
        $this->assertStringContainsString('exaport-item-content-section', $blocks[$viewblockid]->item->intro);
        $this->assertStringContainsString('/pluginfile.php/' . $ownercontext->id .
            '/block_exaport/item_content_file/itemid/' . $itemid . '/blockid/' . $fileblockid . '/editor.pdf',
            $blocks[$viewblockid]->item->intro);
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
