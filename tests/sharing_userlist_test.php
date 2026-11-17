<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/sharelib.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A complete saved selection must load independently of the size of course rosters.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharing_userlist_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

    private \stdClass $owner;
    private \stdClass $recipient;
    private \stdClass $outside;
    private \stdClass $unselected;
    private \stdClass $coursea;
    private \stdClass $courseb;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $this->owner = $generator->create_user();
        $this->recipient = $generator->create_user();
        $this->outside = $generator->create_user();
        $this->unselected = $generator->create_user();
        $this->coursea = $generator->create_course();
        $this->courseb = $generator->create_course();
        foreach ([$this->coursea, $this->courseb] as $course) {
            $generator->enrol_user($this->owner->id, $course->id, 'student');
            $generator->enrol_user($this->recipient->id, $course->id, 'student');
            $generator->enrol_user($this->unselected->id, $course->id, 'student');
        }
        $generator->create_block('exaport', ['parentcontextid' => \context_course::instance($this->coursea->id)->id]);
        $this->setUser($this->owner);
    }

    private function entity_id(string $type): int {
        global $DB;
        if ($type === 'category') {
            return $this->create_category($this->owner, 'Shared category', 0, 1);
        }
        if ($type === 'view') {
            return $this->create_view($this->owner);
        }
        return $DB->insert_record('block_exaportitem', [
            'userid' => $this->owner->id, 'name' => 'Shared item', 'type' => 'note',
            'intro' => '', 'courseid' => $this->coursea->id, 'timemodified' => time(),
        ]);
    }

    public static function entity_and_filter(): array {
        $cases = [];
        foreach (['category', 'view', 'item'] as $type) {
            foreach ([false, true] as $filter) {
                $cases[$type . ($filter ? '-filtered' : '-all-courses')] = [$type, $filter];
            }
        }
        return $cases;
    }

    /** @dataProvider entity_and_filter */
    #[DataProvider('entity_and_filter')]
    public function test_initial_payload_contains_every_selected_user_but_no_full_roster(string $type, bool $filter): void {
        global $DB;
        set_config('sharing_courses_with_block_only', $filter, 'block_exaport');
        $id = $this->entity_id($type);
        $config = block_exaport_get_sharing_entity_config($type);
        sharing_service::save_internal_shares($type, $id, true, 0,
            [$this->recipient->id, $this->outside->id], [$this->recipient->id]);
        $before = $DB->get_records($config->usersharetable, [$config->idfield => $id], 'userid');

        $courses = array_column(block_exaport_get_sharing_userlist($type, $id), null, 'id');
        $this->assertSame(!$filter, isset($courses[$this->courseb->id]));
        $this->assertFalse($courses[$this->coursea->id]->users_complete);
        $this->assertTrue($courses[$this->coursea->id]->has_shared_users);
        $this->assertEquals([$this->recipient->id], array_column($courses[$this->coursea->id]->users, 'id'));
        $row = $courses[$this->coursea->id]->users[0];
        $this->assertSame('student', $row->rolename);
        $this->assertTrue($row->shared_to);
        $this->assertTrue($row->notify_user);
        $this->assertTrue($courses[-1]->users_complete);
        $this->assertEquals([$this->outside->id], array_column($courses[-1]->users, 'id'));
        $this->assertFalse($courses[-1]->users[0]->notify_user);

        // This is what the partial tables submit, without any full-roster requests.
        $selected = [];
        $notify = [];
        foreach ($courses as $course) {
            foreach ($course->users ?? [] as $user) {
                $this->assertNotEquals($this->unselected->id, $user->id);
                $selected[] = $user->id;
                if ($user->notify_user) {
                    $notify[] = $user->id;
                }
            }
        }
        sharing_service::save_internal_shares($type, $id, true, 0, $selected, $notify);
        $this->assertEquals($before, $DB->get_records($config->usersharetable, [$config->idfield => $id], 'userid'));
        $this->assertArrayHasKey($this->unselected->id, exaport_get_course_shareable_users($this->coursea->id));
    }

    public function test_new_entity_and_unshared_courses_do_not_embed_participants(): void {
        foreach (block_exaport_get_sharing_userlist('category', 0) as $course) {
            $this->assertFalse($course->has_shared_users);
            $this->assertObjectNotHasProperty('users', $course);
        }
    }

    /** Flatten the selected-only course groups without counting repeated course membership twice. */
    private function selected_users(array $courses): array {
        $users = [];
        foreach ($courses as $course) {
            foreach ($course->users ?? [] as $user) {
                $users[$user->id] = $user;
            }
        }
        return $users;
    }

    public function test_new_item_override_preserves_selected_users_and_roles_without_saving(): void {
        global $DB;
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');
        $writes = $DB->perf_get_writes();
        $courses = array_column(block_exaport_get_sharing_userlist('item', 0,
            [$this->recipient->id, (string)$this->recipient->id, $this->outside->id], [$this->recipient->id]), null, 'id');
        $this->assertEquals([$this->recipient->id], array_column($courses[$this->coursea->id]->users, 'id'));
        $this->assertSame('student', $courses[$this->coursea->id]->users[0]->rolename);
        $this->assertFalse($courses[$this->coursea->id]->users_complete);
        $this->assertArrayNotHasKey($this->courseb->id, $courses);
        $this->assertEquals([$this->outside->id], array_column($courses[-1]->users, 'id'));
        $selected = $this->selected_users($courses);
        $this->assertCount(2, $selected);
        $this->assertTrue($selected[$this->recipient->id]->notify_user);
        $this->assertFalse($selected[$this->outside->id]->notify_user);
        $this->assertArrayNotHasKey($this->unselected->id, $selected);
        $this->assertSame($writes, $DB->perf_get_writes());
        $this->assertFalse($DB->record_exists('block_exaportitemshar', []));
        $this->assertFalse($DB->record_exists('block_exaportitem', []));
    }

    public function test_existing_override_replaces_displayed_recipients_and_notify_flags_only(): void {
        global $DB;
        $id = $this->entity_id('item');
        sharing_service::save_internal_shares('item', $id, true, 0,
            [$this->recipient->id, $this->outside->id], [$this->recipient->id]);
        $before = $DB->get_records('block_exaportitemshar', ['itemid' => $id], 'userid');
        $writes = $DB->perf_get_writes();
        $selected = $this->selected_users(block_exaport_get_sharing_userlist('item', $id,
            [$this->outside->id, $this->unselected->id], [$this->outside->id, $this->recipient->id]));
        $this->assertEqualsCanonicalizing([$this->outside->id, $this->unselected->id], array_keys($selected));
        $this->assertTrue($selected[$this->outside->id]->notify_user);
        $this->assertFalse($selected[$this->unselected->id]->notify_user);
        $this->assertSame($writes, $DB->perf_get_writes());
        $this->assertEquals($before, $DB->get_records('block_exaportitemshar', ['itemid' => $id], 'userid'));
        // The ordinary endpoint/default call still reads the real saved selection.
        $saved = $this->selected_users(block_exaport_get_sharing_userlist('item', $id));
        $this->assertEqualsCanonicalizing([$this->recipient->id, $this->outside->id], array_keys($saved));
        $this->assertTrue($saved[$this->recipient->id]->notify_user);
        $this->assertFalse($saved[$this->outside->id]->notify_user);
    }

    public function test_an_explicit_empty_override_does_not_restore_saved_recipients(): void {
        global $DB;
        $id = $this->entity_id('category');
        sharing_service::save_internal_shares('category', $id, true, 0, [$this->recipient->id]);
        $before = $DB->get_records('block_exaportcatshar', ['catid' => $id]);
        $writes = $DB->perf_get_writes();
        $courses = block_exaport_get_sharing_userlist('category', $id, [], [$this->recipient->id]);
        $this->assertSame([], $this->selected_users($courses));
        foreach ($courses as $course) {
            $this->assertFalse($course->has_shared_users);
        }
        $this->assertSame($writes, $DB->perf_get_writes());
        $this->assertEquals($before, $DB->get_records('block_exaportcatshar', ['catid' => $id]));
    }

    public function test_foreign_entity_overrides_do_not_bypass_the_existing_owner_check(): void {
        global $DB;
        foreach (['category', 'view', 'item'] as $type) {
            $this->setUser($this->owner);
            $id = $this->entity_id($type);
            $config = block_exaport_get_sharing_entity_config($type);
            sharing_service::save_internal_shares($type, $id, true, 0, [$this->outside->id]);
            $before = $DB->get_records($config->usersharetable, [$config->idfield => $id]);
            $this->setUser($this->recipient);
            $this->assertSame([], $this->selected_users(block_exaport_get_sharing_userlist(
                $type, $id, [$this->outside->id], [$this->outside->id])));
            $this->assertEquals($before, $DB->get_records($config->usersharetable, [$config->idfield => $id]));
        }
    }

    public function test_an_override_still_requires_internal_sharing_capability(): void {
        $this->setGuestUser();
        $this->expectException(\required_capability_exception::class);
        block_exaport_get_sharing_userlist('item', 0, [$this->recipient->id]);
    }

    public function test_override_names_are_escaped_and_unknown_or_deleted_users_are_omitted(): void {
        global $DB;
        $DB->set_field('user', 'firstname', '<img src=x onerror=alert(1)>', ['id' => $this->outside->id]);
        $deleted = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);
        $selected = $this->selected_users(block_exaport_get_sharing_userlist('item', 0,
            [$this->outside->id, (string)$this->outside->id, $deleted->id, PHP_INT_MAX, 0, -1, 'invalid',
                [$this->recipient->id]], [$this->outside->id, $deleted->id]));
        $this->assertSame([(int)$this->outside->id], array_keys($selected));
        $this->assertStringContainsString('&lt;img', $selected[$this->outside->id]->name);
        $this->assertStringNotContainsString('<img', $selected[$this->outside->id]->name);
        $this->assertTrue($selected[$this->outside->id]->notify_user);
    }

    public static function incomplete_forms(): array {
        return ['missing marker' => [null], 'loading or failed' => [0]];
    }

    /** @dataProvider incomplete_forms */
    #[DataProvider('incomplete_forms')]
    public function test_incomplete_individual_picker_is_rejected(?int $ready): void {
        unset($_POST['exaport_userlist_ready'], $_GET['exaport_userlist_ready']);
        if ($ready !== null) {
            $_POST['exaport_userlist_ready'] = $ready;
        }
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('sharing_users_not_ready', 'block_exaport'));
        block_exaport_require_loaded_share_selection(true, 0);
    }

    /** @dataProvider incomplete_forms */
    #[DataProvider('incomplete_forms')]
    public function test_an_unknown_active_group_selection_is_rejected_without_changes(?int $ready): void {
        global $DB;
        set_config('block_exaport_disable_sharegroups', 0);
        $id = $this->entity_id('item');
        $group = $this->getDataGenerator()->create_cohort();
        cohort_add_member($group->id, $this->owner->id);
        sharing_service::save_internal_shares('item', $id, true, 2, [], [], [$group->id]);
        $beforeitem = $DB->get_record('block_exaportitem', ['id' => $id]);
        $beforegroups = $DB->get_records('block_exaportitemgroupshar', ['itemid' => $id]);
        unset($_POST['exaport_grouplist_ready'], $_GET['exaport_grouplist_ready']);
        if ($ready !== null) {
            $_POST['exaport_grouplist_ready'] = $ready;
        }
        $writes = $DB->perf_get_writes();
        try {
            block_exaport_require_loaded_share_selection(true, 2);
            $this->fail('An unknown group selection was accepted as an empty audience');
        } catch (\moodle_exception $error) {
            $this->assertSame('sharing_groups_not_ready', $error->errorcode);
        }
        $this->assertSame($writes, $DB->perf_get_writes());
        $this->assertEquals($beforeitem, $DB->get_record('block_exaportitem', ['id' => $id]));
        $this->assertEquals($beforegroups, $DB->get_records('block_exaportitemgroupshar', ['itemid' => $id]));
    }

    public function test_a_ready_empty_group_selection_can_intentionally_remove_existing_groups(): void {
        global $DB;
        set_config('block_exaport_disable_sharegroups', 0);
        $id = $this->entity_id('item');
        $group = $this->getDataGenerator()->create_cohort();
        cohort_add_member($group->id, $this->owner->id);
        sharing_service::save_internal_shares('item', $id, true, 2, [], [], [$group->id]);
        $_POST['exaport_grouplist_ready'] = 1;
        block_exaport_require_loaded_share_selection(true, 2);
        sharing_service::save_internal_shares('item', $id, true, 2, [], [], []);
        $this->assertFalse($DB->record_exists('block_exaportitemgroupshar', ['itemid' => $id]));
    }

    public function test_only_the_active_enabled_picker_requires_a_ready_marker(): void {
        $_POST['exaport_userlist_ready'] = 0;
        $_POST['exaport_grouplist_ready'] = 0;
        block_exaport_require_loaded_share_selection(false, 0);
        block_exaport_require_loaded_share_selection(false, 2);
        block_exaport_require_loaded_share_selection(true, 1);
        $_POST['exaport_userlist_ready'] = 1;
        block_exaport_require_loaded_share_selection(true, 0);
        $_POST['exaport_grouplist_ready'] = 1;
        $_POST['exaport_userlist_ready'] = 0;
        block_exaport_require_loaded_share_selection(true, 2);
        $this->assertTrue(true);
    }

    public function test_disabled_group_policy_is_not_blocked_and_still_preserves_existing_groups(): void {
        global $DB;
        set_config('block_exaport_disable_sharegroups', 0);
        $id = $this->entity_id('item');
        $group = $this->getDataGenerator()->create_cohort();
        cohort_add_member($group->id, $this->owner->id);
        sharing_service::save_internal_shares('item', $id, true, 2, [], [], [$group->id]);
        $before = $DB->get_records('block_exaportitemgroupshar', ['itemid' => $id]);
        set_config('block_exaport_disable_sharegroups', 1);
        $_POST['exaport_grouplist_ready'] = 0;
        block_exaport_require_loaded_share_selection(true, 2);
        sharing_service::save_internal_shares('item', $id, true, 2, [], [], []);
        $this->assertEquals($before, $DB->get_records('block_exaportitemgroupshar', ['itemid' => $id]));
    }

    public function test_readiness_does_not_grant_permission_to_change_a_foreign_entity(): void {
        global $DB;
        $id = $this->entity_id('item');
        $before = $DB->get_record('block_exaportitem', ['id' => $id]);
        $this->setUser($this->recipient);
        $_POST['exaport_grouplist_ready'] = 1;
        block_exaport_require_loaded_share_selection(true, 2);
        try {
            sharing_service::save_internal_shares('item', $id, true, 2);
            $this->fail('A ready picker bypassed ownership checks');
        } catch (\moodle_exception $error) {
            $this->assertSame('bookmarknotfound', $error->errorcode);
        }
        $this->assertEquals($before, $DB->get_record('block_exaportitem', ['id' => $id]));
    }

    public function test_users_without_sharing_capability_are_not_blocked_by_picker_readiness(): void {
        $this->setGuestUser();
        $_POST['exaport_userlist_ready'] = 0;
        $_POST['exaport_grouplist_ready'] = 0;
        block_exaport_require_loaded_share_selection(true, 0);
        block_exaport_require_loaded_share_selection(true, 2);
        $this->assertTrue(true);
    }

    public function test_invalid_mode_is_checked_like_the_normalized_user_mode(): void {
        $_POST['exaport_userlist_ready'] = 0;
        $this->expectException(\moodle_exception::class);
        block_exaport_require_loaded_share_selection(true, -1);
    }
}
