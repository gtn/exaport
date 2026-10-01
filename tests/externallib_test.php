<?php
// This file is part of Exabis Eportfolio (extension for Moodle)

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/item_content_helpers.php');

/**
 * Tests the structured-content external response projection.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class externallib_test extends \advanced_testcase {

    /** Create a stored structured or residual file. */
    private function create_file(int $userid, string $area, int $itemid, string $path, string $name): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'block_exaport',
            'filearea' => $area,
            'itemid' => $itemid,
            'filepath' => $path,
            'filename' => $name,
        ], $name);
    }

    public function test_response_ignores_residual_parent_content_and_returns_structured_content(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $item = (object)[
            'userid' => $owner->id, 'type' => 'file', 'categoryid' => 0, 'name' => 'Mixed item',
            'url' => 'https://legacy.example/', 'intro' => 'Description', 'attachment' => 'legacy.pdf',
            'timecreated' => time(), 'timemodified' => time(), 'courseid' => 0,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        $block = block_exaport_create_file_content_block($item->id, 'Structured file');
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id, 'component' => 'block_exaport',
            'filearea' => 'item_file', 'itemid' => $item->id, 'filepath' => '/', 'filename' => 'legacy.pdf',
        ], 'legacy');
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($owner->id)->id, 'component' => 'block_exaport',
            'filearea' => 'item_content_file', 'itemid' => $block->id, 'filepath' => '/',
            'filename' => 'structured.pdf',
        ], 'structured');

        $method = new \ReflectionMethod(externallib::class, 'make_item_result');
        $response = $method->invoke(null, $item);

        $this->assertSame('', $response->url);
        $this->assertSame('structured.pdf', $response->files[0]['filename']);
        $this->assertCount(1, $response->contentblocks);
        $this->assertSame('file', $response->contentblocks[0]->type);
        $this->assertSame('structured.pdf', $response->contentblocks[0]->files[0]['filename']);
        $this->assertStringNotContainsString('item_file', $response->contentblocks[0]->files[0]['url']);
        $this->assertStringContainsString('item_content_file', $response->contentblocks[0]->files[0]['url']);
    }

    public function test_webservice_projection_preserves_order_and_uses_structured_content_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $item = (object)[
            'userid' => $owner->id, 'type' => 'note', 'categoryid' => 0, 'name' => 'Ordered item',
            'url' => 'https://legacy.example/', 'intro' => 'Description', 'attachment' => 'legacy.txt',
            'timecreated' => time(), 'timemodified' => time(), 'courseid' => 0,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        $emptylink = block_exaport_create_link_content_block($item->id, 'Empty', '', ['sortorder' => 1]);
        $firstlink = block_exaport_create_link_content_block(
            $item->id, 'First', 'https://first.example/', ['sortorder' => 2]
        );
        $secondlink = block_exaport_create_link_content_block(
            $item->id, 'Second', 'https://second.example/', ['sortorder' => 3]
        );
        block_exaport_create_content_block($item->id, 'text', 'Text', '', [
            'sortorder' => 4, 'content' => 'Structured text', 'contentformat' => FORMAT_PLAIN,
        ]);
        $filesone = block_exaport_create_file_content_block($item->id, 'Files one', ['sortorder' => 5]);
        $filestwo = block_exaport_create_file_content_block($item->id, 'Files two', ['sortorder' => 6]);
        $this->create_file($owner->id, 'item_content_file', $filesone->id, '/z/', 'b.txt');
        $this->create_file($owner->id, 'item_content_file', $filesone->id, '/a/', 'z.txt');
        $this->create_file($owner->id, 'item_content_file', $filestwo->id, '/', 'c.txt');
        $this->create_file($owner->id, 'item_file', $item->id, '/', 'legacy.txt');

        $response = block_exaport_get_item_content_webservice_data($item, 'token123');

        $this->assertSame('https://first.example/', $response['url']);
        $this->assertSame([$emptylink->id, $firstlink->id, $secondlink->id], array_map(
            static fn($block) => $block->id,
            array_slice($response['contentblocks'], 0, 3)
        ));
        $this->assertSame(['https://first.example/', 'https://second.example/'], [
            $response['contentblocks'][1]->url, $response['contentblocks'][2]->url,
        ]);
        $this->assertSame('Structured text', $response['contentblocks'][3]->content);
        $this->assertSame(['z.txt', 'b.txt', 'c.txt'], array_column($response['files'], 'filename'));
        $this->assertSame($response['contentblocks'][4]->files[0], $response['files'][0]);
        foreach ($response['files'] as $index => $file) {
            $blockid = $index < 2 ? $filesone->id : $filestwo->id;
            $this->assertStringContainsString('/webservice/pluginfile.php/', $file['url']);
            $this->assertStringContainsString('/item_content_file/itemid/' . $item->id .
                '/blockid/' . $blockid . '/', $file['url']);
            $this->assertStringEndsWith('?token=token123', $file['url']);
            $this->assertStringNotContainsString('portfoliofile.php', $file['url']);
            $this->assertStringNotContainsString('/item_file/', $file['url']);
        }
        $this->assertNotContains('legacy.txt', array_column($response['files'], 'filename'));
        $this->assertNotSame($item->url, $response['url']);
    }

    public function test_text_only_projection_has_no_fabricated_legacy_values(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $item = (object)['userid' => $owner->id, 'type' => 'note', 'categoryid' => 0, 'name' => 'Text',
            'url' => 'https://legacy.example/', 'intro' => '', 'attachment' => 'legacy.txt',
            'timecreated' => time(), 'timemodified' => time(), 'courseid' => 0];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        block_exaport_create_content_block($item->id, 'text', '', '', ['content' => 'Only text']);

        $response = block_exaport_get_item_content_webservice_data($item);
        $this->assertSame('', $response['url']);
        $this->assertSame([], $response['files']);
        $this->assertSame('Only text', $response['contentblocks'][0]->content);
    }
}
