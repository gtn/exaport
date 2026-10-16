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

use block_exaport\externallib\externallib;
use core_external\external_api;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for root and categorized items returned by the all-items web service.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class externallib_get_all_user_items_test extends \advanced_testcase {
    /**
     * Create a note without category relations.
     *
     * @param int $userid Owner ID.
     * @param string $name Item title.
     * @return int Item ID.
     */
    private function create_item(int $userid, string $name): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportitem', (object)[
            'userid' => $userid,
            'name' => $name,
            'type' => 'note',
            'url' => '',
            'intro' => '<p>Synthetic API test</p>',
            'attachment' => '',
        ]);
    }

    /**
     * Call the public service and validate its declared response structure.
     *
     * @return array Categories keyed by category ID.
     */
    private function get_categories(): array {
        $result = external_api::clean_returnvalue(
            externallib::get_all_user_items_returns(),
            externallib::get_all_user_items()
        );
        return array_column($result, null, 'id');
    }

    /** Root contains all and only the current user's uncategorized items. */
    public function test_root_items_are_returned_without_leaking_other_owners(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($owner);

        $first = $this->create_item($owner->id, 'Root one');
        $second = $this->create_item($owner->id, 'Root two');
        $this->create_item($other->id, 'Private root');
        $category = (int)$DB->insert_record('block_exaportcate', (object)[
            'userid' => $owner->id, 'name' => 'Category', 'pid' => 0,
        ]);
        $categorized = $this->create_item($owner->id, 'Categorized');
        $DB->insert_record('block_exaportitemcate', (object)['itemid' => $categorized, 'cateid' => $category]);
        // Even a foreign item linked to our category must not be returned.
        $foreign = $this->create_item($other->id, 'Private categorized');
        $DB->insert_record('block_exaportitemcate', (object)['itemid' => $foreign, 'cateid' => $category]);

        $categories = $this->get_categories();
        $this->assertEqualsCanonicalizing([$first, $second], array_column($categories[0]['items'], 'id'));
        $this->assertSame([$categorized], array_column($categories[$category]['items'], 'id'));
        $this->assertSame('Root one', array_column($categories[0]['items'], null, 'id')[$first]['name']);
    }

    /** Nested/multiple assignments exclude an item from root until the last is removed. */
    public function test_root_membership_follows_category_relations(): void {
        global $DB;
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $parent = (int)$DB->insert_record('block_exaportcate', (object)[
            'userid' => $owner->id, 'name' => 'Parent', 'pid' => 0,
        ]);
        $child = (int)$DB->insert_record('block_exaportcate', (object)[
            'userid' => $owner->id, 'name' => 'Child', 'pid' => $parent,
        ]);
        $item = $this->create_item($owner->id, 'Move between root and categories');
        foreach ([$parent, $child] as $category) {
            $DB->insert_record('block_exaportitemcate', (object)['itemid' => $item, 'cateid' => $category]);
        }
        $categories = $this->get_categories();
        $this->assertSame([], $categories[0]['items']);
        $this->assertSame([$item], array_column($categories[$parent]['items'], 'id'));
        $this->assertSame([$item], array_column($categories[$child]['items'], 'id'));

        $DB->delete_records('block_exaportitemcate', ['itemid' => $item, 'cateid' => $parent]);
        $this->assertSame([], $this->get_categories()[0]['items']);
        $DB->delete_records('block_exaportitemcate', ['itemid' => $item]);
        $this->assertSame([$item], array_column($this->get_categories()[0]['items'], 'id'));
    }

    /** A user with no items still gets the empty root category. */
    public function test_empty_portfolio_returns_empty_root(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_item($other->id, 'Someone else\'s root item');
        $this->setUser($owner);
        $categories = $this->get_categories();
        $this->assertSame([0], array_keys($categories));
        $this->assertSame([], $categories[0]['items']);
    }
}
