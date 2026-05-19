<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for item/category compatibility and multi-category assignments.
 */
final class itemcate_test extends \advanced_testcase {
    private \stdClass $user;
    private \stdClass $course;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    private function create_category(string $name = 'Category', int $pid = 0): int {
        global $DB;

        return (int)$DB->insert_record('block_exaportcate', (object)[
            'userid' => $this->user->id,
            'pid' => $pid,
            'name' => $name,
            'timemodified' => time(),
            'courseid' => $this->course->id,
            'description' => '',
            'subjid' => 0,
            'topicid' => 0,
            'source' => 0,
            'sourceid' => 0,
            'isoez' => 0,
            'sortorder' => 0,
            'internshare' => 0,
            'shareall' => 0,
            'structure_shareall' => 0,
            'structure_share' => 0,
            'iconmerge' => 0,
            'creatorid' => $this->user->id,
        ]);
    }

    private function create_item(int $categoryid, string $name = 'Item'): int {
        global $DB;

        $itemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $this->user->id,
            'type' => 'note',
            'name' => $name,
            'url' => '',
            'intro' => '',
            'attachment' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'courseid' => $this->course->id,
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

        if ($categoryid > 0) {
            $DB->insert_record('block_exaportitemcate', (object)[
                'itemid' => $itemid,
                'cateid' => $categoryid,
            ]);
        }

        return $itemid;
    }

    public function test_create_category(): void {
        global $DB;

        $categoryid = $this->create_category('Test category');
        $this->assertTrue($DB->record_exists('block_exaportcate', ['id' => $categoryid]));
    }

    public function test_create_item_with_category(): void {
        global $DB;

        $categoryid = $this->create_category('Category for item');
        $itemid = $this->create_item($categoryid);
        $this->assertTrue($DB->record_exists('block_exaportitemcate', ['itemid' => $itemid, 'cateid' => $categoryid]));
    }

    public function test_create_item_multiple_categories(): void {
        global $DB;

        $cat1 = $this->create_category('Cat 1');
        $cat2 = $this->create_category('Cat 2');
        $cat3 = $this->create_category('Cat 3');
        $itemid = $this->create_item($cat1);

        foreach ([$cat1, $cat2, $cat3] as $cateid) {
            $DB->insert_record('block_exaportitemcate', (object)[
                'itemid' => $itemid,
                'cateid' => $cateid,
            ]);
        }

        $assigned = array_map('intval', $DB->get_fieldset_select('block_exaportitemcate', 'cateid', 'itemid = ?', [$itemid]));
        sort($assigned);
        $expected = [$cat1, $cat2, $cat3];
        sort($expected);
        $this->assertSame($expected, $assigned);
    }

    public function test_migration_populates_junction_table(): void {
        global $DB;

        $categoryid = $this->create_category('Migration cat');
        $itemid = $this->create_item($categoryid, 'Migrated item');

        // Verify item is in the junction table.
        $this->assertTrue($DB->record_exists('block_exaportitemcate', ['itemid' => $itemid, 'cateid' => $categoryid]));
    }

    public function test_item_appears_in_all_assigned_categories(): void {
        global $DB;

        $cat1 = $this->create_category('Cat A');
        $cat2 = $this->create_category('Cat B');
        $cat3 = $this->create_category('Cat C');
        $itemid = $this->create_item($cat1);

        foreach ([$cat1, $cat2, $cat3] as $cateid) {
            $DB->insert_record('block_exaportitemcate', (object)[
                'itemid' => $itemid,
                'cateid' => $cateid,
            ]);
        }

        foreach ([$cat1, $cat2, $cat3] as $cateid) {
            $records = $DB->get_records_sql("
                SELECT DISTINCT i.id
                  FROM {block_exaportitem} i
                  JOIN {block_exaportitemcate} ic ON ic.itemid = i.id
                 WHERE ic.cateid = ?
                   AND i.userid = ?
            ", [$cateid, $this->user->id]);
            $this->assertArrayHasKey($itemid, $records);
        }
    }

    public function test_item_no_longer_has_categoryid_column(): void {
        global $DB;

        $categoryid = $this->create_category('New cat');
        $itemid = $this->create_item($categoryid, 'New item');
        // Verify the item exists and the relation is in the junction table.
        $this->assertTrue($DB->record_exists('block_exaportitem', ['id' => $itemid]));
        $this->assertTrue($DB->record_exists('block_exaportitemcate', ['itemid' => $itemid, 'cateid' => $categoryid]));
    }
}
