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

    /**
     * Only entity types whose share form has a notify checkbox offer one on the search page.
     */
    public function test_config_marks_notify_support(): void {
        $this->assertTrue(block_exaport_get_sharing_entity_config('view')->supportsnotify);
        $this->assertTrue(block_exaport_get_sharing_entity_config('category')->supportsnotify);
        $this->assertFalse(block_exaport_get_sharing_entity_config('item')->supportsnotify);
    }

    /**
     * The user search page shares with notify = 0 unless notify was really checked, and a
     * notify = 0 row stays 0 when it is saved again without an explicit notify change.
     */
    public function test_toggle_shared_users_notify_round_trip(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $categoryid = $this->create_category($owner, 'Cat', 0, 1);
        $config = block_exaport_get_sharing_entity_config('category');

        // Sharing without checking notify (the hidden field submits an explicit 0).
        block_exaport_sharing_toggle_shared_users($config, $categoryid,
            [$recipient->id => $recipient->id], [$recipient->id => 0]);
        $share = $DB->get_record('block_exaportcatshar', ['catid' => $categoryid, 'userid' => $recipient->id]);
        $this->assertEquals(0, $share->notify);

        // Saving again without an explicit notify change must not turn notify on.
        block_exaport_sharing_toggle_shared_users($config, $categoryid,
            [$recipient->id => $recipient->id], [$recipient->id => 0]);
        $this->assertEquals(0, $DB->get_field('block_exaportcatshar', 'notify',
            ['catid' => $categoryid, 'userid' => $recipient->id]));

        // Checking notify submits the user id and turns it on.
        block_exaport_sharing_toggle_shared_users($config, $categoryid,
            [$recipient->id => $recipient->id], [$recipient->id => $recipient->id]);
        $this->assertEquals(1, $DB->get_field('block_exaportcatshar', 'notify',
            ['catid' => $categoryid, 'userid' => $recipient->id]));

        // ... and unchecking it turns it off again, without touching the share itself.
        block_exaport_sharing_toggle_shared_users($config, $categoryid,
            [$recipient->id => $recipient->id], [$recipient->id => 0]);
        $this->assertEquals(0, $DB->get_field('block_exaportcatshar', 'notify',
            ['catid' => $categoryid, 'userid' => $recipient->id]));
        $this->assertCount(1, $DB->get_records('block_exaportcatshar', ['catid' => $categoryid]));
    }

    /**
     * A missing notify value (its checkbox was disabled and therefore not submitted at all)
     * keeps the stored value, and unselecting a user removes the row including its notify state.
     */
    public function test_toggle_shared_users_notify_absent_keeps_value(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($owner);
        $config = block_exaport_get_sharing_entity_config('view');

        block_exaport_sharing_toggle_shared_users($config, $viewid,
            [$recipient->id => $recipient->id], [$recipient->id => $recipient->id]);
        $this->assertEquals(1, $DB->get_field('block_exaportviewshar', 'notify',
            ['viewid' => $viewid, 'userid' => $recipient->id]));

        // No notify value submitted at all: the stored 1 survives.
        block_exaport_sharing_toggle_shared_users($config, $viewid, [$recipient->id => $recipient->id]);
        $this->assertEquals(1, $DB->get_field('block_exaportviewshar', 'notify',
            ['viewid' => $viewid, 'userid' => $recipient->id]));

        // Unselecting the user removes the row, notify value included.
        block_exaport_sharing_toggle_shared_users($config, $viewid, [$recipient->id => 0],
            [$recipient->id => $recipient->id]);
        $this->assertFalse($DB->record_exists('block_exaportviewshar',
            ['viewid' => $viewid, 'userid' => $recipient->id]));
    }

    /**
     * With "always notify when share" the notify checkboxes are disabled and only mirror the
     * share state, so the value is forced on save - exactly like the normal share form does.
     */
    public function test_toggle_shared_users_always_notify(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $categoryid = $this->create_category($owner, 'Cat', 0, 1);
        $config = block_exaport_get_sharing_entity_config('category');

        // New share: notify is forced on although nothing was submitted for it.
        block_exaport_sharing_toggle_shared_users($config, $categoryid,
            [$recipient->id => $recipient->id], [], true);
        $this->assertEquals(1, $DB->get_field('block_exaportcatshar', 'notify',
            ['catid' => $categoryid, 'userid' => $recipient->id]));

        // Existing share with notify = 0: also forced on, a submitted 0 cannot override it.
        $DB->set_field('block_exaportcatshar', 'notify', 0,
            ['catid' => $categoryid, 'userid' => $recipient->id]);
        block_exaport_sharing_toggle_shared_users($config, $categoryid,
            [$recipient->id => $recipient->id], [$recipient->id => 0], true);
        $this->assertEquals(1, $DB->get_field('block_exaportcatshar', 'notify',
            ['catid' => $categoryid, 'userid' => $recipient->id]));
    }

    // =========================================================================
    // block_exaport_sharing_save_direct_user_shares() - "share" form save path,
    // used by views_mod.php/category.php/item.php instead of a delete-all-then-insert-all
    // loop. Direct user shares are entity-wide (one row per entity/user), even though the
    // share form groups the same eligible user under several courses - see
    // block_exaport_ajax_sharing_userlist_course() in lib/sharelib.php.
    // =========================================================================

    /**
     * Duplicate submitted user ids (e.g. the same user checked under two course groups) must
     * never create more than one share row.
     */
    public function test_save_direct_user_shares_dedupes_duplicate_submitted_ids(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($owner);
        $config = block_exaport_get_sharing_entity_config('view');

        // The same user id submitted three times, as if it had been checked under three
        // different course groups in the share form.
        block_exaport_sharing_save_direct_user_shares($config, $viewid,
            [$recipient->id, $recipient->id, $recipient->id], []);

        $this->assertCount(1, $DB->get_records('block_exaportviewshar', ['viewid' => $viewid]));
        $this->assertTrue($DB->record_exists('block_exaportviewshar',
            ['viewid' => $viewid, 'userid' => $recipient->id]));
    }

    /**
     * Non-existent and deleted user ids are never inserted, regardless of course grouping.
     */
    public function test_save_direct_user_shares_rejects_invalid_and_deleted_users(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $validuser = $this->getDataGenerator()->create_user();
        $deleteduser = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $deleteduser->id]);
        $nonexistentid = $deleteduser->id + 1000000;
        $viewid = $this->create_view($owner);
        $config = block_exaport_get_sharing_entity_config('view');

        block_exaport_sharing_save_direct_user_shares($config, $viewid,
            [$validuser->id, $deleteduser->id, $nonexistentid], []);

        $this->assertCount(1, $DB->get_records('block_exaportviewshar', ['viewid' => $viewid]));
        $this->assertTrue($DB->record_exists('block_exaportviewshar',
            ['viewid' => $viewid, 'userid' => $validuser->id]));
        $this->assertFalse($DB->record_exists('block_exaportviewshar',
            ['viewid' => $viewid, 'userid' => $deleteduser->id]));
    }

    /**
     * Reconciliation removes shares for users no longer selected - including ones whose course
     * group was never expanded/loaded by the frontend and therefore could not be re-submitted.
     */
    public function test_save_direct_user_shares_removes_unselected_users(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $keptuser = $this->getDataGenerator()->create_user();
        $removeduser = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($owner);
        $config = block_exaport_get_sharing_entity_config('view');

        block_exaport_sharing_save_direct_user_shares($config, $viewid,
            [$keptuser->id, $removeduser->id], []);
        $this->assertCount(2, $DB->get_records('block_exaportviewshar', ['viewid' => $viewid]));

        // Only $keptuser is submitted this time (as if $removeduser's course group was never
        // loaded and so never re-submitted the checkbox).
        block_exaport_sharing_save_direct_user_shares($config, $viewid, [$keptuser->id], []);

        $this->assertCount(1, $DB->get_records('block_exaportviewshar', ['viewid' => $viewid]));
        $this->assertTrue($DB->record_exists('block_exaportviewshar',
            ['viewid' => $viewid, 'userid' => $keptuser->id]));
        $this->assertFalse($DB->record_exists('block_exaportviewshar',
            ['viewid' => $viewid, 'userid' => $removeduser->id]));
    }

    /**
     * An empty selection removes every existing direct share (e.g. when internal access/share
     * to single users is turned off).
     */
    public function test_save_direct_user_shares_empty_selection_removes_all(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($owner);
        $config = block_exaport_get_sharing_entity_config('view');

        block_exaport_sharing_save_direct_user_shares($config, $viewid, [$recipient->id], []);
        $this->assertCount(1, $DB->get_records('block_exaportviewshar', ['viewid' => $viewid]));

        block_exaport_sharing_save_direct_user_shares($config, $viewid, [], []);
        $this->assertCount(0, $DB->get_records('block_exaportviewshar', ['viewid' => $viewid]));
    }

    /**
     * Notify values are set on insert and updated for already-shared users, with duplicate
     * submitted notify ids normalized the same way as duplicate share ids.
     */
    public function test_save_direct_user_shares_notify_values(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($owner);
        $config = block_exaport_get_sharing_entity_config('view');

        // $usera is shared and notified (submitted twice, as if listed under two courses),
        // $userb is shared without notification.
        block_exaport_sharing_save_direct_user_shares($config, $viewid,
            [$usera->id, $userb->id], [$usera->id, $usera->id]);

        $sharea = $DB->get_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $usera->id]);
        $shareb = $DB->get_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $userb->id]);
        $this->assertEquals(1, $sharea->notify);
        $this->assertEquals(0, $shareb->notify);

        // A later save without $usera in notifyuserids turns notification off again for the
        // already-shared row instead of leaving it stale.
        block_exaport_sharing_save_direct_user_shares($config, $viewid,
            [$usera->id, $userb->id], [$userb->id]);

        $sharea = $DB->get_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $usera->id]);
        $shareb = $DB->get_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $userb->id]);
        $this->assertEquals(0, $sharea->notify);
        $this->assertEquals(1, $shareb->notify);
    }

    /**
     * Regression test for the "notify checkbox appears checked after reload, then persists as
     * notify=1 on the next unmodified save" bug: sharing a user (without notifying) and
     * resubmitting the exact same form data afterwards (as a plain "reload, then save without
     * changing anything" round trip would) must keep notify=0 forever, even when the user is
     * submitted under several duplicate course occurrences, and even across several repeated
     * saves.
     */
    public function test_save_direct_user_shares_repeated_save_without_notify_stays_zero(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $viewid = $this->create_view($owner);
        $config = block_exaport_get_sharing_entity_config('view');

        // Share $recipient, submitted three times as if rendered under three course groups,
        // without ever selecting notify.
        block_exaport_sharing_save_direct_user_shares($config, $viewid,
            [$recipient->id, $recipient->id, $recipient->id], []);

        $share = $DB->get_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $recipient->id]);
        $this->assertEquals(0, $share->notify);

        // Reload + save again without changing anything: the share checkbox is still submitted
        // (checked), notify is still absent from the submission (unchecked) - notify must stay 0.
        block_exaport_sharing_save_direct_user_shares($config, $viewid,
            [$recipient->id, $recipient->id, $recipient->id], []);

        $share = $DB->get_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $recipient->id]);
        $this->assertEquals(0, $share->notify);

        // A third identical save keeps behaving the same way.
        block_exaport_sharing_save_direct_user_shares($config, $viewid,
            [$recipient->id, $recipient->id, $recipient->id], []);

        $share = $DB->get_record('block_exaportviewshar', ['viewid' => $viewid, 'userid' => $recipient->id]);
        $this->assertEquals(0, $share->notify);
    }

    /**
     * The $forcenotify parameter (block_exaport's "always notify when share" admin setting)
     * overrides the submitted notify selection for every shared user.
     */
    public function test_save_direct_user_shares_forcenotify(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $categoryid = $this->create_category($owner, 'Cat', 0, 1);
        $config = block_exaport_get_sharing_entity_config('category');

        block_exaport_sharing_save_direct_user_shares($config, $categoryid,
            [$recipient->id], [], [], true);

        $share = $DB->get_record('block_exaportcatshar', ['catid' => $categoryid, 'userid' => $recipient->id]);
        $this->assertEquals(1, $share->notify);
    }

    /**
     * Extra fields (e.g. item.php's 'original'/'courseid') are only applied to newly inserted
     * rows, matching block_exaportitemshar's schema which requires them.
     */
    public function test_save_direct_user_shares_extrafields_on_item(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $recipient = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $itemid = $DB->insert_record('block_exaportitem', (object)[
            'userid' => $owner->id,
            'name' => 'Test item',
            'type' => 'note',
            'intro' => '',
            'timemodified' => time(),
            'courseid' => $course->id,
        ]);
        $config = block_exaport_get_sharing_entity_config('item');

        block_exaport_sharing_save_direct_user_shares($config, $itemid, [$recipient->id], [],
            ['original' => 0, 'courseid' => $course->id]);

        $share = $DB->get_record('block_exaportitemshar', ['itemid' => $itemid, 'userid' => $recipient->id]);
        $this->assertNotEmpty($share);
        $this->assertEquals(0, $share->original);
        $this->assertEquals($course->id, $share->courseid);
    }
}
