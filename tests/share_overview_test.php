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
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Tests for share_overview::build_share_info() across item, category and view entity types.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class share_overview_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    /** @var \stdClass Owner user. */
    private \stdClass $owner;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        $this->assertTrue(
            get_string_manager()->string_exists('share_tooltip_users', 'block_exaport'),
            'Language strings missing — run admin/tool/phpunit/cli/init.php'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Insert an item owned by the given user and return its id.
     *
     * @param \stdClass $user
     * @param int       $shareall
     * @param int       $externaccess
     * @return int
     */
    private function create_item(\stdClass $user, int $shareall = 0, int $externaccess = 0): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportitem', (object)[
            'userid'        => $user->id,
            'name'          => 'Test item',
            'type'          => 'note',
            'intro'         => '',
            'timemodified'  => time(),
            'shareall'      => $shareall,
            'externaccess'  => $externaccess,
            'courseid'      => 0,
        ]);
    }

    private function share_item_with_user(int $itemid, int $userid): void {
        global $DB;
        $DB->insert_record('block_exaportitemshar', (object)[
            'itemid' => $itemid,
            'userid' => $userid,
        ]);
    }

    private function share_item_with_cohort(int $itemid, int $cohortid): void {
        global $DB;
        $DB->insert_record('block_exaportitemgroupshar', (object)[
            'itemid'  => $itemid,
            'groupid' => $cohortid,
        ]);
    }

    private function share_view_with_user(int $viewid, int $userid): void {
        global $DB;
        $DB->insert_record('block_exaportviewshar', (object)[
            'viewid' => $viewid,
            'userid' => $userid,
        ]);
    }

    private function share_view_with_cohort(int $viewid, int $cohortid): void {
        global $DB;
        $DB->insert_record('block_exaportviewgroupshar', (object)[
            'viewid'  => $viewid,
            'groupid' => $cohortid,
        ]);
    }

    private function share_category_with_user(int $categoryid, int $userid): void {
        global $DB;
        $DB->insert_record('block_exaportcatshar', (object)[
            'catid' => $categoryid,
            'userid' => $userid,
        ]);
    }

    private function share_category_with_cohort(int $categoryid, int $cohortid): void {
        global $DB;
        $DB->insert_record('block_exaportcatgroupshar', (object)[
            'catid' => $categoryid,
            'groupid' => $cohortid,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests: share_overview::build_share_info — items
    // -------------------------------------------------------------------------

    /**
     * Item shareall flag → share->all === true, no users/groups.
     */
    public function test_item_shareall(): void {
        $itemid = $this->create_item($this->owner, 1);
        $row = (object)['shareall' => 1, 'externaccess' => 0];

        $share = share_overview::build_share_info('item', $itemid, $row);

        $this->assertTrue($share->all);
        $this->assertEmpty($share->users);
        $this->assertEmpty($share->groups);
        $this->assertFalse($share->external);
    }

    /**
     * Item-level share-all audience is also authorized by the shared-item access check.
     */
    public function test_item_shareall_grants_shared_item_access(): void {
        global $USER;

        $recipient = $this->getDataGenerator()->create_user();
        $USER = $recipient;
        $itemid = $this->create_item($this->owner, 1);

        $this->assertSame(
            (int)$this->owner->id,
            (int)block_exaport_can_user_access_shared_item($recipient->id, $itemid)
        );
    }

    /**
     * Item externaccess flag → share->external === true.
     */
    public function test_item_externaccess(): void {
        $itemid = $this->create_item($this->owner, 0, 1);
        $row = (object)['shareall' => 0, 'externaccess' => 1];

        $share = share_overview::build_share_info('item', $itemid, $row);

        $this->assertTrue($share->external);
        $this->assertFalse($share->all);
    }

    /**
     * Item shared with individual user → share->users populated with full name.
     */
    public function test_item_shared_with_user(): void {
        $user1  = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Test']);
        $itemid = $this->create_item($this->owner);
        $this->share_item_with_user($itemid, $user1->id);
        $row = (object)['shareall' => 0, 'externaccess' => 0];

        $share = share_overview::build_share_info('item', $itemid, $row);

        $this->assertNotEmpty($share->users);
        $this->assertEmpty($share->groups);
        $this->assertFalse($share->all);
    }

    /**
     * Item shared with cohort → share->groups populated with cohort name.
     */
    public function test_item_shared_with_cohort(): void {
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'ItemCohort']);
        $itemid = $this->create_item($this->owner);
        $this->share_item_with_cohort($itemid, $cohort->id);
        $row = (object)['shareall' => 0, 'externaccess' => 0];

        $share = share_overview::build_share_info('item', $itemid, $row);

        $this->assertNotEmpty($share->groups);
        $this->assertContains('ItemCohort', $share->groups);
        $this->assertEmpty($share->users);
        $this->assertFalse($share->all);
    }

    /**
     * Item shared with both user and cohort → both arrays populated.
     */
    public function test_item_shared_with_user_and_cohort(): void {
        $user1  = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Test']);
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'BothCohort']);
        $itemid = $this->create_item($this->owner);
        $this->share_item_with_user($itemid, $user1->id);
        $this->share_item_with_cohort($itemid, $cohort->id);
        $row = (object)['shareall' => 0, 'externaccess' => 0];

        $share = share_overview::build_share_info('item', $itemid, $row);

        $this->assertNotEmpty($share->users);
        $this->assertNotEmpty($share->groups);
    }

    // -------------------------------------------------------------------------
    // Tests: share_overview::build_share_info — views
    // -------------------------------------------------------------------------

    /**
     * View shareall flag → share->all === true.
     */
    public function test_view_shareall(): void {
        $viewid = $this->create_view($this->owner, 1);
        $row = (object)['shareall' => 1, 'externaccess' => 0];

        $share = share_overview::build_share_info('view', $viewid, $row);

        $this->assertTrue($share->all);
        $this->assertEmpty($share->users);
        $this->assertEmpty($share->groups);
    }

    /**
     * View shared with user → share->users populated.
     */
    public function test_view_shared_with_user(): void {
        $user1  = $this->getDataGenerator()->create_user(['firstname' => 'Carol', 'lastname' => 'Test']);
        $viewid = $this->create_view($this->owner);
        $this->share_view_with_user($viewid, $user1->id);
        $row = (object)['shareall' => 0, 'externaccess' => 0];

        $share = share_overview::build_share_info('view', $viewid, $row);

        $this->assertNotEmpty($share->users);
        $this->assertEmpty($share->groups);
    }

    /**
     * View shared with cohort → share->groups populated with cohort name.
     */
    public function test_view_shared_with_cohort(): void {
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'ViewCohort']);
        $viewid = $this->create_view($this->owner);
        $this->share_view_with_cohort($viewid, $cohort->id);
        $row = (object)['shareall' => 0, 'externaccess' => 0];

        $share = share_overview::build_share_info('view', $viewid, $row);

        $this->assertNotEmpty($share->groups);
        $this->assertContains('ViewCohort', $share->groups);
        $this->assertEmpty($share->users);
    }

    /**
     * The recipient overview and view-items resolver use the complete owner-wide audience.
     */
    public function test_view_shared_with_three_users_has_same_full_audience_everywhere(): void {
        $users = [
            $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Audience']),
            $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Audience']),
            $this->getDataGenerator()->create_user(['firstname' => 'Carol', 'lastname' => 'Audience']),
        ];
        $viewid = $this->create_view($this->owner);
        foreach ($users as $user) {
            $this->share_view_with_user($viewid, $user->id);
        }

        $overviewrows = share_overview::get_shared_with_me($users[0]->id);
        $overviewrow = current(array_filter($overviewrows, function($row) use ($viewid) {
            return $row->entity_type === 'view' && (int)$row->id === $viewid;
        }));
        $viewitemsrows = view_helper::load_flat_views($this->owner->id, [], 'name', 'asc');

        $this->assertNotFalse($overviewrow);
        $this->assertCount(3, $overviewrow->shareinfo->users);
        $this->assertSame($overviewrow->shareinfo->users, $viewitemsrows[$viewid]->shareinfo->users);
        $this->assertSame(
            get_string('sharedwith_user_cnt', 'block_exaport', 3),
            block_exaport_get_share_summary($overviewrow->shareinfo)
        );
        foreach (['Alice Audience', 'Bob Audience', 'Carol Audience'] as $name) {
            $this->assertStringContainsString($name, block_exaport_get_share_tooltip($overviewrow->shareinfo));
        }
    }

    /**
     * Direct users and cohort recipients remain distinct in the canonical result.
     */
    public function test_view_mixed_user_and_cohort_audience_is_not_collapsed(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Direct', 'lastname' => 'Recipient']);
        $cohortuser = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Cohort recipient']);
        cohort_add_member($cohort->id, $cohortuser->id);
        $viewid = $this->create_view($this->owner, 2);
        $this->share_view_with_user($viewid, $user->id);
        $this->share_view_with_cohort($viewid, $cohort->id);

        $rows = share_overview::get_shared_with_me($user->id);
        $row = current(array_filter($rows, function($row) use ($viewid) {
            return $row->entity_type === 'view' && (int)$row->id === $viewid;
        }));

        $this->assertNotFalse($row);
        $this->assertFalse($row->shareinfo->all);
        $this->assertSame(['Direct Recipient'], $row->shareinfo->users);
        $this->assertSame(['Cohort recipient'], $row->shareinfo->groups);
    }

    // -------------------------------------------------------------------------
    // Tests: share_overview::build_share_info — categories (delegates to category_helper)
    // -------------------------------------------------------------------------

    /**
     * Category delegation: build_share_info for a category returns the same result
     * as calling category_helper::build_share_info() directly.
     */
    public function test_category_delegates_to_category_helper(): void {
        $catid = $this->create_category($this->owner, internshare: 1, shareall: 1);
        $row   = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 1, 'externaccess' => 0];

        $viaOverview = share_overview::build_share_info('category', $catid, $row);
        $viaHelper   = category_helper::build_share_info($row);

        $this->assertSame($viaHelper->all, $viaOverview->all);
        $this->assertSame($viaHelper->users, $viaOverview->users);
        $this->assertSame($viaHelper->groups, $viaOverview->groups);
        $this->assertSame($viaHelper->external, $viaOverview->external);
    }

    /**
     * Category direct and cohort shares are both included in one resolved audience.
     */
    public function test_category_direct_and_cohort_audience(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Category', 'lastname' => 'Recipient']);
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Category cohort']);
        cohort_add_member($cohort->id, $user->id);
        $categoryid = $this->create_category($this->owner, internshare: 1, shareall: 2);
        $this->share_category_with_user($categoryid, $user->id);
        $this->share_category_with_cohort($categoryid, $cohort->id);

        $rows = share_overview::get_shared_with_me($user->id);
        $row = current(array_filter($rows, function($row) use ($categoryid) {
            return $row->entity_type === 'category' && (int)$row->id === $categoryid;
        }));

        $this->assertNotFalse($row);
        $this->assertSame(['Category Recipient'], $row->shareinfo->users);
        $this->assertSame(['Category cohort'], $row->shareinfo->groups);
    }

    // -------------------------------------------------------------------------
    // Tests: tooltip integration
    // -------------------------------------------------------------------------

    /**
     * build_share_info() result can be passed straight to block_exaport_get_share_tooltip().
     */
    public function test_tooltip_from_item_share(): void {
        $user1  = $this->getDataGenerator()->create_user(['firstname' => 'Dave', 'lastname' => 'Test']);
        $itemid = $this->create_item($this->owner);
        $this->share_item_with_user($itemid, $user1->id);
        $row = (object)['shareall' => 0, 'externaccess' => 0];

        $share   = share_overview::build_share_info('item', $itemid, $row);
        $tooltip = block_exaport_get_share_tooltip($share);

        $this->assertNotEmpty($tooltip);
        $this->assertStringContainsString('Dave', $tooltip);
    }
}
