<?php
// This file is part of Exabis ePortfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/package_import_helpers.php');
require_once($CFG->dirroot . '/blocks/exaport/lib/scorm_export_helpers.php');

/**
 * Exercise ZIP extraction together with historical and structured item imports.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class package_archive_test extends \advanced_testcase {

    /** Create and reopen a real archive, preserving the supplied entry names. */
    private function archive(array $entries): \ZipArchive {
        $path = make_request_directory() . '/portfolio.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        foreach ($entries as $name => $content) {
            $this->assertTrue($content === null ? $zip->addEmptyDir($name) : $zip->addFromString($name, $content));
        }
        $this->assertTrue($zip->close());
        $this->assertTrue($zip->open($path));
        return $zip;
    }

    /** Both archive conventions retain nested names, Unicode and exact file bytes. */
    public function test_current_and_historical_directory_entries_are_compatible(): void {
        $this->resetAfterTest(true);
        foreach (['categories/', '/categories/'] as $directory) {
            $zip = $this->archive([
                $directory => null,
                'imsmanifest.xml' => '<manifest/>',
                'categories/Work/item.html' => '<p>Exported item</p>',
                'items/nested space/ümlaut.txt' => 'Original nested bytes',
            ]);
            $path = $zip->filename;
            $hash = sha1_file($path);
            $root = make_request_directory();
            try {
                block_exaport_extract_import_archive($zip, $root);
                $this->assertSame('<manifest/>', file_get_contents($root . '/imsmanifest.xml'));
                $this->assertSame('<p>Exported item</p>', file_get_contents($root . '/categories/Work/item.html'));
                $this->assertSame('Original nested bytes', file_get_contents($root . '/items/nested space/ümlaut.txt'));
            } finally {
                $zip->close();
            }
            $this->assertSame($hash, sha1_file($path), 'Import must not rewrite the recovery archive');
        }
    }

    /** No safe entry may be extracted before the complete archive passes validation. */
    public function test_unsafe_entries_are_rejected_before_any_extraction(): void {
        $this->resetAfterTest(true);
        $names = [
            '../outside.txt',
            'categories/../../outside.txt',
            'categories\\..\\..\\outside.txt',
            '/outside.txt',
            '/categories/outside.txt',
            '/categories',
            '//categories/',
            '\\outside.txt',
            'C:/outside.txt',
            'C:\\outside.txt',
            'C:outside.txt',
            '//server/share/outside.txt',
        ];
        foreach ($names as $name) {
            $zip = $this->archive([
                '/categories/' => null,
                'safe.txt' => 'Must not be extracted',
                $name => 'Unsafe entry',
            ]);
            $parent = make_request_directory();
            $root = $parent . '/extracted';
            mkdir($root);
            file_put_contents($parent . '/outside.txt', 'Existing outside bytes');
            try {
                block_exaport_extract_import_archive($zip, $root);
                $this->fail('Unsafe archive entry was accepted: ' . $name);
            } catch (\moodle_exception $exception) {
                $this->assertSame('couldntextractscormfile', $exception->errorcode, $name);
                $this->assertSame(['.', '..'], scandir($root), $name);
                $this->assertSame('Existing outside bytes', file_get_contents($parent . '/outside.txt'), $name);
            } finally {
                $zip->close();
            }
        }
    }

    /** An empty extraction allowlist must not select the entire archive. */
    public function test_archive_containing_only_the_legacy_marker_is_rejected(): void {
        $this->resetAfterTest(true);
        $zip = $this->archive(['/categories/' => null]);
        $root = make_request_directory();
        try {
            $this->expectException(\moodle_exception::class);
            block_exaport_extract_import_archive($zip, $root);
        } finally {
            $zip->close();
            $this->assertSame(['.', '..'], scandir($root));
        }
    }

    /** Productive-era archives without structured metadata still import files and URLs. */
    public function test_historical_archive_without_sidecars_imports_legacy_content(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $zip = $this->archive([
            '/categories/' => null,
            'categories/Work/file.html' => '<!--###BOOKMARK_FILE_URL###-->first.txt<!--###BOOKMARK_FILE_URL###-->' .
                '<!--###BOOKMARK_FILE_URL###-->second ü.txt<!--###BOOKMARK_FILE_URL###-->' .
                '<!--###BOOKMARK_FILE_DESC###--><p>Old description</p><!--###BOOKMARK_FILE_DESC###-->',
            'categories/Work/link.html' => '<!--###BOOKMARK_EXT_URL###-->https://example.test/?a=1&amp;b=2' .
                '<!--###BOOKMARK_EXT_URL###--><!--###BOOKMARK_EXT_DESC###-->Old link<!--###BOOKMARK_EXT_DESC###-->',
            'first.txt' => 'First legacy attachment',
            'second ü.txt' => 'Second legacy attachment',
        ]);
        $root = make_request_directory();
        try {
            block_exaport_extract_import_archive($zip, $root);
        } finally {
            $zip->close();
        }
        $aftercreate = static function(): void {};
        $fileitem = block_exaport_import_legacy_item($root, file_get_contents($root . '/categories/Work/file.html'),
            'Old files', $course->id, $owner->id, $aftercreate);
        $linkitem = block_exaport_import_legacy_item($root, file_get_contents($root . '/categories/Work/link.html'),
            'Old link', $course->id, $owner->id, $aftercreate);
        $fileblocks = block_exaport_get_item_content_export_data($fileitem);
        $this->assertCount(1, $fileblocks);
        $this->assertSame('file', $fileblocks[0]['type']);
        $this->assertSame(['first.txt', 'second ü.txt'], array_map(
            static fn(\stored_file $file) => $file->get_filename(), $fileblocks[0]['files']));
        $this->assertSame(['First legacy attachment', 'Second legacy attachment'], array_map(
            static fn(\stored_file $file) => $file->get_content(), $fileblocks[0]['files']));
        $this->assertSame('<p>Old description</p>', $fileitem->intro);
        $linkblocks = block_exaport_get_item_content_blocks((int)$linkitem->id);
        $this->assertCount(1, $linkblocks);
        $this->assertSame('https://example.test/?a=1&b=2', $linkblocks[0]->url);
        $this->assertSame('', $fileitem->attachment);
        $this->assertSame('', $linkitem->url);
    }

    /** A real ZIP round trip preserves ordered mixed blocks across owners in both layouts. */
    public function test_structured_archives_preserve_content_files_and_embedded_assets(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $source = $this->item($owner->id);
        $text = block_exaport_create_content_block($source->id, 'text', 'Rich text', '', [
            'sortorder' => 2, 'content' => '<p>Text<img src="@@PLUGINFILE@@/images/picture.png"></p>',
        ]);
        block_exaport_create_link_content_block($source->id, 'Link', 'https://example.test/?a=1&b=2', ['sortorder' => 4]);
        $first = block_exaport_create_file_content_block($source->id, 'First files', ['sortorder' => 7]);
        $second = block_exaport_create_file_content_block($source->id, 'Other files', ['sortorder' => 10]);
        $this->file($owner->id, 'item_content_text', $text->id, '/images/', 'picture.png', 'Embedded image bytes');
        $this->file($owner->id, 'item_content_file', $first->id, '/nested space/', 'same ü.txt', 'First file bytes');
        $this->file($owner->id, 'item_content_file', $second->id, '/other/', 'same ü.txt', 'Second file bytes');
        $sourceblocks = block_exaport_get_item_content_export_data($source);

        foreach (['categories/', '/categories/'] as $directory) {
            $path = make_request_directory() . '/portfolio.zip';
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
            $zip->addEmptyDir($directory);
            $export = block_exaport_scorm_build_item_package($source, $sourceblocks, 'categories/item.html',
                static function(\stored_file $file, string $base) use ($zip): string {
                    $path = block_exaport_scorm_archive_path($file, $base, []);
                    $zip->addFromString($path, $file->get_content());
                    return $path;
                });
            $zip->addFromString('items/content.json', json_encode($export['manifest']));
            $zip->addFromString('categories/item.html', $export['html']);
            $this->assertTrue($zip->close());
            $this->assertTrue($zip->open($path));
            $root = make_request_directory();
            try {
                block_exaport_extract_import_archive($zip, $root);
            } finally {
                $zip->close();
            }
            $destination = $this->item($recipient->id);
            $manifest = block_exaport_decode_item_package(file_get_contents($root . '/items/content.json'));
            block_exaport_import_item_package($destination, $root, $manifest);
            $restored = block_exaport_get_item_content_export_data($destination);
            $this->assertCount(count($sourceblocks), $restored);
            foreach ($sourceblocks as $index => $block) {
                foreach (['type', 'sortorder', 'title', 'content', 'contentformat', 'url'] as $field) {
                    $this->assertSame($block[$field], $restored[$index][$field], $field);
                }
                $this->assertNotSame($block['blockid'], $restored[$index]['blockid']);
                foreach (['files', 'editorfiles'] as $area) {
                    $this->assertCount(count($block[$area]), $restored[$index][$area]);
                    foreach ($block[$area] as $fileindex => $file) {
                        $copy = $restored[$index][$area][$fileindex];
                        $this->assertSame($file->get_filepath(), $copy->get_filepath());
                        $this->assertSame($file->get_filename(), $copy->get_filename());
                        $this->assertSame($file->get_content(), $copy->get_content());
                        $this->assertSame((int)$recipient->id, (int)$copy->get_userid());
                        $this->assertSame(\context_user::instance($recipient->id)->id, (int)$copy->get_contextid());
                    }
                }
            }
        }
    }

    private function item(int $userid): \stdClass {
        global $DB;
        $item = (object)['userid' => $userid, 'type' => 'file', 'name' => 'ZIP item',
            'intro' => 'Parent description', 'url' => '', 'attachment' => '', 'courseid' => 0,
            'timecreated' => time(), 'timemodified' => time()];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    private function file(int $userid, string $area, int $blockid, string $path, string $name, string $bytes): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id, 'component' => 'block_exaport',
            'filearea' => $area, 'itemid' => $blockid, 'filepath' => $path, 'filename' => $name, 'userid' => $userid,
        ], $bytes);
    }
}
