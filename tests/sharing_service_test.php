<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/sharelib.php');
require_once($CFG->dirroot . '/blocks/exaport/tests/fixtures/exaport_test_helpers_trait.php');

/**
 * Characterization tests for the common sharing persistence service.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharing_service_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    /** @var \stdClass Sharing owner. */
    private $owner;

    /** @var \stdClass First direct recipient. */
    private $recipientone;

    /** @var \stdClass Second direct recipient. */
    private $recipienttwo;

    /** @var \stdClass Course used by item share records. */
    private $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->owner = $this->getDataGenerator()->create_user();
        $this->recipientone = $this->getDataGenerator()->create_user();
        $this->recipienttwo = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        $this->setUser($this->owner);
    }

    /**
     * Return every entity type supported by the service.
     *
     * @return array
     */
    public static function entity_type_provider(): array {
        return [
            'view' => ['view'],
            'category' => ['category'],
            'item' => ['item'],
        ];
    }

    /**
     * Covers every mode transition and unchanged save for each entity type.
     *
     * @dataProvider entity_type_provider
     * @param string $entitytype Entity type under test.
     */
    public function test_mode_transition_matrix(string $entitytype): void {
        global $DB;

        $entity = $this->create_entity($entitytype);
        $cohort = $this->create_owner_cohort();

        // An unchanged disabled save remains empty, even with forged subordinate values.
        $this->save($entity, false, 2, [$this->recipienttwo->id], [$this->recipienttwo->id], [$cohort->id]);
        $this->assert_entity_state($entity, 0, 0);
        $this->assert_user_shares($entity, []);
        $this->assert_group_shares($entity, []);

        // Disabled -> selected users.
        $this->save($entity, true, 0, [$this->recipientone->id], [$this->recipientone->id]);
        $this->assert_entity_state($entity, 0, 1);
        $this->assert_user_shares($entity, [
            $this->recipientone->id => ['notify' => 1],
        ]);
        $this->assert_group_shares($entity, []);

        // An unchanged user-mode save preserves the exact effective state.
        $userrowid = $DB->get_field($entity->config->usersharetable, 'id', [
            $entity->config->idfield => $entity->id,
            'userid' => $this->recipientone->id,
        ]);
        $this->save($entity, true, 0, [$this->recipientone->id], [$this->recipientone->id]);
        $this->assert_user_shares($entity, [
            $this->recipientone->id => ['notify' => 1],
        ]);
        $this->assertSame($userrowid, $DB->get_field($entity->config->usersharetable, 'id', [
            $entity->config->idfield => $entity->id,
            'userid' => $this->recipientone->id,
        ]));

        // Users -> everyone.
        $this->save($entity, true, 1, [$this->recipientone->id], [$this->recipientone->id], [$cohort->id]);
        $this->assert_entity_state($entity, 1, 1);
        $this->assert_user_shares($entity, []);
        $this->assert_group_shares($entity, []);

        // An unchanged everyone-mode save remains empty of relation rows.
        $this->save($entity, true, 1);
        $this->assert_entity_state($entity, 1, 1);
        $this->assert_user_shares($entity, []);
        $this->assert_group_shares($entity, []);

        // Everyone -> groups. Forged direct users are ignored in group mode.
        $this->save($entity, true, 2, [$this->recipienttwo->id], [$this->recipienttwo->id], [$cohort->id]);
        $this->assert_entity_state($entity, 2, 1);
        $this->assert_user_shares($entity, []);
        $this->assert_group_shares($entity, [$cohort->id]);

        // An unchanged group-mode save retains the exact effective audience.
        $this->save($entity, true, 2, [], [], [$cohort->id]);
        $this->assert_entity_state($entity, 2, 1);
        $this->assert_user_shares($entity, []);
        $this->assert_group_shares($entity, [$cohort->id]);

        // Groups -> users.
        $this->save($entity, true, 0, [$this->recipienttwo->id]);
        $this->assert_entity_state($entity, 0, 1);
        $this->assert_user_shares($entity, [
            $this->recipienttwo->id => ['notify' => 0],
        ]);
        $this->assert_group_shares($entity, []);

        // Every enabled mode -> disabled. Re-enable between assertions so each mode is covered.
        foreach ([0, 1, 2] as $mode) {
            $users = $mode === 0 ? [$this->recipientone->id] : [];
            $groups = $mode === 2 ? [$cohort->id] : [];
            $this->save($entity, true, $mode, $users, [], $groups);
            $this->save($entity, false, $mode, [$this->recipienttwo->id], [$this->recipienttwo->id], [$cohort->id]);
            $this->assert_entity_state($entity, 0, 0);
            $this->assert_user_shares($entity, []);
            $this->assert_group_shares($entity, []);
        }
    }

    /**
     * Duplicate ids are collapsed and deleted users are rejected for every entity type.
     *
     * @dataProvider entity_type_provider
     * @param string $entitytype Entity type under test.
     */
    public function test_duplicate_and_deleted_recipients(string $entitytype): void {
        global $DB;

        $entity = $this->create_entity($entitytype);
        $deleteduser = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $deleteduser->id]);

        $this->save($entity, true, 0, [
            $this->recipientone->id,
            $this->recipientone->id,
            $deleteduser->id,
            $deleteduser->id,
        ]);

        $this->assert_user_shares($entity, [
            $this->recipientone->id => ['notify' => 0],
        ]);
        $this->assert_group_shares($entity, []);
    }

    /**
     * Only cohorts containing the owner may be persisted, and duplicates are collapsed.
     *
     * @dataProvider entity_type_provider
     * @param string $entitytype Entity type under test.
     */
    public function test_invalid_and_duplicate_cohorts(string $entitytype): void {
        $entity = $this->create_entity($entitytype);
        $allowedcohort = $this->create_owner_cohort();
        $disallowedcohort = $this->getDataGenerator()->create_cohort(['name' => 'Not owners cohort']);

        $this->save($entity, true, 2, [], [], [
            $allowedcohort->id,
            $allowedcohort->id,
            $disallowedcohort->id,
            999999,
            -1,
        ]);

        $this->assert_user_shares($entity, []);
        $this->assert_group_shares($entity, [$allowedcohort->id]);
    }

    /**
     * Notification state can be enabled, disabled and globally forced for every entity type.
     *
     * @dataProvider entity_type_provider
     * @param string $entitytype Entity type under test.
     */
    public function test_notification_changes_and_force_notify(string $entitytype): void {
        $entity = $this->create_entity($entitytype);

        $this->save($entity, true, 0, [$this->recipientone->id]);
        $this->assert_user_shares($entity, [
            $this->recipientone->id => ['notify' => 0],
        ]);

        $this->save($entity, true, 0, [$this->recipientone->id], [$this->recipientone->id]);
        $this->assert_user_shares($entity, [
            $this->recipientone->id => ['notify' => 1],
        ]);

        $this->save($entity, true, 0, [$this->recipientone->id]);
        $this->assert_user_shares($entity, [
            $this->recipientone->id => ['notify' => 0],
        ]);

        $this->save($entity, true, 0, [$this->recipientone->id, $this->recipienttwo->id], [], [], true);
        $this->assert_user_shares($entity, [
            $this->recipientone->id => ['notify' => 1],
            $this->recipienttwo->id => ['notify' => 1],
        ]);
    }

    /**
     * A non-owner cannot mutate sharing state for any entity type.
     *
     * @dataProvider entity_type_provider
     * @param string $entitytype Entity type under test.
     */
    public function test_non_owner_is_rejected_without_changes(string $entitytype): void {
        $entity = $this->create_entity($entitytype);
        $otheruser = $this->getDataGenerator()->create_user();
        $this->setUser($otheruser);

        try {
            $this->save($entity, true, 0, [$this->recipientone->id]);
            $this->fail('A non-owner was allowed to change sharing state.');
        } catch (\moodle_exception $exception) {
            $this->assertSame($entity->config->notfoundstring, $exception->errorcode);
        }

        $this->setUser($this->owner);
        $this->assert_entity_state($entity, 0, 0);
        $this->assert_user_shares($entity, []);
        $this->assert_group_shares($entity, []);
    }

    /**
     * Create one entity and its table metadata.
     *
     * @param string $entitytype Entity type.
     * @return \stdClass
     */
    private function create_entity(string $entitytype): \stdClass {
        global $DB;

        if ($entitytype === 'view') {
            $entityid = $this->create_view($this->owner);
            $extrafields = [];
        } else if ($entitytype === 'category') {
            $entityid = $this->create_category($this->owner);
            $extrafields = [];
        } else {
            $entityid = (int)$DB->insert_record('block_exaportitem', (object)[
                'userid' => $this->owner->id,
                'name' => 'Sharing service item',
                'type' => 'note',
                'intro' => '',
                'timemodified' => time(),
                'courseid' => $this->course->id,
            ]);
            $extrafields = ['original' => 0, 'courseid' => $this->course->id];
        }

        return (object)[
            'type' => $entitytype,
            'id' => $entityid,
            'config' => block_exaport_get_sharing_entity_config($entitytype),
            'extrafields' => $extrafields,
        ];
    }

    /**
     * Create a cohort the sharing owner is allowed to select.
     *
     * @return \stdClass
     */
    private function create_owner_cohort(): \stdClass {
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Owners cohort']);
        cohort_add_member($cohort->id, $this->owner->id);
        return $cohort;
    }

    /**
     * Invoke the service with the entity's required extra share fields.
     *
     * @param \stdClass $entity Entity metadata.
     * @param bool $enabled Master sharing state.
     * @param int $mode Audience mode.
     * @param int[] $userids Direct recipients.
     * @param int[] $notifyuserids Notification recipients.
     * @param int[] $groupids Cohort recipients.
     * @param bool $alwaysnotify Force notifications.
     */
    private function save(\stdClass $entity, bool $enabled, int $mode, array $userids = [],
            array $notifyuserids = [], array $groupids = [], bool $alwaysnotify = false): void {
        sharing_service::save_internal_shares($entity->type, $entity->id, $enabled, $mode, $userids,
            $notifyuserids, $groupids, $entity->extrafields, $alwaysnotify);
    }

    /**
     * Assert persisted entity flags, including the category-only internshare flag.
     *
     * @param \stdClass $entity Entity metadata.
     * @param int $mode Expected persisted shareall mode.
     * @param int $enabled Expected category internal state.
     */
    private function assert_entity_state(\stdClass $entity, int $mode, int $enabled): void {
        global $DB;

        $record = $DB->get_record($entity->config->entitytable, ['id' => $entity->id], '*', MUST_EXIST);
        $this->assertEquals($mode, $record->shareall);
        if ($entity->type === 'category') {
            $this->assertEquals($enabled, $record->internshare);
        } else {
            $this->assertFalse(property_exists($record, 'internshare'));
            $this->assertFalse(property_exists($record, 'internaccess'));
        }
    }

    /**
     * Assert the exact direct-recipient state.
     *
     * @param \stdClass $entity Entity metadata.
     * @param array $expected Expected rows keyed by user id.
     */
    private function assert_user_shares(\stdClass $entity, array $expected): void {
        global $DB;

        $records = $DB->get_records($entity->config->usersharetable,
            [$entity->config->idfield => $entity->id], 'userid ASC');
        $actual = [];
        foreach ($records as $record) {
            $actual[(int)$record->userid] = ['notify' => (int)$record->notify];
            if ($entity->type === 'item') {
                $actual[(int)$record->userid]['original'] = (int)$record->original;
                $actual[(int)$record->userid]['courseid'] = (int)$record->courseid;
            }
        }
        if ($entity->type === 'item') {
            foreach ($expected as &$row) {
                $row += ['original' => 0, 'courseid' => (int)$this->course->id];
            }
            unset($row);
        }
        $this->assertSame($expected, $actual);
    }

    /**
     * Assert the exact group-recipient state.
     *
     * @param \stdClass $entity Entity metadata.
     * @param int[] $expectedgroupids Expected cohort ids.
     */
    private function assert_group_shares(\stdClass $entity, array $expectedgroupids): void {
        global $DB;

        $actual = array_map('intval', $DB->get_fieldset_select(
            $entity->config->groupsharetable,
            'groupid',
            $entity->config->idfield . ' = ?',
            [$entity->id]
        ));
        sort($actual);
        sort($expectedgroupids);
        $this->assertSame($expectedgroupids, $actual);
    }
}
