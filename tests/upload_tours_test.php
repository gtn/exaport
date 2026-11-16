<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

use block_exaport\local\upload_tours;

defined('MOODLE_INTERNAL') || die();

/**
 * Installation, administrator control and native completion of the upload guides.
 *
 * @package block_exaport
 * @copyright 2026 GTN GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upload_tours_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        upload_tours::uninstall();
    }

    /** Fetch the registered guides by their language-independent identities. */
    private function guides(): array {
        global $DB;

        $guides = [];
        foreach ($DB->get_records('tool_usertours_tours') as $record) {
            $config = json_decode($record->configdata);
            if (isset($config->block_exaport_tour)) {
                $guides[$config->block_exaport_tour] = \tool_usertours\tour::instance($record->id);
            }
        }
        return $guides;
    }

    /** Make an ordinary administrator-created guide without Exaport ownership metadata. */
    private function custom_guide(): \tool_usertours\tour {
        $definition = json_decode(file_get_contents(__DIR__ . '/../db/usertours/create_item.json'));
        $definition->name = 'Administrator guide';
        $definition->configdata = '{}';
        return \tool_usertours\manager::import_tour_from_json(json_encode($definition));
    }

    public function test_registration_is_idempotent_and_preserves_unrelated_tours(): void {
        global $DB;

        $custom = $this->custom_guide();
        $before = $DB->get_records('tool_usertours_tours');
        upload_tours::register();
        $first = $this->guides();
        $this->assertSame(['upload-create', 'upload-content'], array_keys($first));
        $this->assertCount(count($before) + 2, $DB->get_records('tool_usertours_tours'));
        foreach ($first as $guide) {
            $this->assertTrue($guide->is_enabled());
            $this->assertCount(2, $guide->get_steps());
            $this->assertSame(1, $guide->get_config('showtourwhen', 1));
        }

        $registered = $DB->get_records('tool_usertours_tours');
        $steps = $DB->get_records('tool_usertours_steps');
        upload_tours::register();
        $this->assertEquals($registered, $DB->get_records('tool_usertours_tours'));
        $this->assertEquals($steps, $DB->get_records('tool_usertours_steps'));
        $this->assertEquals($before[$custom->get_id()],
            $DB->get_record('tool_usertours_tours', ['id' => $custom->get_id()]));
    }

    public function test_recovery_preserves_admin_edits_disabled_state_completion_and_deletions(): void {
        upload_tours::register();
        $guides = $this->guides();
        $guide = $guides['upload-create'];
        $guide->set_name('Our local instructions')->set_enabled(false);
        $guide->set_filter_values('course', [42]);
        $guide->persist();
        $steps = $guide->get_steps();
        $step = reset($steps);
        $step->set_content('Administrator wording')->persist();
        $guide->mark_user_completed();
        $completed = get_user_preferences(\tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $guide->get_id());

        // Imported definitions and interrupted registration must be adopted without overwriting them.
        unset_config('uploadtoursversion', 'block_exaport');
        upload_tours::register();
        $recovered = $this->guides();
        $this->assertCount(2, $recovered);
        $this->assertSame('Our local instructions', $recovered['upload-create']->get_name());
        $this->assertFalse($recovered['upload-create']->is_enabled());
        $this->assertSame([42], $recovered['upload-create']->get_filter_values('course'));
        $steps = $recovered['upload-create']->get_steps();
        $this->assertSame('Administrator wording', reset($steps)->get_content());
        $this->assertSame($completed,
            get_user_preferences(\tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $guide->get_id()));

        $recovered['upload-create']->remove();
        upload_tours::register();
        $this->assertSame(['upload-content'], array_keys($this->guides()));
    }

    public function test_registration_waits_for_native_tool_installation(): void {
        global $DB;

        $this->preventResetByRollback();
        $manager = $DB->get_manager();
        $original = new \xmldb_table('tool_usertours_steps');
        $temporary = new \xmldb_table('block_exaport_toursteps_tmp');
        $manager->rename_table($original, $temporary->getName());
        try {
            upload_tours::register();
            $this->assertFalse(get_config('block_exaport', 'uploadtoursversion'));
            $this->assertSame([], $this->guides());
        } finally {
            $manager->rename_table($temporary, $original->getName());
        }
        upload_tours::register();
        $this->assertCount(2, $this->guides());
    }

    public function test_install_recovery_and_upgrade_register_the_guides(): void {
        global $CFG;

        require_once(__DIR__ . '/../db/install.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        xmldb_block_exaport_install();
        xmldb_block_exaport_install_recovery();
        $this->assertCount(2, $this->guides());

        upload_tours::uninstall();
        set_config('version', 2026111301, 'block_exaport');
        $this->assertTrue(xmldb_block_exaport_upgrade(2026111301));
        $this->assertCount(2, $this->guides());
        $this->assertSame('2026111600', get_config('block_exaport', 'version'));
    }

    public function test_completion_is_per_user_and_per_guide_with_native_replay(): void {
        upload_tours::register();
        $guides = $this->guides();
        foreach ($guides as $guide) {
            $guide->set_config('majorupdatetime', time() - 60)->persist();
        }
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $this->setUser($first);
        $create = $guides['upload-create'];
        $content = $guides['upload-content'];
        $this->assertTrue($create->should_show_for_user());
        $create->mark_user_completed();
        $this->assertFalse($create->should_show_for_user());
        $this->assertTrue($content->should_show_for_user());

        $this->setUser($second);
        $this->assertTrue($create->should_show_for_user());
        $this->setUser($first);
        $create->request_user_reset();
        $this->assertTrue($create->should_show_for_user());
    }

    public function test_native_url_matching_separates_creation_editing_and_unrelated_pages(): void {
        upload_tours::register();
        $guides = $this->guides();
        $ids = array_map(static fn($guide) => (int)$guide->get_id(), $guides);
        $cases = [
            ['/blocks/exaport/item.php?courseid=7&id=0&action=add', 'upload-create'],
            ['/blocks/exaport/item.php?action=add&courseid=77&cattype=shared', 'upload-create'],
            ['/blocks/exaport/item.php?courseid=7&id=12&action=edit', 'upload-content'],
            ['/blocks/exaport/item.php?action=edit&id=12&cattype=shared', 'upload-content'],
            ['/blocks/exaport/item.php?courseid=7&action=assignment_import', null],
            ['/blocks/exaport/item.php?courseid=7&action=delete', null],
            ['/blocks/exaport/shared_item.php?courseid=7&itemid=12', null],
            ['/blocks/exaport/view_items.php?courseid=7', null],
        ];
        foreach ($cases as [$url, $expected]) {
            $matches = \tool_usertours\cache::get_matching_tourdata(new \moodle_url($url));
            $matchedids = array_values(array_intersect(array_keys($matches), $ids));
            $this->assertSame($expected ? [$ids[$expected]] : [], $matchedids, $url);
        }
        $this->assertSame(['#exaport button.exaport-item-content-add-button'],
            $guides['upload-content']->get_filter_values('cssselector'));
    }

    public function test_uninstall_removes_only_exaport_guides(): void {
        global $DB;

        $custom = $this->custom_guide();
        upload_tours::register();
        $guides = $this->guides();
        require_once(__DIR__ . '/../db/uninstall.php');
        $this->assertTrue(xmldb_block_exaport_uninstall());
        foreach ($guides as $guide) {
            $this->assertFalse($DB->record_exists('tool_usertours_tours', ['id' => $guide->get_id()]));
            $this->assertFalse($DB->record_exists('tool_usertours_steps', ['tourid' => $guide->get_id()]));
        }
        $this->assertTrue($DB->record_exists('tool_usertours_tours', ['id' => $custom->get_id()]));
        $this->assertFalse(get_config('block_exaport', 'uploadtoursversion'));
    }
}
