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

    /**
     * The registry knows where the sharing settings of each entity type are edited and which
     * column enables internal sharing, used by block_exaport_sharing_user_search_page().
     */
    public function test_config_contains_search_page_settings(): void {
        $view = block_exaport_get_sharing_entity_config('view');
        $this->assertSame('internaccess', $view->internaccessfield);
        $this->assertSame('/blocks/exaport/views_mod.php', $view->editpage);
        $this->assertSame(['type' => 'share', 'action' => 'edit'], $view->editparams);

        $category = block_exaport_get_sharing_entity_config('category');
        $this->assertSame('internshare', $category->internaccessfield);
        $this->assertSame('/blocks/exaport/category.php', $category->editpage);
        $this->assertSame(['action' => 'edit'], $category->editparams);

        // Items have no internal access flag at all.
        $this->assertNull(block_exaport_get_sharing_entity_config('item')->internaccessfield);
    }

    /**
     * The shared user-search page is restricted to users with the internal share capability.
     */
    public function test_search_page_requires_shareintern_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);

        $_GET['courseid'] = $course->id;
        $_GET['id'] = 0;

        $this->expectException(\required_capability_exception::class);
        block_exaport_sharing_user_search_page('category');
    }

    /**
     * Toggling direct shares adds/removes rows and enables internal sharing for the entity.
     */
    public function test_toggle_shared_users(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $thirduser = $this->getDataGenerator()->create_user();
        $categoryid = $this->create_category($owner, 'Cat', 0, 0, 1);
        $config = block_exaport_get_sharing_entity_config('category');

        // Share to one user.
        block_exaport_sharing_toggle_shared_users($config, $categoryid, [$otheruser->id => $otheruser->id]);
        $this->assertTrue($DB->record_exists('block_exaportcatshar',
            ['catid' => $categoryid, 'userid' => $otheruser->id]));

        // Sharing to single users enables internal sharing and disables "share to all".
        $category = $DB->get_record('block_exaportcate', ['id' => $categoryid]);
        $this->assertEquals(1, $category->internshare);
        $this->assertEquals(0, $category->shareall);

        // Unchecking removes the share again, unknown users are never inserted.
        block_exaport_sharing_toggle_shared_users($config, $categoryid,
            [$otheruser->id => 0, $thirduser->id => $thirduser->id, ($thirduser->id + 1000) => 1]);
        $this->assertFalse($DB->record_exists('block_exaportcatshar',
            ['catid' => $categoryid, 'userid' => $otheruser->id]));
        $this->assertTrue($DB->record_exists('block_exaportcatshar',
            ['catid' => $categoryid, 'userid' => $thirduser->id]));
        $this->assertCount(1, $DB->get_records('block_exaportcatshar', ['catid' => $categoryid]));
    }

    /**
     * The same helper works for collections, which use different table/column names.
     */
    public function test_toggle_shared_users_for_views(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($owner, 1);
        $config = block_exaport_get_sharing_entity_config('view');

        block_exaport_sharing_toggle_shared_users($config, $viewid, [$otheruser->id => $otheruser->id]);

        $this->assertTrue($DB->record_exists('block_exaportviewshar',
            ['viewid' => $viewid, 'userid' => $otheruser->id]));
        $view = $DB->get_record('block_exaportview', ['id' => $viewid]);
        $this->assertEquals(1, $view->internaccess);
        $this->assertEquals(0, $view->shareall);
    }
}
