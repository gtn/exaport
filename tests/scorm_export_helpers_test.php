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
require_once($CFG->dirroot . '/blocks/exaport/lib/package_import_helpers.php');

/**
 * Tests SCORM rendering of structured item content.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scorm_export_helpers_test extends \advanced_testcase {

    public function test_residual_legacy_content_is_ignored_in_favour_of_structured_blocks(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->create_item(
            $owner->id,
            'note',
            'https://legacy.example/path?a=1&b=2',
            'same.pdf'
        );
        $textid = $this->create_block($item->id, 'text', 1, 'First',
            '<p>Text <img src="@@PLUGINFILE@@/images/editor.png"></p>');
        $linkid = $this->create_block($item->id, 'link', 2, 'Second', '', 'https://structured.example/?a=1&b=2');
        $fileid1 = $this->create_block($item->id, 'file', 3, 'Third');
        $fileid2 = $this->create_block($item->id, 'file', 4, 'Fourth');
        $this->create_file($owner->id, 'item_file', $item->id, '/', 'same.pdf');
        $this->create_file($owner->id, 'item_content_text', $textid, '/images/', 'editor.png');
        $this->create_file($owner->id, 'item_content_file', $fileid1, '/nested/', 'same.pdf');
        $this->create_file($owner->id, 'item_content_file', $fileid2, '/nested/', 'same.pdf');
        $calls = [];
        $package = static function(\stored_file $file, string $base) use (&$calls): string {
            $path = $base . $file->get_filepath() . block_exaport_scorm_path_component($file->get_filename());
            $calls[] = $path;
            return $path;
        };

        $result = block_exaport_scorm_render_item_content(
            $item,
            block_exaport_get_item_content_export_data($item),
            'categories/sub/item.html',
            $package
        );

        $html = $result['html'];
        $this->assertSame($calls, $result['assets']);
        $this->assertCount(3, $result['assets']);
        $this->assertSame([
            'items/' . $item->id . '/blocks/' . $textid . '/editor/images/editor.png',
            'items/' . $item->id . '/blocks/' . $fileid1 . '/nested/same.pdf',
            'items/' . $item->id . '/blocks/' . $fileid2 . '/nested/same.pdf',
        ], $result['assets']);
        $this->assertStringNotContainsString('https://legacy.example/path', $html);
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
        $this->assertSame('__secret.txt', block_exaport_scorm_path_component('../../secret.txt'));
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

    public function test_structured_package_round_trip_preserves_mixed_content_and_files(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $source = $this->create_item($owner->id, 'file');
        $source->intro = '<p>Parent intro</p>';
        $DB->set_field('block_exaportitem', 'intro', $source->intro, ['id' => $source->id]);
        $textid = $this->create_block($source->id, 'text', 10, 'Rich text',
            '<p><img src="@@PLUGINFILE@@/images/picture one.png"></p>');
        $this->create_block($source->id, 'link', 20, 'First link', '', 'https://example.test/one');
        $fileid1 = $this->create_block($source->id, 'file', 30, 'Documents');
        $this->create_block($source->id, 'link', 40, 'Second link', '', 'https://example.test/two');
        $fileid2 = $this->create_block($source->id, 'file', 50, 'Duplicate name');
        $this->create_file($owner->id, 'item_content_text', $textid, '/images/', 'picture one.png');
        $this->create_file($owner->id, 'item_content_file', $fileid1, '/nested path/', 'same #.txt');
        $this->create_file($owner->id, 'item_content_file', $fileid2, '/elsewhere/', 'same #.txt');

        $root = make_request_directory();
        $package = static function(\stored_file $file, string $base) use ($root): string {
            $path = block_exaport_scorm_archive_path($file, $base, []);
            check_dir_exists(dirname($root . '/' . $path));
            file_put_contents($root . '/' . $path, $file->get_content());
            return $path;
        };
        $export = block_exaport_scorm_build_item_package($source,
            block_exaport_get_item_content_export_data($source), 'category/item.html', $package);
        $json = json_encode($export['manifest']);
        $manifest = block_exaport_decode_item_package($json);
        $destination = $this->create_item($owner->id, 'file', 'https://must.be/cleared', 'old.txt');
        $DB->set_field('block_exaportitem', 'url', '', ['id' => $destination->id]);
        $DB->set_field('block_exaportitem', 'attachment', '', ['id' => $destination->id]);
        block_exaport_import_item_package($destination, $root, $manifest);

        $blocks = block_exaport_get_item_content_blocks($destination->id);
        $this->assertSame(['text', 'link', 'file', 'link', 'file'], array_column($blocks, 'type'));
        $this->assertSame([10, 20, 30, 40, 50], array_map('intval', array_column($blocks, 'sortorder')));
        $this->assertSame(['Rich text', 'First link', 'Documents', 'Second link', 'Duplicate name'],
            array_column($blocks, 'title'));
        $this->assertSame('https://example.test/one', $blocks[1]->url);
        $this->assertSame('https://example.test/two', $blocks[3]->url);
        $editorfiles = get_file_storage()->get_area_files(\context_user::instance($owner->id)->id,
            'block_exaport', 'item_content_text', $blocks[0]->id, 'id', false);
        $this->assertCount(1, $editorfiles);
        $this->assertSame('/images/picture one.png', reset($editorfiles)->get_filepath() . reset($editorfiles)->get_filename());
        $firstfiles = block_exaport_get_item_content_files($owner->id, $blocks[2]->id);
        $secondfiles = block_exaport_get_item_content_files($owner->id, $blocks[4]->id);
        $this->assertSame('/nested path/same #.txt', $firstfiles[0]->get_filepath() . $firstfiles[0]->get_filename());
        $this->assertSame('/elsewhere/same #.txt', $secondfiles[0]->get_filepath() . $secondfiles[0]->get_filename());
        $this->assertSame('same #.txt content', $firstfiles[0]->get_content());
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $export['html']);
    }

    public function test_structured_package_rejects_malformed_and_unsafe_metadata(): void {
        $this->expectException(\invalid_parameter_exception::class);
        block_exaport_decode_item_package('{"format":"exaport-item-content","version":2}');
    }

    public function test_structured_package_rejects_unsafe_asset_path(): void {
        $manifest = [
            'format' => 'exaport-item-content', 'version' => 1,
            'parent' => ['type' => 'note', 'intro' => ''],
            'blocks' => [[
                'type' => 'file', 'sortorder' => 0, 'title' => '', 'content' => '',
                'contentformat' => FORMAT_HTML, 'url' => '',
                'files' => [['filepath' => '/../', 'filename' => 'secret.txt',
                    'archivepath' => '../secret.txt']], 'textassets' => [],
            ]],
        ];
        $this->expectException(\invalid_parameter_exception::class);
        block_exaport_decode_item_package(json_encode($manifest));
    }

    private function create_item(int $userid, string $type, string $url = '', string $attachment = ''): \stdClass {
        global $DB;
        $item = (object)[
            'userid' => $userid, 'type' => $type, 'categoryid' => 0, 'name' => 'Export item',
            'url' => $url, 'intro' => '', 'attachment' => $attachment, 'timecreated' => time(),
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
