<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport;

use block_exaport\local\user_tours;

defined('MOODLE_INTERNAL') || die();

/**
 * Installation, individual tour revisions and native per-user completion.
 *
 * @package block_exaport
 * @copyright 2026 GTN GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_tours_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        user_tours::uninstall();
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
    private function custom_guide(array $config = []): \tool_usertours\tour {
        $definition = json_decode(file_get_contents(__DIR__ . '/../db/usertours/item_content.json'));
        $definition->name = 'Administrator guide';
        $definition->configdata = json_encode((object)$config);
        return \tool_usertours\manager::import_tour_from_json(json_encode($definition));
    }

    /** Model either released separate guide without retaining obsolete deployment JSON. */
    private function legacy_guide(string $identity, int $revision): \tool_usertours\tour {
        $guide = $this->custom_guide(['block_exaport_tour' => $identity, 'block_exaport_tour_version' => $revision]);
        return $guide->set_config('majorupdatetime', time() - 60)->persist();
    }

    public function test_registration_is_idempotent_and_preserves_unrelated_tours(): void {
        global $DB;

        $custom = $this->custom_guide();
        $before = $DB->get_records('tool_usertours_tours');
        user_tours::register();
        $first = $this->guides();
        $this->assertSame(['item-content'], array_keys($first));
        $this->assertCount(count($before) + 1, $DB->get_records('tool_usertours_tours'));
        foreach ($first as $guide) {
            $this->assertTrue($guide->is_enabled());
            $this->assertCount(2, $guide->get_steps());
            $this->assertSame(1, $guide->get_config('showtourwhen', 1));
            $this->assertSame(1, $guide->get_config('block_exaport_tour_version'));
        }

        $registered = $DB->get_records('tool_usertours_tours');
        $steps = $DB->get_records('tool_usertours_steps');
        user_tours::register();
        $this->assertEquals($registered, $DB->get_records('tool_usertours_tours'));
        $this->assertEquals($steps, $DB->get_records('tool_usertours_steps'));
        $this->assertEquals($before[$custom->get_id()],
            $DB->get_record('tool_usertours_tours', ['id' => $custom->get_id()]));
    }

    public function test_current_revisions_preserve_completion_when_signature_is_missing(): void {
        global $DB;

        user_tours::register();
        foreach ($this->guides() as $guide) {
            $guide->set_config('majorupdatetime', time() - 60)->persist();
            $guide->mark_user_completed();
            $this->assertFalse($guide->should_show_for_user());
        }
        $registered = $DB->get_records('tool_usertours_tours');
        $steps = $DB->get_records('tool_usertours_steps');
        $preferences = $DB->get_records('user_preferences');
        $signature = get_config('block_exaport', 'usertourssignature');

        // Rechecking after an upgrade does not replace guides whose revisions are current.
        unset_config('usertourssignature', 'block_exaport');
        user_tours::register();
        $this->assertEquals($registered, $DB->get_records('tool_usertours_tours'));
        $this->assertEquals($steps, $DB->get_records('tool_usertours_steps'));
        $this->assertEquals($preferences, $DB->get_records('user_preferences'));
        $this->assertSame($signature, get_config('block_exaport', 'usertourssignature'));
    }

    public function test_retiring_old_guides_preserves_existing_common_guide_completion(): void {
        global $DB;

        user_tours::register();
        $guide = $this->guides()['item-content'];
        $guide->set_config('majorupdatetime', time() - 60)->persist();
        $guide->mark_user_completed();
        $record = $DB->get_record('tool_usertours_tours', ['id' => $guide->get_id()]);
        $steps = $DB->get_records('tool_usertours_steps', ['tourid' => $guide->get_id()]);
        $completionkey = \tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $guide->get_id();
        $completed = $DB->get_records('user_preferences', ['name' => $completionkey]);
        $this->legacy_guide('upload-create', 1);
        $this->legacy_guide('upload-content', 2);
        unset_config('usertourssignature', 'block_exaport');

        user_tours::register();
        $this->assertSame(['item-content'], array_keys($this->guides()));
        $this->assertEquals($record, $DB->get_record('tool_usertours_tours', ['id' => $guide->get_id()]));
        $this->assertEquals($steps, $DB->get_records('tool_usertours_steps', ['tourid' => $guide->get_id()]));
        $this->assertEquals($completed, $DB->get_records('user_preferences', ['name' => $completionkey]));
        $this->assertFalse($guide->should_show_for_user());
    }

    public function test_revision_change_replaces_only_the_outdated_guide_and_resets_its_users(): void {
        global $DB;

        $custom = $this->custom_guide();
        $custom->set_config('majorupdatetime', time() - 60)->persist();
        user_tours::register();
        $outdated = $this->guides()['item-content'];
        $outdated->set_config('majorupdatetime', time() - 60)->persist();
        $users = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        foreach ($users as $user) {
            $this->setUser($user);
            $outdated->mark_user_completed();
            $custom->mark_user_completed();
        }
        $outdated->set_config('block_exaport_tour_version', 0);
        $outdated->set_pathmatch('/outdated/page.php')->set_enabled(false)->persist();
        $customrecord = $DB->get_record('tool_usertours_tours', ['id' => $custom->get_id()]);
        $customkey = \tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $custom->get_id();
        $completions = $DB->get_records('user_preferences', ['name' => $customkey]);
        set_config('usertourssignature', 'earlier-release', 'block_exaport');
        \core\task\manager::queue_adhoc_task(new task\register_user_tours(), true);

        $this->runAdhocTasks(task\register_user_tours::class);
        $replacement = $this->guides()['item-content'];
        $this->assertNotSame($outdated->get_id(), $replacement->get_id());
        $this->assertFalse($DB->record_exists('tool_usertours_tours', ['id' => $outdated->get_id()]));
        $this->assertFalse($DB->record_exists('tool_usertours_steps', ['tourid' => $outdated->get_id()]));
        $this->assertFalse($DB->record_exists('user_preferences', [
            'name' => \tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $outdated->get_id(),
        ]));
        $definition = json_decode(file_get_contents(__DIR__ . '/../db/usertours/item_content.json'));
        $this->assertSame($definition->pathmatch, $replacement->get_pathmatch());
        $this->assertTrue($replacement->is_enabled());
        $this->assertSame(1, $replacement->get_config('block_exaport_tour_version'));
        $steps = $replacement->get_steps();
        $this->assertSame($definition->steps[0]->content, reset($steps)->get_content());
        $this->assertEquals($customrecord, $DB->get_record('tool_usertours_tours', ['id' => $custom->get_id()]));
        $this->assertEquals($completions, $DB->get_records('user_preferences', ['name' => $customkey]));
        foreach ($users as $user) {
            $this->setUser($user);
            $this->assertTrue($replacement->should_show_for_user());
            $this->assertFalse($custom->should_show_for_user());
        }
    }

    public function test_newer_installed_revision_is_not_downgraded(): void {
        global $DB;

        user_tours::register();
        $guide = $this->guides()['item-content'];
        $guide->set_config('block_exaport_tour_version', 3)->persist();
        $record = $DB->get_record('tool_usertours_tours', ['id' => $guide->get_id()]);
        unset_config('usertourssignature', 'block_exaport');
        user_tours::register();
        $this->assertEquals($record, $DB->get_record('tool_usertours_tours', ['id' => $guide->get_id()]));
        $this->assertCount(1, $this->guides());
    }

    public function test_completed_separate_guides_are_retired_for_every_user_on_upgrade(): void {
        global $CFG, $DB;

        $custom = $this->custom_guide();
        $custom->set_config('majorupdatetime', time() - 60)->persist();
        $old = [];
        foreach (['upload-create', 'upload-content'] as $identity) {
            foreach ([1, 2] as $revision) {
                $old[] = $this->legacy_guide($identity, $revision);
            }
        }
        $users = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        foreach ($users as $user) {
            $this->setUser($user);
            foreach (array_merge([$custom], $old) as $guide) {
                $guide->mark_user_completed();
                $this->assertFalse($guide->should_show_for_user());
                $guide->request_user_reset();
            }
        }
        $customkey = \tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $custom->get_id();
        $customcompletions = $DB->get_records('user_preferences', ['name' => $customkey]);
        set_config('usertourssignature', 'separate-guides-release', 'block_exaport');
        set_config('version', 2026111601, 'block_exaport');
        require_once(__DIR__ . '/../db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        $this->assertTrue(xmldb_block_exaport_upgrade(2026111601));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(task\register_user_tours::class));
        $this->assertTrue($DB->record_exists('tool_usertours_tours', ['id' => $old[0]->get_id()]),
            'The upgrade step must leave tour APIs to the deferred task.');
        $this->runAdhocTasks(task\register_user_tours::class);
        $this->assertSame(['item-content'], array_keys($this->guides()));
        $updated = $this->guides()['item-content'];
        $this->assertSame(1, $updated->get_config('block_exaport_tour_version'));
        foreach ($old as $guide) {
            $this->assertFalse($DB->record_exists('tool_usertours_tours', ['id' => $guide->get_id()]));
            $this->assertFalse($DB->record_exists('tool_usertours_steps', ['tourid' => $guide->get_id()]));
            foreach ([\tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER, \tool_usertours\tour::TOUR_REQUESTED_BY_USER]
                    as $prefix) {
                $this->assertFalse($DB->record_exists('user_preferences', ['name' => $prefix . $guide->get_id()]));
            }
        }
        $updated->set_config('majorupdatetime', time() - 60)->persist();
        foreach ($users as $user) {
            $this->setUser($user);
            $this->assertTrue($updated->should_show_for_user());
            $updated->mark_user_completed();
            $this->assertFalse($updated->should_show_for_user());
        }
        $this->assertEquals($customcompletions, $DB->get_records('user_preferences', ['name' => $customkey]));
        $record = $DB->get_record('tool_usertours_tours', ['id' => $updated->get_id()]);
        $preferences = $DB->get_records('user_preferences');
        user_tours::register();
        $this->assertEquals($record, $DB->get_record('tool_usertours_tours', ['id' => $updated->get_id()]));
        $this->assertEquals($preferences, $DB->get_records('user_preferences'));
    }

    public function test_common_guide_points_to_uploads_before_the_main_save(): void {
        $definition = json_decode(file_get_contents(__DIR__ . '/../db/usertours/item_content.json'));
        $this->assertSame('#exaport #fitem_id_editorcontent', $definition->steps[0]->targetvalue);
        $this->assertSame('#exaport [name="submitbutton"]', $definition->steps[1]->targetvalue);
        $this->assertSame(['#exaport button.exaport-item-content-add-button'],
            json_decode($definition->configdata, true)['filtervalues']['cssselector']);
    }

    public function test_registration_waits_for_native_tool_installation(): void {
        global $DB;

        $this->preventResetByRollback();
        $manager = $DB->get_manager();
        $original = new \xmldb_table('tool_usertours_steps');
        $temporary = new \xmldb_table('block_exaport_toursteps_tmp');
        $manager->rename_table($original, $temporary->getName());
        try {
            user_tours::register();
            $this->assertFalse(get_config('block_exaport', 'usertourssignature'));
            $this->assertSame([], $this->guides());
        } finally {
            $manager->rename_table($temporary, $original->getName());
        }
        user_tours::register();
        $this->assertCount(1, $this->guides());
    }

    public function test_install_recovery_and_upgrade_queue_one_registration_task(): void {
        global $CFG;

        require_once(__DIR__ . '/../db/install.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        require_once($CFG->libdir . '/upgradelib.php');
        xmldb_block_exaport_install();
        xmldb_block_exaport_install_recovery();
        $this->assertSame([], $this->guides(), 'Install callbacks must not call the User tours API.');
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(task\register_user_tours::class));
        $this->runAdhocTasks(task\register_user_tours::class);
        $this->assertCount(1, $this->guides());

        user_tours::uninstall();
        set_config('version', 2026111301, 'block_exaport');
        $this->assertTrue(xmldb_block_exaport_upgrade(2026111301));
        $this->assertSame([], $this->guides(), 'Upgrade must defer calls to the User tours API.');
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(task\register_user_tours::class));
        $this->runAdhocTasks(task\register_user_tours::class);
        $this->assertCount(1, $this->guides());
        $this->assertSame('2026111800', get_config('block_exaport', 'version'));
    }

    public function test_queued_registration_task_is_idempotent(): void {
        require_once(__DIR__ . '/../db/install.php');
        xmldb_block_exaport_install();
        $this->runAdhocTasks(task\register_user_tours::class);
        $before = array_map(static fn($guide) => $guide->get_id(), $this->guides());
        $this->assertCount(1, $before);
        \core\task\manager::queue_adhoc_task(new task\register_user_tours(), true);
        $this->runAdhocTasks(task\register_user_tours::class);
        $this->assertSame($before, array_map(static fn($guide) => $guide->get_id(), $this->guides()));
        $this->assertSame([], \core\task\manager::get_adhoc_tasks(task\register_user_tours::class));
    }

    public function test_lock_timeout_keeps_registration_task_available_for_retry(): void {
        global $CFG;

        \core\task\manager::queue_adhoc_task(new task\register_user_tours(), true);
        $factory = new class('block_exaport') extends \core\lock\file_lock_factory {
            /** Simulate an exhausted lock wait without delaying the test. */
            public function get_lock($resource, $timeout, $maxlifetime = 86400) {
                return false;
            }
        };
        $originalfactory = $CFG->lock_factory ?? null;
        $CFG->lock_factory = get_class($factory);
        $caught = null;
        try {
            $this->runAdhocTasks(task\register_user_tours::class);
        } catch (\moodle_exception $exception) {
            $caught = $exception;
        } finally {
            if ($originalfactory === null) {
                unset($CFG->lock_factory);
            } else {
                $CFG->lock_factory = $originalfactory;
            }
        }

        $this->assertInstanceOf(\moodle_exception::class, $caught);
        $this->assertSame('locktimeout', $caught->errorcode);
        $this->assertFalse(get_config('block_exaport', 'usertourssignature'));
        $this->assertSame([], $this->guides());
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(task\register_user_tours::class));

        $this->runAdhocTasks(task\register_user_tours::class);
        $this->assertCount(1, $this->guides());
        $this->assertNotFalse(get_config('block_exaport', 'usertourssignature'));
        $this->assertSame([], \core\task\manager::get_adhoc_tasks(task\register_user_tours::class));
    }

    public function test_common_guide_completion_is_per_user_with_native_replay(): void {
        user_tours::register();
        $guide = $this->guides()['item-content'];
        $guide->set_config('majorupdatetime', time() - 60)->persist();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $this->setUser($first);
        $this->assertTrue($guide->should_show_for_user());
        $guide->mark_user_completed();
        $this->assertFalse($guide->should_show_for_user());

        $this->setUser($second);
        $this->assertTrue($guide->should_show_for_user());
        $this->setUser($first);
        $guide->request_user_reset();
        $this->assertTrue($guide->should_show_for_user());
    }

    public function test_native_url_matching_uses_one_guide_for_creation_and_editing(): void {
        user_tours::register();
        $guide = $this->guides()['item-content'];
        $cases = [
            ['/blocks/exaport/item.php?courseid=7&id=0&action=add', true],
            ['/blocks/exaport/item.php?action=add&courseid=77&cattype=shared', true],
            ['/blocks/exaport/item.php?courseid=7&id=12&action=edit', true],
            ['/blocks/exaport/item.php?action=edit&id=12&cattype=shared', true],
            ['/blocks/exaport/shared_item.php?courseid=7&itemid=12', false],
            ['/blocks/exaport/item_content_file.php?courseid=7&itemid=12', false],
            ['/blocks/exaport/view_items.php?courseid=7', false],
        ];
        foreach ($cases as [$url, $expected]) {
            $matches = \tool_usertours\cache::get_matching_tourdata(new \moodle_url($url));
            $this->assertSame($expected, isset($matches[$guide->get_id()]), $url);
        }
        // Native client-side filtering excludes confirmation/read-only pages without an Add button.
        $this->assertSame(['#exaport button.exaport-item-content-add-button'],
            $guide->get_filter_values('cssselector'));
    }

    public function test_uninstall_removes_only_exaport_guides(): void {
        global $DB;

        $custom = $this->custom_guide();
        user_tours::register();
        $guides = $this->guides();
        require_once(__DIR__ . '/../db/uninstall.php');
        $this->assertTrue(xmldb_block_exaport_uninstall());
        foreach ($guides as $guide) {
            $this->assertFalse($DB->record_exists('tool_usertours_tours', ['id' => $guide->get_id()]));
            $this->assertFalse($DB->record_exists('tool_usertours_steps', ['tourid' => $guide->get_id()]));
        }
        $this->assertTrue($DB->record_exists('tool_usertours_tours', ['id' => $custom->get_id()]));
        $this->assertFalse(get_config('block_exaport', 'usertourssignature'));
    }

    public function test_uninstall_preserves_lookalikes_steps_and_every_other_users_preferences(): void {
        global $DB;

        $otherguides = [$this->custom_guide()];
        user_tours::register();
        $owned = $this->guides();
        // Include ordinary guides after the Exaport ones to exercise native sort-order renumbering.
        foreach ([
            ['block_exaport_tour' => 'upload-create-extra'],
            ['block_exaport_tour' => 'upload-create '],
            ['block_exaport_tour' => 1],
            ['block_exaport_tour' => ['item-content']],
            ['other_plugin_tour' => 'upload-create'],
            ['shipped_tour' => true, 'shipped_filename' => 'other.json', 'shipped_version' => 1],
        ] as $config) {
            $guide = $this->custom_guide($config);
            // Names and URLs do not grant Exaport ownership.
            $guide->set_name($owned['item-content']->get_name());
            $guide->set_pathmatch($owned['item-content']->get_pathmatch())->persist();
            $otherguides[] = $guide;
        }
        $owned['item-content']->set_name('Renamed by the site administrator')->persist();
        // Copies exported/imported with Exaport's exact identity are still Exaport-owned.
        $copy = $this->custom_guide(['block_exaport_tour' => 'item-content']);
        $owned[] = $copy;
        $owned[] = $this->legacy_guide('upload-content', 2);

        $users = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        foreach ($users as $user) {
            $this->setUser($user);
            foreach (array_merge($owned, $otherguides) as $guide) {
                $guide->mark_user_completed();
                $guide->request_user_reset();
            }
            // Preference removal must use the exact tour ID, not a numeric-prefix match.
            set_user_preference(\tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $copy->get_id() . '0', 123);
        }
        $records = [];
        $steps = [];
        foreach ($otherguides as $guide) {
            $record = $DB->get_record('tool_usertours_tours', ['id' => $guide->get_id()]);
            unset($record->sortorder);
            $records[$guide->get_id()] = $record;
            $steps[$guide->get_id()] = $DB->get_records('tool_usertours_steps', ['tourid' => $guide->get_id()]);
        }
        $preferences = $DB->get_records('user_preferences');
        foreach ($preferences as $id => $preference) {
            foreach ($owned as $guide) {
                if (in_array($preference->name, [
                    \tool_usertours\tour::TOUR_LAST_COMPLETED_BY_USER . $guide->get_id(),
                    \tool_usertours\tour::TOUR_REQUESTED_BY_USER . $guide->get_id(),
                ], true)) {
                    unset($preferences[$id]);
                }
            }
        }
        set_config('unrelatedsetting', 'keep', 'block_exaport');
        set_config('usertourssignature', 'other-component-marker', 'local_other');
        require_once(__DIR__ . '/../db/uninstall.php');
        $this->setAdminUser();
        $this->assertTrue(xmldb_block_exaport_uninstall());

        foreach ($owned as $guide) {
            $this->assertFalse($DB->record_exists('tool_usertours_tours', ['id' => $guide->get_id()]));
            $this->assertFalse($DB->record_exists('tool_usertours_steps', ['tourid' => $guide->get_id()]));
        }
        foreach ($records as $id => $expected) {
            $record = $DB->get_record('tool_usertours_tours', ['id' => $id], '*', MUST_EXIST);
            unset($record->sortorder);
            $this->assertEquals($expected, $record);
            $this->assertEquals($steps[$id], $DB->get_records('tool_usertours_steps', ['tourid' => $id]));
        }
        $this->assertEquals($preferences, $DB->get_records('user_preferences'));
        $this->assertSame('keep', get_config('block_exaport', 'unrelatedsetting'));
        $this->assertSame('other-component-marker', get_config('local_other', 'usertourssignature'));
        $this->assertFalse(get_config('block_exaport', 'usertourssignature'));
        $this->assertTrue(xmldb_block_exaport_uninstall(), 'Repeated cleanup must be safe.');
        $this->assertEquals($preferences, $DB->get_records('user_preferences'));
    }

    public function test_failed_import_rolls_back_and_releases_the_registration_lock(): void {
        global $DB;

        $this->preventResetByRollback();
        user_tours::register();
        foreach ($this->guides() as $guide) {
            $guide->set_config('block_exaport_tour_version', 0)->persist();
            $guide->mark_user_completed();
        }
        $retired = $this->legacy_guide('upload-create', 2);
        $retired->mark_user_completed();
        $retired->request_user_reset();
        set_config('usertourssignature', 'earlier-release', 'block_exaport');
        $records = $DB->get_records('tool_usertours_tours');
        $steps = $DB->get_records('tool_usertours_steps');
        $preferences = $DB->get_records('user_preferences');

        // Give an exact copy of the registrar its own deployment files, leaving the source tree untouched.
        $directory = make_request_directory();
        mkdir($directory . '/classes/local', 0777, true);
        mkdir($directory . '/db/usertours', 0777, true);
        $namespace = 'block_exaport\\local\\fixture_' . bin2hex(random_bytes(4));
        $source = file_get_contents(__DIR__ . '/../classes/local/user_tours.php');
        file_put_contents($directory . '/classes/local/user_tours.php',
            str_replace('namespace block_exaport\\local;', 'namespace ' . $namespace . ';', $source));
        file_put_contents($directory . '/db/usertours/item_content.json', '{');
        require($directory . '/classes/local/user_tours.php');
        $registrar = $namespace . '\\user_tours';

        $caught = null;
        // Turn the native importer's malformed-JSON warning into the expected exception.
        set_error_handler(static function($severity, $message, $file, $line): void {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $registrar::register();
        } catch (\Throwable $exception) {
            $caught = $exception;
        } finally {
            restore_error_handler();
        }
        $open = $DB->is_transaction_started();
        if ($open) {
            $DB->force_transaction_rollback();
        }
        $this->assertNotNull($caught, 'The malformed deployment file must interrupt synchronisation.');
        $this->assertFalse($open, 'Rollback must finish before returning control to a caller or task runner.');
        $this->assertEquals($records, $DB->get_records('tool_usertours_tours'));
        $this->assertEquals($steps, $DB->get_records('tool_usertours_steps'));
        $this->assertEquals($preferences, $DB->get_records('user_preferences'));
        $this->assertSame('earlier-release', get_config('block_exaport', 'usertourssignature'));
        $lock = \core\lock\lock_config::get_lock_factory('block_exaport')->get_lock('usertours', 0);
        $this->assertNotFalse($lock);
        if ($lock) {
            $lock->release();
        }

        copy(__DIR__ . '/../db/usertours/item_content.json', $directory . '/db/usertours/item_content.json');
        $registrar::register();
        $this->assertNotSame('earlier-release', get_config('block_exaport', 'usertourssignature'));
        $this->assertCount(1, $this->guides());
        foreach ($this->guides() as $guide) {
            $this->assertSame(1, $guide->get_config('block_exaport_tour_version'));
        }
    }

    public function test_completed_signature_skips_database_work_with_warm_configuration_cache(): void {
        global $DB;

        user_tours::register();
        // set_config() invalidates Moodle's cache; allow its normal single reload first.
        get_config('block_exaport', 'usertourssignature');
        $before = $DB->perf_get_queries();
        user_tours::register();
        $this->assertSame($before, $DB->perf_get_queries());
    }
}
