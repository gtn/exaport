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
require_once($CFG->dirroot . '/blocks/exaport/lib.php');
require_once($CFG->dirroot . '/blocks/exaport/lib/item_content_helpers.php');
require_once($CFG->dirroot . '/blocks/exaport/tests/fixtures/exaport_test_helpers_trait.php');

/**
 * Exercises structured file identity and authorized file resolution.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_content_file_access_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    /** Create a minimal parent item record. */
    private function create_item(int $userid, int $courseid = 0): \stdClass {
        global $DB;
        $item = (object)[
            'userid' => $userid,
            'type' => 'note',
            'categoryid' => 0,
            'name' => 'File access test',
            'url' => '',
            'intro' => '',
            'attachment' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'courseid' => $courseid,
        ];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    /** Create a stored file for a block. */
    private function create_file(int $userid, int $blockid, string $filepath, string $filename): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'block_exaport',
            'filearea' => 'item_content_file',
            'itemid' => $blockid,
            'filepath' => $filepath,
            'filename' => $filename,
        ], 'file bytes');
    }

    public function test_legacy_file_selector_is_exact_and_ambiguous_requests_fail_closed(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner->id);
        $firstblock = \block_exaport_create_file_content_block($item->id, 'First');
        $secondblock = \block_exaport_create_file_content_block($item->id, 'Second');
        $first = $this->create_file($owner->id, $firstblock->id, '/nested/', 'first.txt');
        $second = $this->create_file($owner->id, $secondblock->id, '/', 'second.txt');

        $this->assertSame((int)$item->id, (int)$firstblock->itemid);
        $this->assertSame((int)$firstblock->id, (int)$first->get_itemid());
        $this->assertSame($first->get_id(), block_exaport_resolve_legacy_item_file($item, (string)$first->get_id())->get_id());
        $this->assertFalse(block_exaport_resolve_legacy_item_file($item, '1'));
        $this->assertFalse(block_exaport_resolve_legacy_item_file($item));

        $second->delete();
        $resolved = block_exaport_resolve_legacy_item_file($item);
        $this->assertSame($first->get_id(), $resolved->get_id());
        $this->assertSame('/nested/', $resolved->get_filepath());
    }

    public function test_owner_can_resolve_nested_file_but_unrelated_user_cannot(): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner->id);
        $block = \block_exaport_create_file_content_block($item->id, 'Nested');
        $file = $this->create_file($owner->id, $block->id, '/deep/path/', 'evidence.txt');
        $parsed = block_exaport_parse_item_content_file_args([
            'itemid', (string)$item->id,
            'blockid', (string)$block->id,
            'deep', 'path', 'evidence.txt',
        ]);

        $this->assertSame((int)$item->id, $parsed['itemid']);
        $this->assertSame((int)$block->id, $parsed['blockid']);
        $this->assertSame('/deep/path/', $parsed['filepath']);

        $this->setUser($owner);
        $authorized = block_exaport_get_item_content_file_for_access($parsed);
        $this->assertSame($file->get_id(), $authorized->get_id());
        $this->assertSame('file bytes', $authorized->get_content());

        $this->setUser($other);
        $this->assertFalse(block_exaport_get_item_content_file_for_access($parsed));
    }

    public function test_private_view_pdf_file_request_uses_validated_view_user_context(): void {
        global $DB;

        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $item = $this->create_item($owner->id);
        $fileblock = \block_exaport_create_file_content_block($item->id, 'Evidence');
        $file = $this->create_file($owner->id, $fileblock->id, '/proof/', 'proof.png');
        $viewid = $this->create_view($owner);
        $DB->insert_record('block_exaportviewshar', (object)[
            'viewid' => $viewid,
            'userid' => $recipient->id,
            'notify' => 0,
        ]);
        $DB->insert_record('block_exaportviewblock', (object)[
            'viewid' => $viewid,
            'type' => 'item',
            'itemid' => $item->id,
        ]);

        $this->setUser($recipient);
        $access = 'view/id/' . $owner->id . '-' . $viewid;
        $parsed = block_exaport_parse_item_content_file_args([
            'view', 'id', $owner->id . '-' . $viewid,
            'itemid', (string)$item->id,
            'blockid', (string)$fileblock->id,
            'proof.png',
        ]);

        $resolved = block_exaport_get_item_content_file_for_access($parsed, true, (int)$recipient->id);
        $this->assertSame($access, $parsed['access']);
        $this->assertSame($file->get_id(), $resolved->get_id());
        $this->assertSame('proof.png', $resolved->get_filename());
    }
}
