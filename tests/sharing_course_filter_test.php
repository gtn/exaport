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
 * Course filtering changes recipient browsing, never the audience or saved assignments.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharing_course_filter_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

    private \stdClass $owner;
    private \stdClass $includedcourse;
    private \stdClass $excludedcourse;
    private \stdClass $includeduser;
    private \stdClass $excludeduser;
    private \stdClass $outsideuser;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $this->owner = $generator->create_user();
        $this->includeduser = $generator->create_user();
        $this->excludeduser = $generator->create_user();
        $this->outsideuser = $generator->create_user();
        $this->includedcourse = $generator->create_course();
        $this->excludedcourse = $generator->create_course();
        $this->add_block(\context_course::instance($this->includedcourse->id));
        foreach ([$this->includedcourse, $this->excludedcourse] as $course) {
            $generator->enrol_user($this->owner->id, $course->id, 'student');
            // Appearing in both courses must not duplicate this user in "Other users".
            $generator->enrol_user($this->includeduser->id, $course->id, 'student');
        }
        $generator->enrol_user($this->excludeduser->id, $this->excludedcourse->id, 'student');
        $this->setUser($this->owner);
    }

    private function add_block(\context $context, string $name = 'exaport'): \stdClass {
        return $this->getDataGenerator()->create_block($name, ['parentcontextid' => $context->id]);
    }

    public static function entity_types(): array {
        return ['view' => ['view'], 'category' => ['category'], 'item' => ['item']];
    }

    private function entity_id(string $type): int {
        global $DB;
        if ($type === 'view') {
            return $this->create_view($this->owner);
        }
        if ($type === 'category') {
            return $this->create_category($this->owner, 'Shared category', 0, 1);
        }
        return $DB->insert_record('block_exaportitem', [
            'userid' => $this->owner->id, 'name' => 'Shared item', 'type' => 'note',
            'intro' => '', 'courseid' => $this->includedcourse->id, 'timemodified' => time(),
        ]);
    }

    public function test_filter_defaults_off_and_can_be_disabled_again(): void {
        $this->assertEmpty(get_config('block_exaport', 'sharing_courses_with_block_only'));
        $this->assertCount(2, exaport_get_shareable_courses_list());
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');
        $courses = exaport_get_shareable_courses_list();
        $this->assertSame([(int)$this->includedcourse->id], array_keys($courses));
        $this->assertFalse($courses[$this->includedcourse->id]->has_shared_users);
        $this->assertObjectNotHasProperty('users', $courses[$this->includedcourse->id]);
        set_config('sharing_courses_with_block_only', 0, 'block_exaport');
        $this->assertCount(2, exaport_get_shareable_courses_list());
    }

    public function test_only_direct_course_blocks_count_and_enrolment_is_still_required(): void {
        global $DB;
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');
        $inherited = $this->add_block(\context_system::instance());
        $DB->set_field('block_instances', 'showinsubcontexts', 1, ['id' => $inherited->id]);
        $this->add_block(\context_user::instance($this->owner->id));
        $this->add_block(\context_course::instance($this->excludedcourse->id), 'html');
        $unrelated = $this->getDataGenerator()->create_course();
        $this->add_block(\context_course::instance($unrelated->id));
        // Multiple and hidden direct blocks still represent a single qualifying course.
        $this->add_block(\context_course::instance($this->includedcourse->id));
        $contextid = \context_course::instance($this->includedcourse->id)->id;
        foreach ($DB->get_records('block_instances', ['parentcontextid' => $contextid]) as $block) {
            $DB->insert_record('block_positions', [
                'blockinstanceid' => $block->id, 'contextid' => $contextid,
                'pagetype' => 'course-view-topics', 'subpage' => '', 'visible' => 0,
                'region' => 'side-pre', 'weight' => 0,
            ]);
        }
        $this->assertSame([(int)$this->includedcourse->id], array_keys(exaport_get_shareable_courses_list()));
        $DB->delete_records('block_instances', ['parentcontextid' =>
            \context_course::instance($this->includedcourse->id)->id, 'blockname' => 'exaport']);
        $this->assertSame([], exaport_get_shareable_courses_list());
    }

    public function test_filter_does_not_hide_existing_shared_content_course_groups(): void {
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');
        // This legacy helper groups received content, not the recipient picker.
        foreach (['shared_views', 'shared_categories'] as $page) {
            $courses = exaport_get_shareable_courses_with_users($page);
            $this->assertArrayHasKey($this->excludedcourse->id, $courses);
        }
    }

    #[DataProvider('entity_types')]
    public function test_excluded_recipients_and_notifications_survive_save_and_can_be_removed(string $type): void {
        global $DB;
        $id = $this->entity_id($type);
        $config = block_exaport_get_sharing_entity_config($type);
        $recipients = [$this->includeduser->id, $this->excludeduser->id, $this->outsideuser->id];
        $notify = $config->supportsnotify ? [$this->excludeduser->id] : [];
        sharing_service::save_internal_shares($type, $id, true, 0, $recipients, $notify);
        $before = $DB->get_records($config->usersharetable, [$config->idfield => $id], 'userid');
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');

        $courses = block_exaport_get_sharing_userlist($type, $id);
        $this->assertEquals([-1, $this->includedcourse->id], array_column($courses, 'id'));
        $this->assertSame(get_string('other_shared_users_course', 'block_exaport'), $courses[0]->fullname);
        $this->assertTrue($courses[1]->has_shared_users);
        $otherusers = array_column($courses[0]->users, null, 'id');
        $this->assertEqualsCanonicalizing([$this->excludeduser->id, $this->outsideuser->id], array_keys($otherusers));
        $this->assertTrue($otherusers[$this->excludeduser->id]->shared_to);
        $this->assertSame($config->supportsnotify, $otherusers[$this->excludeduser->id]->notify_user);
        $this->assertFalse($otherusers[$this->outsideuser->id]->notify_user);

        // Submit exactly the checked recipients and notify controls represented by the picker.
        $checked = [$this->includeduser->id];
        $notifications = [];
        foreach ($courses[0]->users as $user) {
            $checked[] = $user->id;
            if ($user->notify_user) {
                $notifications[] = $user->id;
            }
        }
        sharing_service::save_internal_shares($type, $id, true, 0, $checked, $notifications);
        $this->assertEquals($before, $DB->get_records($config->usersharetable, [$config->idfield => $id], 'userid'));
        sharing_service::save_internal_shares($type, $id, true, 0,
            [$this->includeduser->id, $this->outsideuser->id]);
        $this->assertFalse($DB->record_exists($config->usersharetable,
            [$config->idfield => $id, 'userid' => $this->excludeduser->id]));
        $this->assertTrue($DB->record_exists($config->usersharetable,
            [$config->idfield => $id, 'userid' => $this->outsideuser->id]));
    }

    #[DataProvider('entity_types')]
    public function test_all_filtered_courses_still_show_existing_recipients_once(string $type): void {
        global $DB;
        $id = $this->entity_id($type);
        sharing_service::save_internal_shares($type, $id, true, 0, [$this->includeduser->id, $this->excludeduser->id]);
        $DB->delete_records('block_instances', ['blockname' => 'exaport']);
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');
        $courses = block_exaport_get_sharing_userlist($type, $id);
        $this->assertCount(1, $courses);
        $this->assertEquals(-1, $courses[0]->id);
        $this->assertEqualsCanonicalizing([$this->includeduser->id, $this->excludeduser->id],
            array_column($courses[0]->users, 'id'));
        $this->assertSame([], block_exaport_get_sharing_userlist($type, 0));
    }

    #[DataProvider('entity_types')]
    public function test_filter_does_not_limit_global_search_shares_or_share_all(string $type): void {
        $id = $this->entity_id($type);
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');
        $config = block_exaport_get_sharing_entity_config($type);
        // The global search uses this helper; a recipient needs no qualifying course.
        block_exaport_sharing_toggle_shared_users($config, $id, [$this->outsideuser->id => $this->outsideuser->id]);
        $this->setUser($this->outsideuser);
        $rows = share_overview::get_shared_with_me($this->outsideuser->id);
        $this->assertNotEmpty(array_filter($rows, fn($row) => $row->entity_type === $type && (int)$row->id === $id));
        $this->setUser($this->owner);
        sharing_service::save_internal_shares($type, $id, true, 1);
        $this->setUser($this->excludeduser);
        $rows = share_overview::get_shared_with_me($this->excludeduser->id);
        $this->assertNotEmpty(array_filter($rows, fn($row) => $row->entity_type === $type && (int)$row->id === $id));
    }

    #[DataProvider('entity_types')]
    public function test_foreign_entity_does_not_expose_existing_recipients(string $type): void {
        $id = $this->entity_id($type);
        sharing_service::save_internal_shares($type, $id, true, 0, [$this->outsideuser->id]);
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');
        $this->setUser($this->includeduser);
        $courses = block_exaport_get_sharing_userlist($type, $id);
        $this->assertCount(1, $courses);
        $this->assertFalse($courses[0]->has_shared_users);
        $this->assertObjectNotHasProperty('users', $courses[0]);
    }

    public function test_picker_still_requires_internal_sharing_capability(): void {
        $this->setGuestUser();
        $this->expectException(\required_capability_exception::class);
        block_exaport_get_sharing_userlist('category', 0);
    }

    public function test_other_user_names_are_escaped_for_the_html_row_renderer(): void {
        global $DB;
        $DB->set_field('user', 'firstname', '<img src=x onerror=alert(1)>', ['id' => $this->excludeduser->id]);
        $id = $this->entity_id('category');
        sharing_service::save_internal_shares('category', $id, true, 0, [$this->excludeduser->id]);
        set_config('sharing_courses_with_block_only', 1, 'block_exaport');
        $courses = block_exaport_get_sharing_userlist('category', $id);
        $name = $courses[0]->users[0]->name;
        $this->assertStringContainsString('&lt;img', $name);
        $this->assertStringNotContainsString('<img', $name);
    }

    #[DataProvider('entity_types')]
    public function test_ajax_picker_still_requires_a_valid_sesskey(string $type): void {
        $_GET['sesskey'] = 'invalid';
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidsesskey', 'error'));
        block_exaport_ajax_sharing_userlist($type, 0);
    }
}
