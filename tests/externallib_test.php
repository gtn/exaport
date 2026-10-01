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
        $this->assertSame([], $response->files);
        $this->assertCount(1, $response->contentblocks);
        $this->assertSame('file', $response->contentblocks[0]->type);
        $this->assertSame('structured.pdf', $response->contentblocks[0]->files[0]['filename']);
        $this->assertStringNotContainsString('item_file', $response->contentblocks[0]->files[0]['url']);
        $this->assertStringContainsString('item_content_file', $response->contentblocks[0]->files[0]['url']);
    }
}
