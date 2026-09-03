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
 * Tests for the "share to users" course helpers used by the userlist AJAX dialog:
 *   - exaport_get_shareable_courses_list()
 *   - exaport_get_courseids_for_shared_users()
 *   - exaport_get_course_shareable_users()
 *   - exaport_get_shareable_courses_with_users() (still used by shared_views.php/
 *     shared_categories.php and must keep behaving exactly like before)
 *
 * These cover the split of exaport_get_shareable_courses_with_users() into a cheap
 * "courses only" listing plus a per-course user lookup, done so the interactive userlist
 * dialog no longer needs to call get_role_users() for every enrolled course up front.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shareable_courses_test extends \advanced_testcase {

    /** @var \stdClass Current user, enrolled as student in $this->course1 and $this->course2. */
    private \stdClass $user;

    /** @var \stdClass Another enrolled user, sharing target in the tests below. */
    private \stdClass $otheruser;

    /** @var \stdClass Course the user is enrolled in, with $otheruser also enrolled. */
    private \stdClass $course1;

    /** @var \stdClass A second course the user is enrolled in, with nobody else enrolled. */
    private \stdClass $course2;

    protected function setUp(): void {
        global $DB;

        $this->resetAfterTest(true);

        $this->user = $this->getDataGenerator()->create_user();
        $this->otheruser = $this->getDataGenerator()->create_user();
        $this->course1 = $this->getDataGenerator()->create_course();
        $this->course2 = $this->getDataGenerator()->create_course();

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course2->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->otheruser->id, $this->course1->id, $studentrole->id);

        $this->setUser($this->user);
    }

    /**
     * The cheap course list contains every enrolled course, without has_shared_users set,
     * when nothing has been shared yet.
     */
    public function test_shareable_courses_list_contains_all_enrolled_courses(): void {
        $courses = exaport_get_shareable_courses_list();

        $this->assertArrayHasKey($this->course1->id, $courses);
        $this->assertArrayHasKey($this->course2->id, $courses);
        $this->assertSame($this->course1->fullname, $courses[$this->course1->id]->fullname);
        $this->assertFalse($courses[$this->course1->id]->has_shared_users);
        $this->assertFalse($courses[$this->course2->id]->has_shared_users);

        // Only cheap fields are present, resolving users is left to
        // exaport_get_course_shareable_users() so the dialog can fetch them lazily.
        $this->assertFalse(property_exists($courses[$this->course1->id], 'users'));
    }

    /**
     * exaport_get_courseids_for_shared_users() finds the (subset of) courses a given user is
     * enrolled in, without ever resolving a course's full user list.
     */
    public function test_courseids_for_shared_users_finds_enrolled_courses(): void {
        $result = exaport_get_courseids_for_shared_users(
            [$this->otheruser->id],
            [$this->course1->id, $this->course2->id]
        );

        $this->assertArrayHasKey($this->otheruser->id, $result);
        $this->assertSame([$this->course1->id], $result[$this->otheruser->id]);
    }

    /**
     * A user not enrolled in any of the given courses is simply absent from the result, so the
     * caller can treat them as an "extra user" (shared, but not in any of the owner's courses).
     */
    public function test_courseids_for_shared_users_ignores_unrelated_users(): void {
        $unrelateduser = $this->getDataGenerator()->create_user();

        $result = exaport_get_courseids_for_shared_users(
            [$unrelateduser->id],
            [$this->course1->id, $this->course2->id]
        );

        $this->assertArrayNotHasKey($unrelateduser->id, $result);
    }

    /**
     * Empty inputs are handled cheaply (no query at all) instead of erroring on an empty
     * IN (...) clause.
     */
    public function test_courseids_for_shared_users_handles_empty_input(): void {
        $this->assertSame([], exaport_get_courseids_for_shared_users([], [$this->course1->id]));
        $this->assertSame([], exaport_get_courseids_for_shared_users([$this->otheruser->id], []));
    }

    /**
     * exaport_get_course_shareable_users() resolves exactly one course's users, excluding the
     * current user themselves.
     */
    public function test_course_shareable_users_excludes_self_and_includes_others(): void {
        $users = exaport_get_course_shareable_users($this->course1->id);

        $this->assertArrayHasKey($this->otheruser->id, $users);
        $this->assertArrayNotHasKey($this->user->id, $users);
        $this->assertSame(fullname($this->otheruser), $users[$this->otheruser->id]->name);
    }

    /**
     * exaport_get_shareable_courses_with_users() - used by shared_views.php/
     * shared_categories.php - still eagerly returns every enrolled course together with its
     * users, unchanged from before the lazy-loading split.
     */
    public function test_shareable_courses_with_users_still_eager_for_legacy_callers(): void {
        $courses = exaport_get_shareable_courses_with_users('shared_views');

        $this->assertArrayHasKey($this->course1->id, $courses);
        $this->assertArrayHasKey($this->course2->id, $courses);
        $this->assertArrayHasKey($this->otheruser->id, $courses[$this->course1->id]->users);
        $this->assertSame([], $courses[$this->course2->id]->users);
    }
}
