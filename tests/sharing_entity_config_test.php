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
require_once($CFG->dirroot . '/blocks/exaport/lib/sharelib.php');
require_once($CFG->dirroot . '/blocks/exaport/tests/fixtures/exaport_test_helpers_trait.php');

/**
 * Tests for the shared sharing helpers used by the userlist/grouplist AJAX endpoints of
 * category.php, views_mod.php and item.php:
 *   - block_exaport_get_sharing_entity_config()
 *   - block_exaport_sharing_owned_entity_id()
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharing_entity_config_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    protected function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Every supported entity type maps to its own share tables.
     */
    public function test_config_contains_all_entity_types(): void {
        $expected = [
            'view' => ['block_exaportview', 'viewid', 'block_exaportviewshar', 'block_exaportviewgroupshar'],
            'category' => ['block_exaportcate', 'catid', 'block_exaportcatshar', 'block_exaportcatgroupshar'],
            'item' => ['block_exaportitem', 'itemid', 'block_exaportitemshar', 'block_exaportitemgroupshar'],
        ];

        foreach ($expected as $entitytype => $tables) {
            $config = block_exaport_get_sharing_entity_config($entitytype);
            $this->assertSame($tables[0], $config->entitytable);
            $this->assertSame($tables[1], $config->idfield);
            $this->assertSame($tables[2], $config->usersharetable);
            $this->assertSame($tables[3], $config->groupsharetable);
        }

        // Only collections list users the entity is shared to but who are outside the owner's courses.
        $this->assertTrue(block_exaport_get_sharing_entity_config('view')->extrausers);
        $this->assertFalse(block_exaport_get_sharing_entity_config('category')->extrausers);
        $this->assertFalse(block_exaport_get_sharing_entity_config('item')->extrausers);
    }

    /**
     * An unknown entity type is a programming error.
     */
    public function test_unknown_entity_type_throws(): void {
        $this->expectException(\coding_exception::class);
        block_exaport_get_sharing_entity_config('unknown');
    }

    /**
     * The owner gets the real id, everybody else gets 0 so that no share state is exposed.
     */
    public function test_ownership_check(): void {
        global $USER;

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $categoryid = $this->create_category($owner);
        $config = block_exaport_get_sharing_entity_config('category');

        $USER = $owner;
        $this->assertSame($categoryid, block_exaport_sharing_owned_entity_id($config, $categoryid));

        $USER = $other;
        $this->assertSame(0, block_exaport_sharing_owned_entity_id($config, $categoryid));

        // Not yet saved entities and non existing ids are treated the same way.
        $USER = $owner;
        $this->assertSame(0, block_exaport_sharing_owned_entity_id($config, 0));
        $this->assertSame(0, block_exaport_sharing_owned_entity_id($config, $categoryid + 1000));
    }
}
