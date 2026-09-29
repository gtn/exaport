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

/**
 * Tests the additive WordPress structured item payload.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wp_integration_export_test extends \advanced_testcase {

    public function test_payload_preserves_legacy_fields_and_ordered_structured_blocks(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $item = (object)[
            'userid' => $owner->id, 'type' => 'note', 'categoryid' => 0, 'name' => 'Mixed item',
            'url' => 'https://legacy.example/', 'intro' => '<p>Legacy intro</p>', 'attachment' => '',
            'timecreated' => time(), 'timemodified' => time(), 'courseid' => 0, 'shareall' => 0,
            'externaccess' => 0, 'externcomment' => 0, 'sortorder' => 0, 'isoez' => 0,
            'langid' => 0, 'source' => 0, 'sourceid' => 0, 'iseditable' => 1, 'parentid' => 0,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        $item->link = $item->url;
        $linkid = $this->create_block($item->id, 'link', 1, 'Link', '', 'https://structured.example/');
        $textid = $this->create_block($item->id, 'text', 2, 'Text', '<p>Structured text</p>');
        $fileid = $this->create_block($item->id, 'file', 3, 'Files');
        $contextid = \context_user::instance($owner->id)->id;
        $legacyfile = get_file_storage()->create_file_from_string([
            'contextid' => $contextid, 'component' => 'block_exaport', 'filearea' => 'item_file',
            'itemid' => $item->id, 'filepath' => '/', 'filename' => 'structured.pdf',
        ], 'legacy file with matching name');
        $structuredfile = get_file_storage()->create_file_from_string([
            'contextid' => $contextid, 'component' => 'block_exaport', 'filearea' => 'item_content_file',
            'itemid' => $fileid, 'filepath' => '/', 'filename' => 'structured.pdf',
        ], 'structured file');
        $editorfile = get_file_storage()->create_file_from_string([
            'contextid' => $contextid, 'component' => 'block_exaport', 'filearea' => 'item_content_text',
            'itemid' => $textid, 'filepath' => '/images/', 'filename' => 'editor.png',
        ], 'editor file');

        $integration = new wp_integration(0, 'test-passphrase');
        $blockdata = [];
        $payload = $integration->getViewBlockTypeContent((object)['type' => 'item', 'item' => $item], $blockdata);

        $this->assertSame('Mixed item', $payload['name']);
        $this->assertSame('<p>Legacy intro</p>', $payload['content']);
        $this->assertSame('https://legacy.example/', $payload['link']);
        $this->assertSame([$legacyfile->get_contenthash()], $payload['files']);
        $this->assertSame(['link', 'text', 'file'], array_column($payload['structured_blocks'], 'type'));
        $this->assertSame([$linkid, $textid, $fileid], array_column($payload['structured_blocks'], 'id'));
        $this->assertSame('https://structured.example/', $payload['structured_blocks'][0]['url']);
        $this->assertStringContainsString('Structured text', $payload['structured_blocks'][1]['content']);
        $this->assertSame('images/editor.png', $payload['structured_blocks'][1]['editorfiles'][0]['reference']);
        $this->assertSame($editorfile->get_contenthash(),
            $payload['structured_blocks'][1]['editorfiles'][0]['file']);
        $this->assertSame([$structuredfile->get_contenthash()], $payload['structured_blocks'][2]['files']);
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
}
