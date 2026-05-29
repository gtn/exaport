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

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');

/**
 * Abstract functional tests for core exaport library functions.
 *
 * These tests exercise high-level API functions like
 * block_exaport_get_items_by_category_and_user() and are designed to
 * work both before and after internal refactoring (e.g. category-to-tag migration).
 *
 * @package    block_exaport
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_functions_test extends \advanced_testcase {
    private \stdClass $user;
    private \stdClass $course;

    protected function setUp(): void {
        global $USER;
        $this->resetAfterTest(true);
        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        // Set the global user so functions relying on $USER work.
        $this->setUser($this->user);
    }

    /**
     * Helper: create a category for the test user.
     */
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

    /**
     * Helper: create an item assigned to a category via the junction table.
     */
    private function create_item(int $categoryid, string $name = 'Item'): int {
        global $DB;

        $itemid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $this->user->id,
            'type' => 'note',
            'categoryid' => 0, // Legacy field; real mapping is via block_exaportitemcate.
            'name' => $name,
            'url' => '',
            'intro' => 'Some content',
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

    /**
     * Helper: assign an item to an additional category.
     */
    private function assign_item_to_category(int $itemid, int $categoryid): void {
        global $DB;
        if (!$DB->record_exists('block_exaportitemcate', ['itemid' => $itemid, 'cateid' => $categoryid])) {
            $DB->insert_record('block_exaportitemcate', (object)[
                'itemid' => $itemid,
                'cateid' => $categoryid,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Tests for block_exaport_get_items_by_category_and_user()
    // -------------------------------------------------------------------------

    /**
     * Test: retrieving items for a specific category returns only items in that category.
     */
    public function test_get_items_returns_items_in_category(): void {
        $cat1 = $this->create_category('Wildtiere');
        $cat2 = $this->create_category('Haustiere');

        $item1 = $this->create_item($cat1, 'Fuchs');
        $item2 = $this->create_item($cat2, 'Katze');

        $items = block_exaport_get_items_by_category_and_user($this->user->id, $cat1);

        $this->assertArrayHasKey($item1, $items);
        $this->assertArrayNotHasKey($item2, $items);
    }

    /**
     * Test: retrieving items for a category with subcategories included
     * (e.g. "wildtiere" should also show items in "wildtiere/hund").
     */
    public function test_get_items_includes_subcategories(): void {
        $parent = $this->create_category('Wildtiere');
        $child = $this->create_category('Hund', $parent);

        $itemParent = $this->create_item($parent, 'Wolf');
        $itemChild = $this->create_item($child, 'Wildtier Hund');

        // Without subcategories - only parent items.
        $items = block_exaport_get_items_by_category_and_user($this->user->id, $parent);
        $this->assertArrayHasKey($itemParent, $items);
        $this->assertArrayNotHasKey($itemChild, $items);

        // With subcategories - should include child items.
        $items = block_exaport_get_items_by_category_and_user($this->user->id, $parent, '', false, [$child]);
        $this->assertArrayHasKey($itemParent, $items);
        $this->assertArrayHasKey($itemChild, $items);
    }

    /**
     * Test: deeply nested subcategories are included when passed.
     */
    public function test_get_items_includes_deeply_nested_subcategories(): void {
        $top = $this->create_category('Tiere');
        $mid = $this->create_category('Wildtiere', $top);
        $leaf = $this->create_category('Hund', $mid);

        $itemTop = $this->create_item($top, 'Tier allgemein');
        $itemMid = $this->create_item($mid, 'Wildtier');
        $itemLeaf = $this->create_item($leaf, 'Wildhund');

        // Include all descendants.
        $items = block_exaport_get_items_by_category_and_user($this->user->id, $top, '', false, [$mid, $leaf]);
        $this->assertArrayHasKey($itemTop, $items);
        $this->assertArrayHasKey($itemMid, $items);
        $this->assertArrayHasKey($itemLeaf, $items);
    }

    /**
     * Test: uncategorized items (categoryid=0) returns items with no category assignment.
     */
    public function test_get_items_uncategorized(): void {
        $cat = $this->create_category('Some category');
        $itemWithCat = $this->create_item($cat, 'Categorized item');
        $itemWithout = $this->create_item(0, 'Uncategorized item');

        $items = block_exaport_get_items_by_category_and_user($this->user->id, 0);
        $this->assertArrayHasKey($itemWithout, $items);
        $this->assertArrayNotHasKey($itemWithCat, $items);
    }

    /**
     * Test: an item assigned to multiple categories appears when querying any of them.
     */
    public function test_item_in_multiple_categories_found_in_each(): void {
        $cat1 = $this->create_category('Wildtiere');
        $cat2 = $this->create_category('Haustiere');
        $cat3 = $this->create_category('Lieblinge');

        $item = $this->create_item($cat1, 'Multitier');
        $this->assign_item_to_category($item, $cat2);
        $this->assign_item_to_category($item, $cat3);

        // Item should appear in all three categories.
        foreach ([$cat1, $cat2, $cat3] as $catid) {
            $items = block_exaport_get_items_by_category_and_user($this->user->id, $catid);
            $this->assertArrayHasKey($item, $items, "Item should appear in category $catid");
        }
    }

    /**
     * Test: items from other users are not returned.
     */
    public function test_get_items_only_own_user(): void {
        $otheruser = $this->getDataGenerator()->create_user();

        $cat = $this->create_category('Shared cat');
        $myitem = $this->create_item($cat, 'My item');

        // Create item for other user in same category.
        global $DB;
        $otherid = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $otheruser->id,
            'type' => 'note',
            'categoryid' => 0, // Legacy field; real mapping is via block_exaportitemcate.
            'name' => 'Other item',
            'url' => '',
            'intro' => 'Other content',
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
        $DB->insert_record('block_exaportitemcate', (object)[
            'itemid' => $otherid,
            'cateid' => $cat,
        ]);

        $items = block_exaport_get_items_by_category_and_user($this->user->id, $cat);
        $this->assertArrayHasKey($myitem, $items);
        $this->assertArrayNotHasKey($otherid, $items);
    }

    /**
     * Test: empty category returns no items.
     */
    public function test_get_items_empty_category(): void {
        $cat = $this->create_category('Empty');

        $items = block_exaport_get_items_by_category_and_user($this->user->id, $cat);
        $this->assertEmpty($items);
    }

    /**
     * Test: passing null as categoryid returns all items regardless of category.
     */
    public function test_get_items_all_with_null_category(): void {
        $cat1 = $this->create_category('Cat A');
        $cat2 = $this->create_category('Cat B');

        $item1 = $this->create_item($cat1, 'Item in cat1');
        $item2 = $this->create_item($cat2, 'Item in cat2');
        $item3 = $this->create_item(0, 'Uncategorized item');

        // null means "all items" - should return everything.
        $items = block_exaport_get_items_by_category_and_user($this->user->id, null);
        $this->assertArrayHasKey($item1, $items);
        $this->assertArrayHasKey($item2, $items);
        $this->assertArrayHasKey($item3, $items);
    }

    /**
     * Test: passing an array of category ids returns own-user items in any of these categories.
     */
    public function test_get_items_with_category_array(): void {
        $cat1 = $this->create_category('Cat A');
        $cat2 = $this->create_category('Cat B');
        $cat3 = $this->create_category('Cat C');

        $item1 = $this->create_item($cat1, 'Item in cat1');
        $item2 = $this->create_item($cat2, 'Item in cat2');
        $item3 = $this->create_item($cat3, 'Item in cat3');

        $items = block_exaport_get_items_by_category_and_user($this->user->id, [$cat1, $cat2]);
        $this->assertArrayHasKey($item1, $items);
        $this->assertArrayHasKey($item2, $items);
        $this->assertArrayNotHasKey($item3, $items);
    }

    /**
     * Test: withShared + category array includes matching items from other users.
     */
    public function test_get_items_with_shared_and_category_array(): void {
        global $DB;

        $otheruser = $this->getDataGenerator()->create_user();
        $cat = $this->create_category('Shared cat');
        $myitem = $this->create_item($cat, 'My item');

        $otheritem = (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $otheruser->id,
            'type' => 'note',
            'categoryid' => 0,
            'name' => 'Other item',
            'url' => '',
            'intro' => 'Other content',
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
        $DB->insert_record('block_exaportitemcate', (object)[
            'itemid' => $otheritem,
            'cateid' => $cat,
        ]);

        $items = block_exaport_get_items_by_category_and_user($this->user->id, [$cat], '', true);
        $this->assertArrayHasKey($myitem, $items);
        $this->assertArrayHasKey($otheritem, $items);
    }
}
