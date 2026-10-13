<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib.php');

/**
 * Regression tests for safe item attachment delivery.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class item_file_delivery_test extends \advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    private function create_file(string $name, string $mimetype, string $content,
            string $area = 'item_content_file', int $itemid = 1): \stored_file {
        global $USER;

        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'block_exaport', 'filearea' => $area, 'itemid' => $itemid,
            'filepath' => '/', 'filename' => $name, 'mimetype' => $mimetype,
        ], $content);
    }

    /** Capture a response without terminating the test process. */
    private function response(\stored_file $file, bool $download): string {
        ob_start();
        try {
            block_exaport_send_item_file($file, 0, $download, ['dontdie' => true]);
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /** @return array HTML MIME types and mismatched filename metadata. */
    public static function html_files(): array {
        return [
            'html MIME' => ['report.bin', 'text/html'],
            'html extension' => ['report.HTML', 'application/octet-stream'],
            'htm extension' => ['report.htm', 'text/plain'],
            'xhtml MIME' => ['report.xml', 'application/xhtml+xml'],
            'xhtml extension' => ['report.xhtml', 'application/octet-stream'],
        ];
    }

    /** @dataProvider html_files */
    public function test_html_preview_and_download_are_cleaned_without_changing_storage(
            string $name, string $mimetype): void {
        $original = '<script>alert(1)</script><p onclick="alert(2)">Safe text</p>' .
            '<a href="javascript:alert(3)">Safe link</a>';
        $file = $this->create_file($name, $mimetype, $original);

        foreach ([false, true] as $download) {
            $response = $this->response($file, $download);
            $this->assertStringContainsString('Safe text', $response);
            $this->assertStringContainsString('Safe link', $response);
            $this->assertStringNotContainsString('<script', $response);
            $this->assertStringNotContainsString('onclick', $response);
            $this->assertStringNotContainsString('javascript:', $response);
        }
        $this->assertSame($original, $file->get_content());
    }

    public function test_non_html_files_keep_moodle_conditional_delivery_and_original_bytes(): void {
        $files = [
            ['report.pdf', 'application/pdf', "%PDF-1.4\noriginal PDF bytes"],
            ['picture.png', 'image/png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWioAAAAASUVORK5CYII='
            )],
            ['notes.txt', 'text/plain', '<script>this is plain text</script>'],
        ];
        $savedserver = $_SERVER;
        try {
            foreach ($files as [$name, $mimetype, $content]) {
                $file = $this->create_file($name, $mimetype, $content);
                // Moodle's binary sender flushes output buffers. A matching ETag
                // exercises its conditional response without disrupting PHPUnit.
                $_SERVER['HTTP_IF_NONE_MATCH'] = '"' . $file->get_contenthash() . '"';
                $this->assertSame('', $this->response($file, false));
                $this->assertSame('', $this->response($file, true));
                $this->assertSame($content, $file->get_content());
            }
        } finally {
            $_SERVER = $savedserver;
        }
    }

    public function test_pluginfile_attachment_routes_apply_the_same_html_cleaning(): void {
        global $DB, $USER;

        $itemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $USER->id, 'name' => 'HTML attachment', 'type' => 'file',
        ]);
        $context = \context_user::instance($USER->id);
        foreach (['item_content_file', 'item_content_text', 'item_file'] as $area) {
            $block = $area === 'item_file' ? null : block_exaport_create_content_block(
                $itemid, $area === 'item_content_file' ? 'file' : 'text', 'HTML attachment'
            );
            $this->create_file('report.html', 'text/html', '<script>alert(1)</script><p>Safe</p>',
                $area, $block ? $block->id : $itemid);
            if ($area === 'item_file') {
                $args = ['portfolio', 'id', $USER->id, 'itemid', $itemid, 'report.html'];
            } else {
                $args = ['itemid', $itemid, 'blockid', $block->id, 'report.html'];
            }

            ob_start();
            try {
                block_exaport_pluginfile(null, null, $context, $area, $args, false, ['dontdie' => true]);
                $response = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            $this->assertStringContainsString('<p>Safe</p>', $response);
            $this->assertStringNotContainsString('<script', $response);
        }
    }
}
