<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

/**
 * Administrative cleanup must remove only its audience, while policy toggles retain grants.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharing_admin_cleanup_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

    private \stdClass $owner;
    private \stdClass $recipient;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        $this->recipient = $this->getDataGenerator()->create_user();
        set_config('block_exaport_disable_shareall', 0);
        set_config('block_exaport_disable_sharegroups', 0);
        set_config('block_exaport_disable_externaccess', 0);
        set_config('block_exaport_teachercanseeartifactsofstudents', 0);
    }

    private function create_entity(string $type, \stdClass $owner, int $mode): \stdClass {
        global $DB;
        if ($type === 'category') {
            $id = $this->create_category($owner, internshare: 1, shareall: $mode, externaccess: 1);
        } else if ($type === 'view') {
            $id = $this->create_view($owner, shareall: $mode, externaccess: 1);
        } else {
            $id = $DB->insert_record('block_exaportitem', [
                'userid' => $owner->id, 'name' => 'Cleanup test item', 'type' => 'note',
                'intro' => 'Content', 'courseid' => 1, 'timemodified' => time(), 'shareall' => $mode,
            ]);
        }
        return $DB->get_record(block_exaport_get_sharing_entity_config($type)->entitytable, ['id' => $id], '*', MUST_EXIST);
    }

    public static function audiences(): array {
        return ['everyone' => ['everyone', 1], 'groups' => ['groups', 2]];
    }

    #[DataProvider('audiences')]
    public function test_cleanup_covers_all_owners_and_types_but_preserves_other_channels(string $audience, int $mode): void {
        global $DB;
        $cohort = $this->getDataGenerator()->create_cohort();
        $snapshots = [];
        foreach (['item', 'category', 'view'] as $type) {
            $config = block_exaport_get_sharing_entity_config($type);
            foreach ([$this->owner, $this->recipient] as $owner) {
                foreach ([0, 1, 2] as $savedmode) {
                    $entity = $this->create_entity($type, $owner, $savedmode);
                    // Include dormant relationships: cleanup must delete all group assignments,
                    // including legacy rows whose current audience is users or Everyone.
                    $DB->insert_record($config->groupsharetable, [
                        $config->idfield => $entity->id, 'groupid' => $cohort->id,
                    ]);
                    $DB->insert_record($config->usersharetable, [
                        $config->idfield => $entity->id, 'userid' => $this->recipient->id,
                    ]);
                }
            }
            $snapshots[$type] = [
                $DB->get_records($config->entitytable), $DB->get_records($config->usersharetable),
                $DB->get_records($config->groupsharetable),
            ];
        }
        // Administrative cleanup is independent of whether its policy is currently enabled.
        set_config('block_exaport_disable_' . ($audience === 'groups' ? 'sharegroups' : 'shareall'), 1);
        $this->setAdminUser();
        sharing_service::remove_all_shares($audience);

        foreach ($snapshots as $type => [$entities, $users, $groups]) {
            $config = block_exaport_get_sharing_entity_config($type);
            foreach ($entities as $entity) {
                if ((int)$entity->shareall === $mode) {
                    $entity->shareall = 0;
                }
            }
            $this->assertEquals($entities, $DB->get_records($config->entitytable));
            $this->assertEquals($users, $DB->get_records($config->usersharetable));
            $this->assertEquals($audience === 'groups' ? [] : $groups, $DB->get_records($config->groupsharetable));
        }
        $this->assertEquals(1, get_config('core',
            'block_exaport_disable_' . ($audience === 'groups' ? 'sharegroups' : 'shareall')));
        $this->assertTrue(block_exaport_externaccess_enabled());
    }

    #[DataProvider('audiences')]
    public function test_nonadministrators_cannot_run_global_cleanup(string $audience, int $mode): void {
        global $DB;
        $entity = $this->create_entity('view', $this->owner, $mode);
        $this->setUser($this->owner);
        try {
            sharing_service::remove_all_shares($audience);
            $this->fail('Global cleanup must require site configuration access');
        } catch (\required_capability_exception $e) {
            $this->assertEquals($entity, $DB->get_record('block_exaportview', ['id' => $entity->id]));
        }
    }

    public function test_unknown_audience_is_rejected(): void {
        $this->setAdminUser();
        $this->expectException(\coding_exception::class);
        sharing_service::remove_all_shares('users');
    }

    #[DataProvider('audiences')]
    public function test_removed_grants_do_not_return_when_policy_is_reenabled(string $audience, int $mode): void {
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $this->owner->id);
        cohort_add_member($cohort->id, $this->recipient->id);
        $entities = [];
        foreach (['item', 'category', 'view'] as $type) {
            $entity = $this->create_entity($type, $this->owner, 0);
            $this->setUser($this->owner);
            sharing_service::save_internal_shares($type, $entity->id, true, $mode, [], [], [$cohort->id]);
            $entities[$type] = $entity;
        }
        $this->setUser($this->recipient);
        foreach ($entities as $type => $entity) {
            $this->assertTrue($this->can_access($type, $entity));
        }
        $setting = 'block_exaport_disable_' . ($audience === 'groups' ? 'sharegroups' : 'shareall');
        set_config($setting, 1);
        foreach ($entities as $type => $entity) {
            $this->assertFalse($this->can_access($type, $entity));
        }
        set_config($setting, 0);
        foreach ($entities as $type => $entity) {
            $this->assertTrue($this->can_access($type, $entity));
        }
        $this->setAdminUser();
        sharing_service::remove_all_shares($audience);
        set_config($setting, 1);
        set_config($setting, 0);
        $this->setUser($this->recipient);
        foreach ($entities as $type => $entity) {
            $this->assertFalse($this->can_access($type, $entity));
        }
    }

    private function can_access(string $type, \stdClass $entity): bool {
        global $DB;
        if ($type === 'category') {
            return category_helper::can_access_thumbnail($DB->get_record('block_exaportcate', ['id' => $entity->id]));
        }
        if ($type === 'view') {
            return (bool)block_exaport_get_view_from_access('id/' . $entity->userid . '-' . $entity->id);
        }
        return (bool)block_exaport_can_user_access_shared_item($this->recipient->id, $entity->id);
    }

    public function test_external_toggle_preserves_links_and_contained_item_access(): void {
        global $DB;
        $category = $this->create_entity('category', $this->owner, 0);
        $view = $this->create_entity('view', $this->owner, 0);
        $item = $this->create_entity('item', $this->owner, 0);
        $DB->insert_record('block_exaportitemcate', ['itemid' => $item->id, 'cateid' => $category->id]);
        $DB->insert_record('block_exaportviewblock', [
            'viewid' => $view->id, 'type' => 'item', 'itemid' => $item->id, 'positionx' => 1, 'positiony' => 1,
        ]);
        $categoryaccess = 'hash/' . $this->owner->id . '-' . $category->hash;
        $viewaccess = 'hash/' . $this->owner->id . '-' . $view->hash;
        $this->setGuestUser();
        foreach ([0, 1, 0] as $disabled) {
            set_config('block_exaport_disable_externaccess', $disabled);
            $this->assertSame(!$disabled, (bool)block_exaport_get_category_from_access($categoryaccess));
            $this->assertSame(!$disabled, (bool)block_exaport_get_view_from_access($viewaccess));
            $this->assertSame(!$disabled, (bool)block_exaport_get_item($item->id, 'category/' . $categoryaccess));
            if (!$disabled) {
                $this->assertNotEmpty(block_exaport_get_item($item->id, 'view/' . $viewaccess));
            }
            $this->assertEquals($category, $DB->get_record('block_exaportcate', ['id' => $category->id]));
            $this->assertEquals($view, $DB->get_record('block_exaportview', ['id' => $view->id]));
        }

        // Owner revocation is separate: re-enabling the site policy must not undo it.
        $DB->set_field('block_exaportcate', 'externaccess', 0, ['id' => $category->id]);
        $DB->set_field('block_exaportview', 'externaccess', 0, ['id' => $view->id]);
        $this->assertEmpty(block_exaport_get_category_from_access($categoryaccess));
        $this->assertEmpty(block_exaport_get_view_from_access($viewaccess));
    }

    public function test_saving_while_disabled_can_revoke_individual_channels_without_rotating_the_link(): void {
        $view = $this->create_entity('view', $this->owner, 1);
        $view->internaccess = 1;
        set_config('block_exaport_disable_shareall', 1);
        set_config('block_exaport_disable_externaccess', 1);
        $normalized = sharing_service::normalize_view_channels(clone $view, true);
        $this->assertSame(0, $normalized->shareall);
        $this->assertSame(0, $normalized->externaccess);
        $this->assertSame($view->hash, $normalized->hash);
    }
}
