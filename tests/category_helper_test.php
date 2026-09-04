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
 * Tests for category_helper::build_share_info(), view_helper sharing, and
 * block_exaport_get_share_tooltip*().
 *
 * @package    block_exaport
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_helper_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    /** @var \stdClass Owner user. */
    private \stdClass $owner;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        // Fail loudly if lang strings are missing (stale PHPUnit string cache).
        $this->assertTrue(
            get_string_manager()->string_exists('share_tooltip_users', 'block_exaport'),
            'Language strings missing — run admin/tool/phpunit/cli/init.php'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Share a category with a cohort (block_exaportcatgroupshar.groupid = cohort id).
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

    /**
     * Share a category with a specific user.
     *
     * @param int $catid
     * @param int $userid
     */
    private function share_with_user(int $catid, int $userid): void {
        global $DB;
        $DB->insert_record('block_exaportcatshar', (object)[
            'catid'  => $catid,
            'userid' => $userid,
            'notify' => 0,
        ]);
    }

    /**
     * Share a view with a cohort (block_exaportviewgroupshar.groupid = cohort id).
     *
     * @param int $viewid
     * @param int $cohortid
     */
    private function share_view_with_cohort(int $viewid, int $cohortid): void {
        global $DB;
        $DB->insert_record('block_exaportviewgroupshar', (object)[
            'viewid'  => $viewid,
            'groupid' => $cohortid,
        ]);
    }

    /**
     * Share a view with a specific user.
     *
     * @param int $viewid
     * @param int $userid
     */
    private function share_view_with_user(int $viewid, int $userid): void {
        global $DB;
        $DB->insert_record('block_exaportviewshar', (object)[
            'viewid' => $viewid,
            'userid' => $userid,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests: category parent selector and validation
    // -------------------------------------------------------------------------

    public function test_parent_options_include_root_and_full_paths(): void {
        $work = $this->create_category($this->owner, 'Work');
        $projects = $this->create_category($this->owner, 'Projects', $work);
        $moodle = $this->create_category($this->owner, 'Moodle', $projects);

        $options = category_helper::build_parent_options($this->owner->id, 'Root');

        $this->assertSame('Root', $options[0]);
        $this->assertSame('Work', $options[$work]);
        $this->assertSame('Work &rarr; Projects', $options[$projects]);
        $this->assertSame('Work &rarr; Projects &rarr; Moodle', $options[$moodle]);
    }

    public function test_root_and_valid_parent_are_accepted(): void {
        $parent = $this->create_category($this->owner, 'Parent');

        $this->assertTrue(category_helper::is_valid_parent(0, $this->owner->id));
        $this->assertTrue(category_helper::is_valid_parent($parent, $this->owner->id));
        $this->assertSame($parent, category_helper::initial_parent_id($parent, $this->owner->id));
        $this->assertSame(0, category_helper::initial_parent_id(0, $this->owner->id));
        $this->assertSame(0, category_helper::initial_parent_id(999999, $this->owner->id));
    }

    public function test_foreign_parent_is_rejected(): void {
        $otheruser = $this->getDataGenerator()->create_user();
        $foreignparent = $this->create_category($otheruser, 'Foreign');

        $this->assertFalse(category_helper::is_valid_parent($foreignparent, $this->owner->id));
    }

    public function test_edit_parent_cannot_be_self_or_descendant(): void {
        $category = $this->create_category($this->owner, 'Category');
        $descendant = $this->create_category($this->owner, 'Descendant', $category);

        $this->assertFalse(category_helper::is_valid_parent($category, $this->owner->id, $category));
        $this->assertFalse(category_helper::is_valid_parent($descendant, $this->owner->id, $category));
    }

    // -------------------------------------------------------------------------
    // Tests: category_helper::build_share_info
    // -------------------------------------------------------------------------

    /**
     * shareall flag produces share->all === true.
     */
    public function test_shareall_flag(): void {
        $catid = $this->create_category($this->owner, internshare: 1, shareall: 1);
        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 1, 'externaccess' => 0];

        $share = category_helper::build_share_info($cat);

        $this->assertTrue($share->all);
        $this->assertEmpty($share->users);
        $this->assertEmpty($share->groups);
        $this->assertFalse($share->external);
    }

    /**
     * externaccess flag produces share->external === true.
     */
    public function test_externaccess_flag(): void {
        $catid = $this->create_category($this->owner, externaccess: 1);
        $cat = (object)['id' => $catid, 'internshare' => 0, 'shareall' => 0, 'externaccess' => 1];

        $share = category_helper::build_share_info($cat);

        $this->assertTrue($share->external);
        $this->assertEmpty($share->users);
        $this->assertEmpty($share->groups);
        $this->assertFalse($share->all);
    }

    /**
     * Category shared with only users → users populated, groups empty.
     */
    public function test_shared_with_users_only(): void {
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Test']);
        $catid  = $this->create_category($this->owner, internshare: 1);
        $this->share_with_user($catid, $user1->id);

        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 0, 'externaccess' => 0];
        $share = category_helper::build_share_info($cat);

        $this->assertNotEmpty($share->users, 'users should be populated');
        $this->assertEmpty($share->groups, 'groups should be empty');
        $this->assertFalse($share->all);
    }

    /**
     * Category shared with only cohorts → groups populated with cohort names, users empty.
     */
    public function test_shared_with_groups_only(): void {
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Gruppe A']);
        $catid  = $this->create_category($this->owner, internshare: 1);
        $this->share_category_with_cohort($catid, $cohort->id);

        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 0, 'externaccess' => 0];
        $share = category_helper::build_share_info($cat);

        $this->assertNotEmpty($share->groups, 'groups should be populated');
        $this->assertContains('Gruppe A', $share->groups);
        $this->assertEmpty($share->users, 'users should be empty');
        $this->assertFalse($share->all);
    }

    /**
     * Category shared with both users and cohorts → both arrays populated.
     */
    public function test_shared_with_both_users_and_groups(): void {
        $user1  = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Tester']);
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Gruppe B']);
        $catid  = $this->create_category($this->owner, internshare: 1);
        $this->share_with_user($catid, $user1->id);
        $this->share_category_with_cohort($catid, $cohort->id);

        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 0, 'externaccess' => 0];
        $share = category_helper::build_share_info($cat);

        $this->assertNotEmpty($share->users, 'users should be populated when both users and groups are set');
        $this->assertNotEmpty($share->groups, 'groups should be populated when both users and groups are set');
    }

    /**
     * Group names are resolved from {cohort}, not {groups} (regression test).
     *
     * Creates a cohort and a course group with a different name. Asserts the
     * result contains the cohort name and NOT the course-group name, even if
     * ids happen to collide.
     */
    public function test_group_names_resolved_from_cohort_table(): void {
        $course = $this->getDataGenerator()->create_course();
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'CohortName']);
        // Create a course group whose name differs from the cohort name.
        $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'CourseGroupName']);

        $catid = $this->create_category($this->owner, internshare: 1);
        $this->share_category_with_cohort($catid, $cohort->id);

        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 0, 'externaccess' => 0];
        $share = category_helper::build_share_info($cat);

        $this->assertContains('CohortName', $share->groups,
            'Group names must be resolved from {cohort} table.');
        $this->assertNotContains('CourseGroupName', $share->groups,
            'Course group names must NOT appear in share->groups.');
    }

    // -------------------------------------------------------------------------
    // Tests: view_helper sharing (exercised via load_flat_views)
    // -------------------------------------------------------------------------

    /**
     * View shared with cohorts only → $share->groups populated with cohort names.
     */
    public function test_view_shared_with_cohorts_only(): void {
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'ViewCohort']);
        $viewid = $this->create_view($this->owner);
        $this->share_view_with_cohort($viewid, $cohort->id);

        $views = view_helper::load_flat_views($this->owner->id, [], 'name', 'asc');
        $this->assertArrayHasKey($viewid, $views);
        $share = $views[$viewid]->shareinfo;

        $this->assertNotEmpty($share->groups);
        $this->assertContains('ViewCohort', $share->groups);
        $this->assertEmpty($share->users);
        $this->assertFalse($share->all);
    }

    /**
     * View shared with individual users only → $share->users populated.
     */
    public function test_view_shared_with_users_only(): void {
        $user1  = $this->getDataGenerator()->create_user(['firstname' => 'Carol', 'lastname' => 'Test']);
        $viewid = $this->create_view($this->owner);
        $this->share_view_with_user($viewid, $user1->id);

        $views = view_helper::load_flat_views($this->owner->id, [], 'name', 'asc');
        $this->assertArrayHasKey($viewid, $views);
        $share = $views[$viewid]->shareinfo;

        $this->assertNotEmpty($share->users);
        $this->assertEmpty($share->groups);
        $this->assertFalse($share->all);
    }

    /**
     * View shared with both users and cohorts → both arrays populated (regression for 2b).
     */
    public function test_view_shared_with_both_users_and_cohorts(): void {
        $user1  = $this->getDataGenerator()->create_user(['firstname' => 'Dave', 'lastname' => 'Test']);
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'BothCohort']);
        $viewid = $this->create_view($this->owner);
        $this->share_view_with_user($viewid, $user1->id);
        $this->share_view_with_cohort($viewid, $cohort->id);

        $views = view_helper::load_flat_views($this->owner->id, [], 'name', 'asc');
        $this->assertArrayHasKey($viewid, $views);
        $share = $views[$viewid]->shareinfo;

        $this->assertNotEmpty($share->users, 'users should be populated');
        $this->assertNotEmpty($share->groups, 'groups should be populated');
    }

    /**
     * View with shareall == 1 → $share->all === true.
     */
    public function test_view_shareall_flag(): void {
        $viewid = $this->create_view($this->owner, 1);

        $views = view_helper::load_flat_views($this->owner->id, [], 'name', 'asc');
        $this->assertArrayHasKey($viewid, $views);
        $share = $views[$viewid]->shareinfo;

        $this->assertTrue($share->all);
    }

    /**
     * View with externaccess set → $share->external === true.
     */
    public function test_view_externaccess_flag(): void {
        $viewid = $this->create_view($this->owner, 0, 1);

        $views = view_helper::load_flat_views($this->owner->id, [], 'name', 'asc');
        $this->assertArrayHasKey($viewid, $views);
        $share = $views[$viewid]->shareinfo;

        $this->assertTrue($share->external);
    }

    // -------------------------------------------------------------------------
    // Tests: block_exaport_get_share_tooltip (HTML variant)
    // -------------------------------------------------------------------------

    /**
     * HTML tooltip contains <br><br> separators.
     */
    public function test_html_tooltip_has_br_separators(): void {
        $share = new share_info();
        $share->all = true;
        $share->external = true;

        $tooltip = block_exaport_get_share_tooltip($share);

        $this->assertStringContainsString('<br><br>', $tooltip);
    }

    /**
     * HTML tooltip escapes names containing HTML special characters.
     */
    public function test_html_tooltip_escapes_special_chars(): void {
        $share = new share_info();
        $share->users = ['Alice <Test> & Co'];

        $tooltip = block_exaport_get_share_tooltip($share);

        $this->assertStringContainsString('&lt;Test&gt;', $tooltip);
        $this->assertStringContainsString('&amp;', $tooltip);
        $this->assertStringNotContainsString('<Test>', $tooltip);
    }

    // -------------------------------------------------------------------------
    // Tests: block_exaport_get_share_tooltip($share, false) — plain-text variant
    // -------------------------------------------------------------------------

    /**
     * Plain-text tooltip contains no HTML tags.
     */
    public function test_text_tooltip_has_no_html_tags(): void {
        $share = new share_info();
        $share->all = true;
        $share->external = true;

        $tooltip = block_exaport_get_share_tooltip($share, false);

        $this->assertStringNotContainsString('<br>', $tooltip);
        $this->assertStringNotContainsString('<', $tooltip);
    }

    /**
     * Plain-text tooltip does not escape special characters (no &amp; etc).
     */
    public function test_text_tooltip_no_escaped_entities(): void {
        $share = new share_info();
        $share->users = ['Alice & Bob'];

        $tooltip = block_exaport_get_share_tooltip($share, false);

        $this->assertStringContainsString('Alice & Bob', $tooltip);
        $this->assertStringNotContainsString('&amp;', $tooltip);
    }
}
