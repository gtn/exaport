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
 * Tests for category-based view sharing:
 *   - view_helper::get_category_shared_view_ids()
 *   - block_exaport_get_view_from_access() extended for category grants
 *   - view_helper::load_flat_views() with $allowedcategoryids
 *
 * @package    block_exaport
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_sharing_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    /** @var \stdClass Owner user (view/category creator). */
    private \stdClass $owner;

    /** @var \stdClass Recipient user (requests access). */
    private \stdClass $recipient;

    protected function setUp(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $this->owner     = $this->getDataGenerator()->create_user();
        $this->recipient = $this->getDataGenerator()->create_user();

        // Make sure external access is enabled so the ACL gate does not short-circuit.
        // NOTE: the setting is an INVERTED "disable" flag — block_exaport_externaccess_enabled()
        // returns empty($CFG->block_exaport_disable_externaccess), so 0 means enabled.
        // There is no 'block_exaport_externaccess' setting.
        set_config('block_exaport_disable_externaccess', 0);

        // The 'views' feature needs no config: block_exaport_feature_enabled('views')
        // is hardcoded to return true.

        // Ensure block_exaport lib functions are loaded.
        require_once($CFG->dirroot . '/blocks/exaport/lib.php');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Assign a view to a category via block_exaportviewcate.
     *
     * @param int $viewid
     * @param int $catid
     */
    private function assign_view_to_category(int $viewid, int $catid): void {
        global $DB;
        $DB->insert_record('block_exaportviewcate', (object)[
            'viewid' => $viewid,
            'cateid' => $catid,
        ]);
    }

    /**
     * Share a category directly with a user.
     *
     * @param int $catid
     * @param int $userid
     */
    private function share_category_with_user(int $catid, int $userid): void {
        global $DB;
        $DB->insert_record('block_exaportcatshar', (object)[
            'catid'  => $catid,
            'userid' => $userid,
            'notify' => 0,
        ]);
    }

    /**
     * Share a category with a cohort (groupid column stores cohort ids).
     *
     * @param int $catid
     * @param int $cohortid
     */
    private function share_category_with_cohort(int $catid, int $cohortid): void {
        global $DB;
        $DB->insert_record('block_exaportcatgroupshar', (object)[
            'catid'   => $catid,
            'groupid' => $cohortid,
        ]);
    }

    // =========================================================================
    // get_category_shared_view_ids() tests
    // =========================================================================

    /**
     * A view assigned to a category shared directly with the recipient is returned.
     */
    public function test_direct_share_returns_view(): void {
        global $USER;
        $USER = $this->recipient;

        $catid  = $this->create_category($this->owner, internshare: 1); // internshare=1
        $viewid = $this->create_view($this->owner);
        $this->assign_view_to_category($viewid, $catid);
        $this->share_category_with_user($catid, $this->recipient->id);

        $ids = view_helper::get_category_shared_view_ids($this->recipient->id);
        $this->assertContains($viewid, $ids);
    }

    /**
     * A view in a descendant of a shared category is returned (recursive inheritance).
     */
    public function test_subcategory_inheritance_is_recursive(): void {
        global $USER;
        $USER = $this->recipient;

        $rootcatid  = $this->create_category($this->owner, internshare: 1);
        $childcatid = $this->create_category($this->owner, pid: $rootcatid, internshare: 1);
        $grandcatid = $this->create_category($this->owner, pid: $childcatid);

        $viewid = $this->create_view($this->owner);
        $this->assign_view_to_category($viewid, $grandcatid);
        $this->share_category_with_user($rootcatid, $this->recipient->id);

        $ids = view_helper::get_category_shared_view_ids($this->recipient->id);
        $this->assertContains($viewid, $ids);
    }

    /**
     * A view in a cohort-shared category is returned when the recipient is a cohort member.
     */
    public function test_cohort_share_returns_view(): void {
        global $USER;
        $USER = $this->recipient;

        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $this->recipient->id);

        $catid  = $this->create_category($this->owner, internshare: 1);
        $viewid = $this->create_view($this->owner);
        $this->assign_view_to_category($viewid, $catid);
        $this->share_category_with_cohort($catid, $cohort->id);

        $ids = view_helper::get_category_shared_view_ids($this->recipient->id);
        $this->assertContains($viewid, $ids);
    }

    /**
     * A view in a category NOT shared to the recipient is not returned.
     */
    public function test_unshared_category_view_not_returned(): void {
        global $USER;
        $USER = $this->recipient;

        $catid  = $this->create_category($this->owner, internshare: 1); // internshare but not shared to anyone
        $viewid = $this->create_view($this->owner);
        $this->assign_view_to_category($viewid, $catid);
        // No share record inserted.

        $ids = view_helper::get_category_shared_view_ids($this->recipient->id);
        $this->assertNotContains($viewid, $ids);
    }

    /**
     * Empty result when recipient has no shared categories at all.
     */
    public function test_no_shared_categories_returns_empty(): void {
        global $USER;
        $USER = $this->recipient;

        $ids = view_helper::get_category_shared_view_ids($this->recipient->id);
        $this->assertSame([], $ids);
    }

    /**
     * A view owned by user B placed in user A's shared category must NOT be reachable
     * via A's share (owner scoping).
     */
    public function test_owner_scoping_blocks_cross_owner_view(): void {
        global $USER;
        $USER = $this->recipient;

        // User B (owner) creates and shares a category.
        $catid = $this->create_category($this->owner, internshare: 1);
        $this->share_category_with_user($catid, $this->recipient->id);

        // User C (other) creates a view and assigns it to B's category.
        $other  = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($other);
        $this->assign_view_to_category($viewid, $catid);

        $ids = view_helper::get_category_shared_view_ids($this->recipient->id);
        $this->assertNotContains($viewid, $ids);
    }

    // =========================================================================
    // block_exaport_get_view_from_access() — id/ branch category grant
    // =========================================================================

    /**
     * A view in a shared category is accessible via block_exaport_get_view_from_access(id/...)
     * even when its own shareall=0 and there is no per-user view share record.
     */
    public function test_get_view_from_access_category_grant(): void {
        global $USER;
        $USER = $this->recipient;

        $catid  = $this->create_category($this->owner, internshare: 1);
        $viewid = $this->create_view($this->owner, 0); // shareall=0, not shared individually
        $this->assign_view_to_category($viewid, $catid);
        $this->share_category_with_user($catid, $this->recipient->id);

        $access = 'id/' . $this->owner->id . '-' . $viewid;
        $view   = block_exaport_get_view_from_access($access);
        $this->assertNotEmpty($view, 'View should be accessible via category grant');
        $this->assertEquals($viewid, (int)$view->id);
    }

    /**
     * A view NOT in a shared category is not accessible to the recipient.
     */
    public function test_get_view_from_access_no_category_grant(): void {
        global $USER;
        $USER = $this->recipient;

        // No category, no share.
        $viewid = $this->create_view($this->owner, 0);

        $access = 'id/' . $this->owner->id . '-' . $viewid;
        $view   = block_exaport_get_view_from_access($access);
        $this->assertEmpty($view, 'View without category grant must not be accessible');
    }

    /**
     * A direct user share remains valid when the view also uses cohort sharing mode.
     */
    public function test_get_view_from_access_direct_grant_with_cohort_mode(): void {
        global $DB, $USER;
        $USER = $this->recipient;

        $viewid = $this->create_view($this->owner, 2);
        $DB->insert_record('block_exaportviewshar', (object)[
            'viewid' => $viewid,
            'userid' => $this->recipient->id,
            'notify' => 0,
        ]);

        $access = 'id/' . $this->owner->id . '-' . $viewid;
        $view = block_exaport_get_view_from_access($access);

        $this->assertNotEmpty($view);
        $this->assertEquals($viewid, (int)$view->id);
    }

    /**
     * Disabling share-all also revokes internal access through an existing URL.
     */
    public function test_get_view_from_access_shareall_disabled(): void {
        global $USER;
        $USER = $this->recipient;
        set_config('block_exaport_disable_shareall', 1);

        $viewid = $this->create_view($this->owner, 1);
        $access = 'id/' . $this->owner->id . '-' . $viewid;

        $this->assertEmpty(block_exaport_get_view_from_access($access));
    }

    /**
     * The sharing master switch revokes all existing channels together.
     */
    public function test_view_sharing_master_switch_revokes_every_channel(): void {
        global $DB, $USER;

        $viewid = $this->create_view($this->owner, 1, 1);
        $view = $DB->get_record('block_exaportview', ['id' => $viewid], '*', MUST_EXIST);
        $view->internaccess = 1;
        $view->sharedemails = 1;
        $DB->update_record('block_exaportview', $view);
        $DB->insert_record('block_exaportviewshar', (object)[
            'viewid' => $viewid, 'userid' => $this->recipient->id, 'notify' => 0,
        ]);
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $this->recipient->id);
        $DB->insert_record('block_exaportviewgroupshar', (object)[
            'viewid' => $viewid, 'groupid' => $cohort->id,
        ]);
        $emailhash = 'emailhash123';
        $DB->insert_record('block_exaportviewemailshar', (object)[
            'viewid' => $viewid, 'email' => 'reader@example.com', 'hash' => $emailhash,
        ]);

        $view = block_exaport_normalize_view_sharing($view, false);
        block_exaport_revoke_view_sharing($viewid);
        $DB->update_record('block_exaportview', $view);

        $saved = $DB->get_record('block_exaportview', ['id' => $viewid], '*', MUST_EXIST);
        $this->assertSame(0, (int)$saved->externaccess);
        $this->assertSame(0, (int)$saved->shareall);
        $this->assertSame(0, (int)$saved->sharedemails);
        $this->assertFalse($DB->record_exists('block_exaportviewshar', ['viewid' => $viewid]));
        $this->assertFalse($DB->record_exists('block_exaportviewgroupshar', ['viewid' => $viewid]));
        $this->assertFalse($DB->record_exists('block_exaportviewemailshar', ['viewid' => $viewid]));

        $USER = $this->recipient;
        $this->assertEmpty(block_exaport_get_view_from_access('id/' . $this->owner->id . '-' . $viewid));
        $this->assertEmpty(block_exaport_get_view_from_access('hash/' . $this->owner->id . '-' . $saved->hash));
        $this->assertEmpty(block_exaport_get_view_from_access('email/' . $saved->hash . '-' . $emailhash));
    }

    /**
     * Forged subordinate values cannot override a disabled master switch.
     */
    public function test_disabled_master_switch_ignores_forged_subordinate_controls(): void {
        $submitted = (object)[
            'externaccess' => 1,
            'internaccess' => 1,
            'shareall' => 2,
            'externcomment' => 1,
            'sharedemails' => 1,
        ];

        $normalized = block_exaport_normalize_view_sharing($submitted, false);

        $this->assertSame(0, $normalized->externaccess);
        $this->assertSame(0, $normalized->internaccess);
        $this->assertSame(0, $normalized->shareall);
        $this->assertSame(0, $normalized->externcomment);
        $this->assertSame(0, $normalized->sharedemails);
    }

    /**
     * Enabling the master leaves each independently selected channel intact.
     *
     * @dataProvider enabled_channel_provider
     */
    public function test_enabled_master_preserves_independent_channels(array $fields): void {
        $normalized = block_exaport_normalize_view_sharing((object)$fields, true);

        foreach ($fields as $field => $value) {
            $this->assertSame($value, $normalized->{$field});
        }
    }

    public static function enabled_channel_provider(): array {
        return [
            'external only' => [['externaccess' => 1, 'internaccess' => 0, 'shareall' => 0, 'sharedemails' => 0]],
            'internal only' => [['externaccess' => 0, 'internaccess' => 1, 'shareall' => 1, 'sharedemails' => 0]],
            'email only' => [['externaccess' => 0, 'internaccess' => 0, 'shareall' => 0, 'sharedemails' => 1]],
        ];
    }

    /**
     * With sharing enabled, external, direct-user and email grants operate without enabling each other.
     */
    public function test_enabled_master_allows_channels_to_work_independently(): void {
        global $DB, $USER;

        $externalid = $this->create_view($this->owner, 0, 1);
        $internalid = $this->create_view($this->owner);
        $emailid = $this->create_view($this->owner);
        $external = block_exaport_normalize_view_sharing(
            $DB->get_record('block_exaportview', ['id' => $externalid], '*', MUST_EXIST), true);
        $internal = block_exaport_normalize_view_sharing(
            $DB->get_record('block_exaportview', ['id' => $internalid], '*', MUST_EXIST), true);
        $email = block_exaport_normalize_view_sharing(
            $DB->get_record('block_exaportview', ['id' => $emailid], '*', MUST_EXIST), true);
        $email->sharedemails = 1;
        $DB->update_record('block_exaportview', $email);
        $DB->insert_record('block_exaportviewshar', (object)[
            'viewid' => $internalid, 'userid' => $this->recipient->id, 'notify' => 0,
        ]);
        $emailhash = 'independent123';
        $DB->insert_record('block_exaportviewemailshar', (object)[
            'viewid' => $emailid, 'email' => 'independent@example.com', 'hash' => $emailhash,
        ]);

        $this->assertNotEmpty(block_exaport_get_view_from_access(
            'hash/' . $this->owner->id . '-' . $external->hash));
        $USER = $this->recipient;
        $this->assertNotEmpty(block_exaport_get_view_from_access(
            'id/' . $this->owner->id . '-' . $internal->id));
        $this->assertNotEmpty(block_exaport_get_view_from_access('email/' . $email->hash . '-' . $emailhash));
    }

    /**
     * A view in a descendant of a shared category is accessible (recursive inheritance).
     */
    public function test_get_view_from_access_descendant_category(): void {
        global $USER;
        $USER = $this->recipient;

        $rootcatid  = $this->create_category($this->owner, internshare: 1);
        $childcatid = $this->create_category($this->owner, pid: $rootcatid);

        $viewid = $this->create_view($this->owner, 0);
        $this->assign_view_to_category($viewid, $childcatid);
        $this->share_category_with_user($rootcatid, $this->recipient->id);

        $access = 'id/' . $this->owner->id . '-' . $viewid;
        $view   = block_exaport_get_view_from_access($access);
        $this->assertNotEmpty($view);
        $this->assertEquals($viewid, (int)$view->id);
    }

    // =========================================================================
    // block_exaport_get_view_from_access() — category/ branch (external)
    // =========================================================================

    /**
     * A view in an externally-shared category is accessible via the category hash.
     */
    public function test_get_view_from_access_external_category_hash(): void {
        $hash   = 'ab12cd34';
        $catid  = $this->create_category($this->owner, externaccess: 1, hash: $hash);
        $viewid = $this->create_view($this->owner, 0, 0); // externaccess=0 should not veto
        $this->assign_view_to_category($viewid, $catid);

        // Simulate GET param viewid.
        $_GET['viewid'] = $viewid;

        $access = 'category/hash/' . $this->owner->id . '-' . $hash;
        $view   = block_exaport_get_view_from_access($access);
        $this->assertNotEmpty($view, 'View should be accessible via external category hash');
        $this->assertEquals($viewid, (int)$view->id);
        $this->assertEquals('extern', $view->access->request);

        unset($_GET['viewid']);
    }

    /**
     * A view in a sibling category (outside the shared subtree) is not accessible via the hash.
     */
    public function test_get_view_from_access_external_sibling_category_blocked(): void {
        $hash   = 'ef56gh78';
        // Shared category.
        $catid  = $this->create_category($this->owner, externaccess: 1, hash: $hash);
        // Sibling (private) category.
        $siblingcatid = $this->create_category($this->owner);

        $viewid = $this->create_view($this->owner, 0, 0);
        $this->assign_view_to_category($viewid, $siblingcatid); // NOT in the shared subtree

        $_GET['viewid'] = $viewid;
        $access = 'category/hash/' . $this->owner->id . '-' . $hash;
        $view   = block_exaport_get_view_from_access($access);
        $this->assertEmpty($view, 'View outside shared subtree must not be accessible');
        unset($_GET['viewid']);
    }

    /**
     * External access fails when block_exaport_externaccess_enabled() is false.
     *
     * The setting is an INVERTED "disable" flag: block_exaport_externaccess_enabled()
     * returns empty($CFG->block_exaport_disable_externaccess), so 1 means DISABLED.
     * block_exaport_get_category_from_access() checks this centrally, so the
     * category never resolves and the category/ branch bails out.
     */
    public function test_get_view_from_access_external_disabled(): void {
        set_config('block_exaport_disable_externaccess', 1);

        $hash   = 'ij90kl12';
        $catid  = $this->create_category($this->owner, externaccess: 1, hash: $hash);
        $viewid = $this->create_view($this->owner, 0, 0);
        $this->assign_view_to_category($viewid, $catid);

        $_GET['viewid'] = $viewid;
        $access = 'category/hash/' . $this->owner->id . '-' . $hash;
        $view   = block_exaport_get_view_from_access($access);
        $this->assertEmpty($view, 'External access must fail when externaccess is disabled');
        unset($_GET['viewid']);

        set_config('block_exaport_disable_externaccess', 0);
    }

    /**
     * A view belonging to a different owner is not accessible via the category hash.
     */
    public function test_get_view_from_access_external_wrong_owner_blocked(): void {
        $hash   = 'mn34op56';
        $catid  = $this->create_category($this->owner, externaccess: 1, hash: $hash);

        $other  = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($other, 0, 0); // Wrong owner
        $this->assign_view_to_category($viewid, $catid);

        $_GET['viewid'] = $viewid;
        $access = 'category/hash/' . $this->owner->id . '-' . $hash;
        $view   = block_exaport_get_view_from_access($access);
        $this->assertEmpty($view, 'View with wrong owner must not be accessible');
        unset($_GET['viewid']);
    }

    // =========================================================================
    // view_helper::load_flat_views() with $allowedcategoryids
    // =========================================================================

    /**
     * load_flat_views() drops views with no intersecting allowed category.
     */
    public function test_load_flat_views_drops_disallowed(): void {
        $sharedcat   = $this->create_category($this->owner, internshare: 1);
        $privatecat  = $this->create_category($this->owner);

        $sharedview  = $this->create_view($this->owner);
        $privateview = $this->create_view($this->owner);
        $this->assign_view_to_category($sharedview, $sharedcat);
        $this->assign_view_to_category($privateview, $privatecat);

        $categories = \block_exaport\category_helper::load_owner_categories($this->owner->id);
        $views = view_helper::load_flat_views($this->owner->id, $categories, 'name', 'asc', [$sharedcat]);

        $viewids = array_keys($views);
        $this->assertContains($sharedview, $viewids);
        $this->assertNotContains($privateview, $viewids);
    }

    /**
     * A view assigned to both a shared and a private category surfaces, and its
     * flatcategories contain only the shared one.
     */
    public function test_load_flat_views_category_badges_restricted(): void {
        $sharedcat  = $this->create_category($this->owner, internshare: 1);
        $privatecat = $this->create_category($this->owner);

        $viewid = $this->create_view($this->owner);
        $this->assign_view_to_category($viewid, $sharedcat);
        $this->assign_view_to_category($viewid, $privatecat);

        $categories = \block_exaport\category_helper::load_owner_categories($this->owner->id);
        $views = view_helper::load_flat_views($this->owner->id, $categories, 'name', 'asc', [$sharedcat]);

        $this->assertArrayHasKey($viewid, $views);
        $view = $views[$viewid];
        $catids = array_map(fn($c) => (int)$c->id, $view->flatcategories);
        $this->assertContains($sharedcat, $catids, 'Shared category badge must be present');
        $this->assertNotContains($privatecat, $catids, 'Private category badge must be absent');
    }
}
