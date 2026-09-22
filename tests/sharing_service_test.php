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
 * Characterization coverage for the entity-independent sharing writer.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharing_service_test extends \advanced_testcase {

    use \block_exaport\tests\exaport_test_helpers_trait;

    protected function setUp(): void {
        $this->resetAfterTest(true);
    }

    /** All modes have identical transition and idempotency semantics for all entity types. */
    public function test_transition_matrix(): void {
        foreach (['view', 'category', 'item'] as $type) {
            [$owner, $entityid, $users, $groups] = $this->fixture($type);
            $this->setUser($owner);

            // Disabled -> users, then an unchanged users save.
            $this->save($type, $entityid, true, sharing_service::MODE_USERS,
                [$users[0]->id, $users[1]->id]);
            $this->assert_state($type, $entityid, 0, [$users[0]->id, $users[1]->id], []);
            $this->save($type, $entityid, true, sharing_service::MODE_USERS,
                [$users[0]->id, $users[1]->id]);
            $this->assert_state($type, $entityid, 0, [$users[0]->id, $users[1]->id], []);

            // Users -> everyone, then an unchanged everyone save.
            $this->save($type, $entityid, true, sharing_service::MODE_EVERYONE);
            $this->assert_state($type, $entityid, 1, [], []);
            $this->save($type, $entityid, true, sharing_service::MODE_EVERYONE);
            $this->assert_state($type, $entityid, 1, [], []);

            // Everyone -> groups, then an unchanged groups save.
            $this->save($type, $entityid, true, sharing_service::MODE_GROUPS,
                [], [$groups[0], $groups[1]]);
            $this->assert_state($type, $entityid, 2, [], [$groups[0], $groups[1]]);
            $this->save($type, $entityid, true, sharing_service::MODE_GROUPS,
                [], [$groups[0], $groups[1]]);
            $this->assert_state($type, $entityid, 2, [], [$groups[0], $groups[1]]);

            // Groups -> users.
            $this->save($type, $entityid, true, sharing_service::MODE_USERS, [$users[1]->id]);
            $this->assert_state($type, $entityid, 0, [$users[1]->id], []);

            // Every enabled mode -> disabled, including an unchanged disabled save.
            foreach ([sharing_service::MODE_USERS, sharing_service::MODE_EVERYONE,
                    sharing_service::MODE_GROUPS] as $mode) {
                $this->save($type, $entityid, true, $mode, [$users[0]->id], [$groups[0]]);
                $this->save($type, $entityid, false, $mode, [$users[1]->id], [$groups[1]]);
                $this->assert_state($type, $entityid, 0, [], [], false);
            }
            $this->save($type, $entityid, false, sharing_service::MODE_USERS);
            $this->assert_state($type, $entityid, 0, [], [], false);
        }
    }

    /** Duplicate/invalid recipients and notify rules are normalized consistently. */
    public function test_recipient_validation_and_notifications(): void {
        global $DB;

        foreach (['view', 'category', 'item'] as $type) {
            [$owner, $entityid, $users, $groups] = $this->fixture($type);
            $deleted = $this->getDataGenerator()->create_user();
            $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);
            $invalidgroup = max($groups) + 10000;
            $this->setUser($owner);

            $this->save($type, $entityid, true, sharing_service::MODE_USERS,
                [$users[0]->id, $users[0]->id, $deleted->id, 999999], [], [$users[0]->id]);
            $this->assert_state($type, $entityid, 0, [$users[0]->id], [], true, 1);
            $config = block_exaport_get_sharing_entity_config($type);
            $this->assertEquals(1, $DB->get_field($config->usersharetable, 'notify',
                [$config->idfield => $entityid, 'userid' => $users[0]->id]));

            // Existing rows change notification value, and forced notification overrides input.
            $this->save($type, $entityid, true, sharing_service::MODE_USERS, [$users[0]->id]);
            $this->assertEquals(0, $DB->get_field($config->usersharetable, 'notify',
                [$config->idfield => $entityid, 'userid' => $users[0]->id]));
            $this->save($type, $entityid, true, sharing_service::MODE_USERS,
                [$users[0]->id, $users[1]->id], [], [], true);
            $this->assertEquals([1, 1], array_values($DB->get_fieldset_select(
                $config->usersharetable, 'notify', $config->idfield . ' = ?', [$entityid], 'userid')));

            $this->save($type, $entityid, true, sharing_service::MODE_GROUPS,
                [], [$groups[0], $groups[0], $invalidgroup]);
            $this->assert_state($type, $entityid, 2, [], [$groups[0]]);
        }
    }

    /** A non-owner cannot alter flags or either recipient table. */
    public function test_non_owner_call_is_a_noop(): void {
        foreach (['view', 'category', 'item'] as $type) {
            [$owner, $entityid, $users, $groups] = $this->fixture($type);
            $this->setUser($owner);
            $this->save($type, $entityid, true, sharing_service::MODE_USERS, [$users[0]->id]);

            $this->setUser($this->getDataGenerator()->create_user());
            $this->assertFalse(sharing_service::save_internal_shares($type, $entityid, true,
                sharing_service::MODE_GROUPS, [$users[1]->id], [$groups[0]]));
            $this->assert_state($type, $entityid, 0, [$users[0]->id], []);
        }
    }

    private function save(string $type, int $id, bool $enabled, int $mode, array $users = [],
            array $groups = [], array $notify = [], bool $force = false): void {
        $this->assertTrue(sharing_service::save_internal_shares(
            $type, $id, $enabled, $mode, $users, $groups, $notify, $force));
    }

    private function fixture(string $type): array {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $users = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        $course = $this->getDataGenerator()->create_course();
        if ($type === 'view') {
            $id = $this->create_view($owner);
        } else if ($type === 'category') {
            $id = $this->create_category($owner);
        } else {
            $id = (int)$DB->insert_record('block_exaportitem', (object)[
                'userid' => $owner->id, 'type' => 'note', 'categoryid' => 0, 'name' => 'Item',
                'url' => '', 'attachment' => '', 'timecreated' => time(), 'timemodified' => time(),
                'courseid' => $course->id, 'shareall' => 0, 'externaccess' => 0, 'externcomment' => 0,
                'isoez' => 0,
            ]);
        }
        $groups = [];
        foreach (['First cohort', 'Second cohort'] as $name) {
            $groups[] = (int)$DB->insert_record('cohort', (object)[
                'contextid' => \context_system::instance()->id, 'name' => $name,
                'idnumber' => '', 'description' => '', 'descriptionformat' => FORMAT_HTML,
                'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
            ]);
            cohort_add_member(end($groups), $owner->id);
        }
        return [$owner, $id, $users, $groups];
    }

    private function assert_state(string $type, int $id, int $shareall, array $users,
            array $groups, bool $enabled = true, int $notify = 0): void {
        global $DB;
        $config = block_exaport_get_sharing_entity_config($type);
        $entity = $DB->get_record($config->entitytable, ['id' => $id], '*', MUST_EXIST);
        $this->assertEquals($shareall, $entity->shareall);
        if ($type === 'category') {
            $this->assertEquals($enabled ? 1 : 0, $entity->internshare);
        } else {
            // Views and items deliberately have no fictional internal-access column.
            $this->assertFalse(property_exists($entity, 'internaccess'));
            $this->assertNull($config->internaccessfield);
        }

        sort($users);
        sort($groups);
        $actualusers = [];
        foreach ($DB->get_records($config->usersharetable, [$config->idfield => $id], 'userid') as $row) {
            $actual = ['userid' => (int)$row->userid, 'notify' => (int)$row->notify];
            if ($type === 'item') {
                $actual['original'] = (int)$row->original;
                $actual['courseid'] = (int)$row->courseid;
            }
            $actualusers[] = $actual;
        }
        $expectedusers = array_map(function(int $userid) use ($type, $entity, $notify): array {
            $expected = ['userid' => $userid, 'notify' => $notify];
            if ($type === 'item') {
                $expected['original'] = 0;
                $expected['courseid'] = (int)$entity->courseid;
            }
            return $expected;
        }, $users);
        $actualgroups = [];
        foreach ($DB->get_records($config->groupsharetable, [$config->idfield => $id], 'groupid') as $row) {
            $actualgroups[] = ['groupid' => (int)$row->groupid];
        }
        $this->assertSame($expectedusers, $actualusers);
        $this->assertSame(array_map(fn(int $groupid): array => ['groupid' => $groupid], $groups), $actualgroups);
    }
}
