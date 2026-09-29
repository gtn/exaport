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
require_once($CFG->dirroot . '/blocks/exaport/lib/item_content_helpers.php');
require_once($CFG->dirroot . '/blocks/exaport/lib/scorm_export_helpers.php');

/**
 * Tests SCORM rendering of legacy and structured item content.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scorm_export_helpers_test extends \advanced_testcase {

    public function test_mixed_content_is_rendered_in_order_with_collision_safe_paths(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner->id, 'note', 'https://legacy.example/path?a=1&b=2');
        $textid = $this->create_block($item->id, 'text', 1, 'First',
            '<p>Text <img src="@@PLUGINFILE@@/images/editor.png"></p>');
        $linkid = $this->create_block($item->id, 'link', 2, 'Second', '', 'https://structured.example/?a=1&b=2');
        $fileid1 = $this->create_block($item->id, 'file', 3, 'Third');
        $fileid2 = $this->create_block($item->id, 'file', 4, 'Fourth');
        $legacyfile = $this->create_file($owner->id, 'item_file', $item->id, '/', 'same.pdf');
        $this->create_file($owner->id, 'item_content_text', $textid, '/images/', 'editor.png');
        $this->create_file($owner->id, 'item_content_file', $fileid1, '/nested/', 'same.pdf');
        $this->create_file($owner->id, 'item_content_file', $fileid2, '/nested/', 'same.pdf');
        $item = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
        $calls = [];
        $package = static function(\stored_file $file, string $base) use (&$calls): string {
            $path = $base . $file->get_filepath() . block_exaport_scorm_path_component($file->get_filename());
            $calls[] = $path;
            return $path;
        };

        $result = block_exaport_scorm_render_item_content(
            $item,
            [$legacyfile],
            block_exaport_get_item_content_export_data($item),
            'categories/sub/item.html',
            $package
        );

        $html = $result['html'];
        $this->assertSame($calls, $result['assets']);
        $this->assertCount(4, $result['assets']);
        $this->assertSame([
            'items/' . $item->id . '/legacy/same.pdf',
            'items/' . $item->id . '/blocks/' . $textid . '/editor/images/editor.png',
            'items/' . $item->id . '/blocks/' . $fileid1 . '/nested/same.pdf',
            'items/' . $item->id . '/blocks/' . $fileid2 . '/nested/same.pdf',
        ], $result['assets']);
        $this->assertStringContainsString('https://legacy.example/path?a=1&amp;b=2', $html);
        $this->assertStringContainsString('https://structured.example/?a=1&amp;b=2', $html);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $html);
        $this->assertStringContainsString('../../items/' . $item->id . '/blocks/' . $textid .
            '/editor/images/editor.png', $html);
        $this->assertLessThan(strpos($html, 'Second'), strpos($html, 'First'));
        $this->assertLessThan(strpos($html, 'Third'), strpos($html, 'Second'));
        $this->assertLessThan(strpos($html, 'Fourth'), strpos($html, 'Third'));
    }

    public function test_structured_content_is_independent_of_parent_legacy_type(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner->id, 'link');
        $fileid = $this->create_block($item->id, 'file', 0, 'File on link item');
        $this->create_file($owner->id, 'item_content_file', $fileid, '/', 'document.pdf');
        $item = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);

        $result = block_exaport_scorm_render_item_content(
            $item,
            [],
            block_exaport_get_item_content_export_data($item),
            'item.html',
            static function(\stored_file $file, string $base): string {
                return $base . '/' . $file->get_filename();
            }
        );

        $this->assertStringContainsString('document.pdf', $result['html']);
        $this->assertSame(['items/' . $item->id . '/blocks/' . $fileid . '/document.pdf'], $result['assets']);
    }

    public function test_archive_paths_and_relative_urls_reject_traversal(): void {
        $this->assertSame('secret.txt', block_exaport_scorm_path_component('../../secret.txt'));
        $this->assertSame('file', block_exaport_scorm_path_component('..'));
        $this->assertSame(
            '../../items/5/blocks/9/nested/file%20name.pdf',
            block_exaport_scorm_relative_url(
                'categories/sub/item.html',
                'items/5/blocks/9/nested/file name.pdf'
            )
        );

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $file = $this->create_file($owner->id, 'item_content_file', 99, '/nested/', 'duplicate.pdf');
        $path = 'items/1/blocks/99/nested/duplicate.pdf';
        $this->assertSame($path, block_exaport_scorm_archive_path($file, 'items/1/blocks/99', []));
        $this->assertSame($path . '-1', block_exaport_scorm_archive_path($file, 'items/1/blocks/99', [$path]));
    }

    private function create_item(int $userid, string $type, string $url = ''): \stdClass {
        global $DB;
        $item = (object)[
            'userid' => $userid, 'type' => $type, 'categoryid' => 0, 'name' => 'Export item',
            'url' => $url, 'intro' => '', 'attachment' => '', 'timecreated' => time(),
            'timemodified' => time(), 'courseid' => 0, 'shareall' => 0, 'externaccess' => 0,
            'externcomment' => 0, 'sortorder' => 0, 'isoez' => 0, 'langid' => 0,
            'source' => 0, 'sourceid' => 0, 'iseditable' => 1, 'parentid' => 0,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    private function create_block(int $itemid, string $type, int $sortorder, string $title,
            string $content = '', string $url = ''): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $itemid, 'type' => $type, 'sortorder' => $sortorder, 'title' => $title,
            'content' => $content, 'contentformat' => FORMAT_HTML, 'url' => $url,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function create_file(int $userid, string $filearea, int $itemid, string $filepath,
            string $filename): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'block_exaport', 'filearea' => $filearea, 'itemid' => $itemid,
            'filepath' => $filepath, 'filename' => $filename,
        ], $filename . ' content');
    }
}
