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

/**
 * Tests for category_helper::build_share_info() and block_exaport_get_share_tooltip*().
 *
 * @package    block_exaport
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_helper_test extends \advanced_testcase {

    /** @var \stdClass Owner user. */
    private \stdClass $owner;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Insert a category owned by the given user and return its id.
     *
     * @param \stdClass $user
     * @param int $internshare 1 = internally shared, 0 = not.
     * @param int $shareall 1 = share-all, 0 = not.
     * @param int $externaccess 1 = externally shared, 0 = not.
     * @return int
     */
    private function create_category(\stdClass $user, int $internshare = 0,
                                     int $shareall = 0, int $externaccess = 0): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportcate', (object)[
            'userid'           => $user->id,
            'pid'              => 0,
            'name'             => 'Cat',
            'timemodified'     => time(),
            'courseid'         => 0,
            'description'      => '',
            'subjid'           => 0,
            'topicid'          => 0,
            'source'           => 0,
            'sourceid'         => 0,
            'isoez'            => 0,
            'sortorder'        => 0,
            'internshare'      => $internshare,
            'shareall'         => $shareall,
            'structure_shareall' => 0,
            'structure_share'  => 0,
            'iconmerge'        => 0,
            'creatorid'        => $user->id,
            'externaccess'     => $externaccess,
        ]);
    }

    /**
     * Share a category with a Moodle group.
     *
     * @param int $catid
     * @param int $groupid
     */
    private function share_with_group(int $catid, int $groupid): void {
        global $DB;
        $DB->insert_record('block_exaportcatgroupshar', (object)[
            'catid'   => $catid,
            'groupid' => $groupid,
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

    // -------------------------------------------------------------------------
    // Tests: build_share_info
    // -------------------------------------------------------------------------

    /**
     * shareall flag produces share->all === true.
     */
    public function test_shareall_flag(): void {
        $catid = $this->create_category($this->owner, 1, 1);
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
        $catid = $this->create_category($this->owner, 0, 0, 1);
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
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Test']);
        $catid  = $this->create_category($this->owner, 1, 0);
        $this->share_with_user($catid, $user1->id);

        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 0, 'externaccess' => 0];
        $share = category_helper::build_share_info($cat);

        $this->assertNotEmpty($share->users, 'users should be populated');
        $this->assertEmpty($share->groups, 'groups should be empty');
        $this->assertFalse($share->all);
    }

    /**
     * Category shared with only groups → groups populated with names from {groups}, users empty.
     */
    public function test_shared_with_groups_only(): void {
        $course = $this->getDataGenerator()->create_course();
        $group  = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Gruppe A']);
        $catid  = $this->create_category($this->owner, 1, 0);
        $this->share_with_group($catid, $group->id);

        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 0, 'externaccess' => 0];
        $share = category_helper::build_share_info($cat);

        $this->assertNotEmpty($share->groups, 'groups should be populated');
        $this->assertContains('Gruppe A', $share->groups);
        $this->assertEmpty($share->users, 'users should be empty');
        $this->assertFalse($share->all);
    }

    /**
     * Category shared with both users and groups → both arrays populated (regression for Problem 2).
     */
    public function test_shared_with_both_users_and_groups(): void {
        $course = $this->getDataGenerator()->create_course();
        $user1  = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Tester']);
        $group  = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Gruppe B']);
        $catid  = $this->create_category($this->owner, 1, 0);
        $this->share_with_user($catid, $user1->id);
        $this->share_with_group($catid, $group->id);

        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 0, 'externaccess' => 0];
        $share = category_helper::build_share_info($cat);

        $this->assertNotEmpty($share->users, 'users should be populated when both users and groups are set');
        $this->assertNotEmpty($share->groups, 'groups should be populated when both users and groups are set');
    }

    /**
     * Group names come from {groups}, not {cohort} (regression for Problem 1).
     *
     * Creates a group and a cohort with identical ids (by inserting in a way
     * that gives the group a known id), then verifies the resolved name is
     * the group name, not the cohort name.
     */
    public function test_group_names_resolved_from_groups_table(): void {
        $course = $this->getDataGenerator()->create_course();
        $group  = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'CorrectGroupName']);
        $catid  = $this->create_category($this->owner, 1, 0);
        $this->share_with_group($catid, $group->id);

        $cat = (object)['id' => $catid, 'internshare' => 1, 'shareall' => 0, 'externaccess' => 0];
        $share = category_helper::build_share_info($cat);

        $this->assertContains('CorrectGroupName', $share->groups,
            'Group name must be resolved from {groups} table.');
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
    // Tests: block_exaport_get_share_tooltip_text (plain-text variant)
    // -------------------------------------------------------------------------

    /**
     * Plain-text tooltip contains no HTML tags.
     */
    public function test_text_tooltip_has_no_html_tags(): void {
        $share = new share_info();
        $share->all = true;
        $share->external = true;

        $tooltip = block_exaport_get_share_tooltip_text($share);

        $this->assertStringNotContainsString('<br>', $tooltip);
        $this->assertStringNotContainsString('<', $tooltip);
    }

    /**
     * Plain-text tooltip does not escape special characters (no &amp; etc).
     */
    public function test_text_tooltip_no_escaped_entities(): void {
        $share = new share_info();
        $share->users = ['Alice & Bob'];

        $tooltip = block_exaport_get_share_tooltip_text($share);

        $this->assertStringContainsString('Alice & Bob', $tooltip);
        $this->assertStringNotContainsString('&amp;', $tooltip);
    }
}
