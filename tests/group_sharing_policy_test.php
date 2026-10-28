<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib.php');
require_once(__DIR__ . '/fixtures/exaport_test_helpers_trait.php');

/**
 * Group-sharing policy must apply to saved audiences, not just visible controls.
 *
 * @package block_exaport
 * @copyright 2026 gtn gmbh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class group_sharing_policy_test extends \advanced_testcase {
    use \block_exaport\tests\exaport_test_helpers_trait;

    private \stdClass $owner;
    private \stdClass $recipient;
    private \stdClass $outsider;
    private \stdClass $cohort;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        $this->recipient = $this->getDataGenerator()->create_user();
        $this->outsider = $this->getDataGenerator()->create_user();
        $this->cohort = $this->getDataGenerator()->create_cohort(['name' => 'Sharing cohort']);
        cohort_add_member($this->cohort->id, $this->owner->id);
        cohort_add_member($this->cohort->id, $this->recipient->id);
        $this->setUser($this->owner);
        set_config('block_exaport_disable_sharegroups', 0);
        set_config('block_exaport_disable_shareall', 0);
        set_config('block_exaport_disable_externaccess', 0);
        set_config('block_exaport_teachercanseeartifactsofstudents', 0);
    }

    public static function entity_types(): array {
        return ['item' => ['item'], 'category' => ['category'], 'view' => ['view']];
    }

    private function entity(string $type): \stdClass {
        global $DB;
        if ($type === 'category') {
            $id = $this->create_category($this->owner);
        } else if ($type === 'view') {
            $id = $this->create_view($this->owner);
        } else {
            $id = $DB->insert_record('block_exaportitem', [
                'userid' => $this->owner->id, 'name' => 'Shared item', 'type' => 'note',
                'intro' => 'Item content', 'courseid' => 1, 'timemodified' => time(),
            ]);
        }
        return (object)['type' => $type, 'id' => (int)$id, 'config' => block_exaport_get_sharing_entity_config($type)];
    }

    private function record(\stdClass $entity): \stdClass {
        global $DB;
        return $DB->get_record($entity->config->entitytable, ['id' => $entity->id], '*', MUST_EXIST);
    }

    private function save_groups(\stdClass $entity): void {
        sharing_service::save_internal_shares($entity->type, $entity->id, true, 2, [], [], [$this->cohort->id]);
    }

    private function can_access(\stdClass $entity): bool {
        if ($entity->type === 'category') {
            return category_helper::can_access_thumbnail($this->record($entity));
        }
        if ($entity->type === 'view') {
            return (bool)block_exaport_get_view_from_access('id/' . $this->owner->id . '-' . $entity->id);
        }
        return (bool)block_exaport_can_user_access_shared_item($this->recipient->id, $entity->id);
    }

    private function contains(array $rows, \stdClass $entity): bool {
        foreach ($rows as $row) {
            if ($row->entity_type === $entity->type && (int)$row->id === $entity->id) {
                return true;
            }
        }
        return false;
    }

    #[DataProvider('entity_types')]
    public function test_existing_group_access_and_overviews_follow_policy_without_deleting_rows(string $type): void {
        global $DB;
        $entity = $this->entity($type);
        $this->save_groups($entity);
        $original = $DB->get_records($entity->config->groupsharetable, [$entity->config->idfield => $entity->id]);
        $this->setUser($this->recipient);
        $this->assertTrue($this->can_access($entity));
        $this->assertTrue($this->contains(share_overview::get_shared_with_me($this->recipient->id), $entity));

        set_config('block_exaport_disable_sharegroups', 1);
        $this->assertFalse($this->can_access($entity));
        $this->assertFalse($this->contains(share_overview::get_shared_with_me($this->recipient->id), $entity));
        $this->assertFalse($this->contains(share_overview::get_my_shares($this->owner->id), $entity));
        $this->assertFalse(share_info::resolve($type, $this->record($entity))->is_shared());
        $this->assertArrayHasKey($this->cohort->id, block_exaport_get_user_cohorts($this->recipient->id));
        $this->assertEquals($original, $DB->get_records($entity->config->groupsharetable,
            [$entity->config->idfield => $entity->id]));

        set_config('block_exaport_disable_sharegroups', 0);
        $this->assertTrue($this->can_access($entity));
        $this->assertTrue($this->contains(share_overview::get_shared_with_me($this->recipient->id), $entity));
    }

    #[DataProvider('entity_types')]
    public function test_disabled_group_saves_preserve_existing_audience_and_ignore_forged_assignments(string $type): void {
        global $DB;
        $entity = $this->entity($type);
        $this->save_groups($entity);
        $original = $DB->get_records($entity->config->groupsharetable, [$entity->config->idfield => $entity->id]);
        $othercohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($othercohort->id, $this->owner->id);
        cohort_add_member($othercohort->id, $this->outsider->id);
        set_config('block_exaport_disable_sharegroups', 1);

        sharing_service::save_internal_shares($type, $entity->id, true, 2,
            [$this->outsider->id], [$this->outsider->id], [$othercohort->id]);
        $this->assertEquals($original, $DB->get_records($entity->config->groupsharetable,
            [$entity->config->idfield => $entity->id]));
        $this->assertFalse($DB->record_exists($entity->config->usersharetable, [$entity->config->idfield => $entity->id]));
        $this->assertEquals(2, $this->record($entity)->shareall);

        $fresh = $this->entity($type);
        sharing_service::save_internal_shares($type, $fresh->id, true, 2,
            [$this->outsider->id], [], [$othercohort->id]);
        $this->assertFalse($DB->record_exists($fresh->config->groupsharetable, [$fresh->config->idfield => $fresh->id]));
        $this->assertFalse($DB->record_exists($fresh->config->usersharetable, [$fresh->config->idfield => $fresh->id]));
        $this->assertEquals(0, $this->record($fresh)->shareall);

        // Explicitly replacing the audience must not revive old groups when policy is re-enabled.
        sharing_service::save_internal_shares($type, $entity->id, true, 0, [$this->outsider->id]);
        $this->assertFalse($DB->record_exists($entity->config->groupsharetable, [$entity->config->idfield => $entity->id]));
        set_config('block_exaport_disable_sharegroups', 0);
        $this->setUser($this->recipient);
        $this->assertFalse($this->can_access($entity));
        $this->setUser($this->owner);
        $this->save_groups($entity);
        set_config('block_exaport_disable_sharegroups', 1);
        sharing_service::save_internal_shares($type, $entity->id, false, 2, [], [], [$othercohort->id]);
        $this->assertFalse($DB->record_exists($entity->config->groupsharetable, [$entity->config->idfield => $entity->id]));
        $this->assertEquals(0, $this->record($entity)->shareall);
    }

    #[DataProvider('entity_types')]
    public function test_direct_user_and_everyone_access_remain_independent(string $type): void {
        $entity = $this->entity($type);
        set_config('block_exaport_disable_sharegroups', 1);
        sharing_service::save_internal_shares($type, $entity->id, true, 0, [$this->recipient->id]);
        $this->setUser($this->recipient);
        $this->assertTrue($this->can_access($entity));
        $this->setUser($this->owner);
        sharing_service::save_internal_shares($type, $entity->id, true, 1);
        $this->setUser($this->recipient);
        $this->assertTrue($this->can_access($entity));
        set_config('block_exaport_disable_shareall', 1);
        $this->assertFalse($this->can_access($entity));
    }

    #[DataProvider('entity_types')]
    public function test_user_search_replaces_retained_group_audience(string $type): void {
        global $DB;
        $entity = $this->entity($type);
        $this->save_groups($entity);
        set_config('block_exaport_disable_sharegroups', 1);
        block_exaport_sharing_toggle_shared_users($entity->config, $entity->id, [$this->outsider->id => 1]);
        $this->assertEquals(0, $this->record($entity)->shareall);
        $this->assertFalse($DB->record_exists($entity->config->groupsharetable,
            [$entity->config->idfield => $entity->id]));
        $this->assertTrue($DB->record_exists($entity->config->usersharetable,
            [$entity->config->idfield => $entity->id, 'userid' => $this->outsider->id]));
        set_config('block_exaport_disable_sharegroups', 0);
        $this->setUser($this->recipient);
        $this->assertFalse($this->can_access($entity));
    }

    public function test_group_category_inheritance_cannot_bypass_disabled_policy(): void {
        global $DB;
        $root = $this->entity('category');
        $this->save_groups($root);
        $childid = $this->create_category($this->owner, 'Child', $root->id);
        $item = $this->entity('item');
        $view = $this->entity('view');
        $DB->insert_record('block_exaportitemcate', ['itemid' => $item->id, 'cateid' => $childid]);
        $DB->insert_record('block_exaportviewcate', ['viewid' => $view->id, 'cateid' => $childid]);
        $this->setUser($this->recipient);
        $this->assertTrue($this->can_access($item));
        $this->assertTrue($this->can_access($view));
        $this->assertTrue(category_helper::can_access_thumbnail($DB->get_record('block_exaportcate', ['id' => $childid])));
        $cachedgroups = block_exaport_get_group_share_categories($this->recipient->id);
        set_config('block_exaport_disable_sharegroups', 1);
        $this->assertEmpty(get_categories_shared_to_user($this->recipient->id));
        $this->assertEmpty(block_exaport_get_group_share_categories($this->recipient->id));
        $this->assertFalse($this->can_access($item));
        $this->assertFalse($this->can_access($view));
        $this->assertFalse(category_helper::can_access_thumbnail($DB->get_record('block_exaportcate', ['id' => $childid])));
        $this->assertEmpty(block_exaport_get_shared_categories(
            'c.id, c.userid, c.name, c.internshare, c.shareall', $cachedgroups, ''));

        // A separate, public category link remains valid; disabling cohorts must not disable other channels.
        $DB->set_field('block_exaportcate', 'externaccess', 1, ['id' => $root->id]);
        $category = $this->record($root);
        $access = 'hash/' . $this->owner->id . '-' . $category->hash;
        $this->setGuestUser();
        $this->assertTrue(category_helper::can_access_thumbnail($DB->get_record('block_exaportcate', ['id' => $childid]), $access));
        $this->assertNotEmpty(block_exaport_get_item($item->id, 'category/' . $access));
    }

    public function test_group_form_hides_controls_without_changing_saved_audience(): void {
        global $PAGE;
        set_config('block_exaport_disable_sharegroups', 1);
        $form = new \block_exaport\output\sharing_form(['mode' => 2, 'enabled' => true, 'showeveryone' => true]);
        $html = $PAGE->get_renderer('block_exaport')->render($form);
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);
        $this->assertSame(0, $xpath->query('//input[@type="radio" and @value="2"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-region="groups-settings"]')->length);
        $modes = $xpath->query('//input[@name="shareall"]');
        $this->assertSame('hidden', $modes->item(0)->getAttribute('type'));
        $this->assertSame('2', $modes->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//input[@type="radio" and @checked]')->length);
        $this->assertStringContainsString(get_string('sharing_groups_disabled', 'block_exaport'), $html);

        set_config('block_exaport_disable_sharegroups', 0);
        set_config('block_exaport_disable_shareall', 1);
        $state = sharing_service::normalize_view_channels((object)[
            'externaccess' => 0, 'internaccess' => 1, 'shareall' => 2, 'externcomment' => 0, 'sharedemails' => 0,
        ], true);
        $this->assertSame(2, $state->shareall);
        $context = (new \block_exaport\output\sharing_form(['mode' => 2]))
            ->export_for_template($PAGE->get_renderer('block_exaport'));
        $this->assertTrue($context['showgroups']);
    }

    public function test_group_sharing_is_enabled_when_setting_is_missing(): void {
        unset_config('block_exaport_disable_sharegroups');
        $this->assertTrue(block_exaport_sharegroups_enabled());
        set_config('block_exaport_disable_sharegroups', 1);
        $this->assertFalse(block_exaport_sharegroups_enabled());
    }

    public function test_missing_view_audience_defaults_to_users(): void {
        $state = sharing_service::normalize_view_channels((object)[
            'externaccess' => 0, 'internaccess' => 1, 'externcomment' => 0, 'sharedemails' => 0,
        ], true);
        $this->assertSame(0, $state->shareall);
    }

    public function test_disabled_groups_do_not_receive_content_notifications(): void {
        $category = $this->entity('category');
        $view = $this->entity('view');
        $this->save_groups($category);
        $this->save_groups($view);
        $sink = $this->redirectMessages();
        try {
            set_config('block_exaport_disable_sharegroups', 1);
            exaport_send_category_notifications($category->id, 1);
            exaport_send_notifications($this->record($view), 1);
            $this->assertCount(0, $sink->get_messages());

            set_config('block_exaport_disable_sharegroups', 0);
            exaport_send_category_notifications($category->id, 1);
            exaport_send_notifications($this->record($view), 1);
            $messages = $sink->get_messages();
            $this->assertCount(2, $messages);
            foreach ($messages as $message) {
                $this->assertEquals($this->recipient->id, $message->useridto);
            }
        } finally {
            $sink->close();
        }
    }
}
