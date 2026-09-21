<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/item_competence_helpers.php');
require_once(__DIR__ . '/../lib/item_content_helpers.php');

/**
 * Tests for item competence persistence helpers.
 *
 * @covers ::block_exaport_normalize_competenceids
 * @covers ::block_exaport_validate_competenceids
 */
final class item_competence_helpers_test extends \advanced_testcase {

    /**
     * Feature entry points which authorize edits to an item.
     *
     * @return array<string, array{0: string}>
     */
    public static function editable_item_entry_points_provider(): array {
        return [
            'content editor' => ['block_exaport_get_editable_content_item'],
            'competence picker' => ['block_exaport_get_editable_competence_item'],
        ];
    }

    /**
     * @dataProvider editable_item_entry_points_provider
     */
    public function test_editable_item_entry_point_rejects_wrong_owner(string $entrypoint): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $this->setUser($viewer);

        $this->assert_entry_point_error('bookmarknotfound', $entrypoint, $itemid, $course->id);
    }

    /**
     * @dataProvider editable_item_entry_points_provider
     */
    public function test_editable_item_entry_point_rejects_wrong_course(string $entrypoint): void {
        $this->resetAfterTest(true);
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $this->setUser($owner);

        $this->assert_entry_point_error('bookmarknotfound', $entrypoint, $itemid, $othercourse->id);
    }

    /**
     * @dataProvider editable_item_entry_points_provider
     */
    public function test_editable_item_entry_point_rejects_noneditable_item(string $entrypoint): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $CFG->block_exaport_app_alloweditdelete = false;
        $CFG->block_exaport_enable_interaction_competences = true;
        if (!block_exaport_check_competence_interaction()) {
            $this->markTestSkipped('Exacomp is required to create an item locked by teacher review.');
        }

        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $this->setUser($owner);
        $DB->insert_record(BLOCK_EXACOMP_DB_ITEM_MM, (object)[
            'itemid' => $itemid,
            'exampleid' => 1,
            'teachervalue' => 1,
        ]);

        $this->assert_entry_point_error('nopermissions', $entrypoint, $itemid, $course->id);
    }

    /**
     * @dataProvider editable_item_entry_points_provider
     */
    public function test_editable_item_entry_point_returns_item(string $entrypoint): void {
        global $CFG;

        $this->resetAfterTest(true);
        $CFG->block_exaport_app_alloweditdelete = true;
        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->insert_item($owner->id, $course->id);
        $this->setUser($owner);

        $item = $entrypoint($itemid, $course->id);

        $this->assertSame($itemid, (int)$item->id);
        $this->assertSame((int)$owner->id, (int)$item->userid);
        $this->assertSame((int)$course->id, (int)$item->courseid);
    }

    public function test_normalize_competenceids_keeps_unique_positive_ids(): void {
        $this->assertSame([4, 8], block_exaport_normalize_competenceids([4, '8', 4, 0, -2]));
    }

    public function test_validate_competenceids_accepts_available_ids(): void {
        $this->assertSame([4, 8], block_exaport_validate_competenceids([4, 8], [2, 4, 8]));
    }

    public function test_validate_competenceids_rejects_unavailable_ids(): void {
        $this->expectException(\invalid_parameter_exception::class);
        block_exaport_validate_competenceids([4, 99], [2, 4, 8]);
    }

    private function insert_item(int $userid, int $courseid): int {
        global $DB;

        return (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $userid,
            'type' => 'note',
            'categoryid' => 0,
            'name' => 'Authorization test item',
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

    private function assert_entry_point_error(
        string $errorcode,
        string $entrypoint,
        int $itemid,
        int $courseid
    ): void {
        try {
            $entrypoint($itemid, $courseid);
            $this->fail('Expected item authorization to fail with ' . $errorcode);
        } catch (\moodle_exception $exception) {
            $this->assertSame($errorcode, $exception->errorcode);
        }
    }
}
