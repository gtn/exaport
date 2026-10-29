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

    public static function incomplete_forms(): array {
        return ['missing marker' => [null], 'loading or failed' => [0]];
    }

    #[DataProvider('incomplete_forms')]
    public function test_incomplete_individual_picker_is_rejected(?int $ready): void {
        unset($_POST['exaport_userlist_ready'], $_GET['exaport_userlist_ready']);
        if ($ready !== null) {
            $_POST['exaport_userlist_ready'] = $ready;
        }
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('sharing_users_not_ready', 'block_exaport'));
        block_exaport_require_loaded_share_users(true, 0);
    }

    public function test_ready_marker_is_only_needed_when_saving_individual_user_shares(): void {
        $_POST['exaport_userlist_ready'] = 0;
        block_exaport_require_loaded_share_users(false, 0);
        block_exaport_require_loaded_share_users(true, 1);
        block_exaport_require_loaded_share_users(true, 2);
        $_POST['exaport_userlist_ready'] = 1;
        block_exaport_require_loaded_share_users(true, 0);
        $this->assertTrue(true);
    }

    public function test_invalid_mode_is_checked_like_the_normalized_user_mode(): void {
        $_POST['exaport_userlist_ready'] = 0;
        $this->expectException(\moodle_exception::class);
        block_exaport_require_loaded_share_users(true, -1);
    }
}
