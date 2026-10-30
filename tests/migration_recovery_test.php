<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

use block_exaport\local\migration_issues;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../db/upgradelib.php');
require_once(__DIR__ . '/../lib/item_content_helpers.php');

/** Recovery must preserve data and committed history across failures and retries. */
final class migration_recovery_test extends \advanced_testcase {
    private array $backups = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        // Exercise real commits, not the test harness's enclosing rollback transaction.
        $this->preventResetByRollback();
    }

    protected function tearDown(): void {
        foreach ($this->backups as [$path, $backup]) {
            if (file_exists($backup)) {
                rename($backup, $path);
            }
        }
        parent::tearDown();
    }

    private function item(int $ownerid): \stdClass {
        global $DB;
        $item = (object)['userid' => $ownerid, 'type' => 'note', 'name' => 'Recovery fixture',
            'url' => 'https://private.example.test/', 'intro' => '', 'attachment' => 'historical marker',
            'timecreated' => 100, 'timemodified' => 200, 'isoez' => 1];
        $item->id = (int)$DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    private function file(\stdClass $item, bool $broken = false): \stored_file {
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($item->userid)->id, 'component' => 'block_exaport',
            'filearea' => 'item_file', 'itemid' => $item->id, 'userid' => $item->userid,
            'filepath' => '/nested/', 'filename' => random_string(10) . '.txt',
        ], random_string(50));
        if ($broken) {
            $path = get_file_storage()->get_file_system()->get_remote_path_from_storedfile($file);
            $backup = $path . '.recovery-test-backup';
            $this->assertTrue(rename($path, $backup));
            $this->backups[$file->get_id()] = [$path, $backup];
        }
        return $file;
    }

    private function restore(\stored_file $file): void {
        [$path, $backup] = $this->backups[$file->get_id()];
        $this->assertTrue(rename($backup, $path));
        unset($this->backups[$file->get_id()]);
    }

    public function test_report_accumulates_across_fatal_rollback_and_recovery(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $first = $this->item($owner->id);
        $missing = $this->file($first, true);
        $healthy = $this->item($owner->id);
        $second = $this->item($owner->id);
        $this->file($second, true);
        $fatal = $this->item($owner->id);
        $this->file($fatal);
        $later = $this->item($owner->id);

        try {
            \block_exaport_migrate_legacy_item_content_with_report(2,
                static function($item) use ($fatal) {
                    return \block_exaport_migrate_legacy_item_content($item, static function($stage) use ($item, $fatal) {
                        if ($item->id == $fatal->id && $stage === 'file_copied') {
                            throw new \coding_exception('Unexpected write failure');
                        }
                    });
                });
            $this->fail('Unexpected migration errors must stop the run');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('Legacy content migration failed', $exception->getMessage());
        }
        $this->assertSame(2, migration_issues::counts()['issues_recorded']);
        $this->assertSame(0, $DB->count_records('block_exaportmigration'));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $fatal->id]));
        $originalissues = $DB->get_records(migration_issues::TABLE, [], 'id');
        $third = $this->item($owner->id);
        $this->file($third, true);

        $report = \block_exaport_migrate_legacy_item_content_with_report(2);
        $summary = json_decode($report->summaryjson, true);
        $this->assertSame(3, $summary['issue_history_at_successful_run_end']['issues_recorded']);
        $this->assertTrue($summary['status']['requires_review']);
        foreach ($originalissues as $original) {
            $current = $DB->get_record(migration_issues::TABLE, ['id' => $original->id], '*', MUST_EXIST);
            $this->assertSame((int)$original->firstseen, (int)$current->firstseen);
            $this->assertSame(2, (int)$current->attempts);
        }
        foreach ([$healthy, $later] as $item) {
            $this->assertSame(1, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        }
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $first->id]));
        $this->assertSame($first->url, $DB->get_field('block_exaportitem', 'url', ['id' => $first->id]));

        $this->restore($missing);
        \block_exaport_migrate_legacy_item_content($first);
        \block_exaport_migrate_legacy_item_content($first);
        $this->assertFalse(migration_issues::pending($first->id));
        $this->assertSame(3, migration_issues::counts()['issues_recorded']);
        $this->assertSame(2, migration_issues::counts()['issues_unresolved']);
        $this->assertSame(2, $DB->count_records('block_exaportitemblock', ['itemid' => $first->id]));
        $this->assertSame($report->summaryjson, $DB->get_field('block_exaportmigration', 'summaryjson', ['id' => $report->id]));
    }

    public function test_whole_item_is_preserved_and_all_bad_sources_are_recorded(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->file($item, true);
        $this->file($item, true);
        $healthy = $this->file($item);
        $parent = $DB->get_record('block_exaportitem', ['id' => $item->id]);
        $files = $DB->get_records('files', ['component' => 'block_exaport', 'filearea' => 'item_file']);
        \block_exaport_migrate_legacy_item_content($item);
        $this->assertEquals($parent, $DB->get_record('block_exaportitem', ['id' => $item->id]));
        $this->assertEquals($files, $DB->get_records('files', ['component' => 'block_exaport', 'filearea' => 'item_file']));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        $this->assertSame(2, migration_issues::counts()['issues_recorded']);
        $this->assertNotEmpty(get_file_storage()->get_file_by_id($healthy->get_id())->get_content());
    }

    public function test_crash_after_content_commit_is_reconciled_without_duplicates(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $file = $this->file($item, true);
        \block_exaport_migrate_legacy_item_content($item);
        $this->restore($file);
        try {
            \block_exaport_migrate_legacy_item_content($item, static function($stage) {
                if ($stage === 'content_committed') {
                    throw new \coding_exception('Simulated interruption before issue resolution');
                }
            });
            $this->fail('Expected interruption');
        } catch (\coding_exception $exception) {
            $this->assertTrue(migration_issues::pending($item->id));
        }
        $this->assertSame(2, $DB->count_records('block_exaportitemblock'));
        \block_exaport_migrate_legacy_item_content($item);
        $this->assertFalse(migration_issues::pending($item->id));
        $this->assertSame(2, $DB->count_records('block_exaportitemblock'));
    }

    public function test_enclosing_transaction_cannot_swallow_issue_history(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->file($item, true);
        $transaction = $DB->start_delegated_transaction();
        try {
            \block_exaport_migrate_legacy_item_content($item);
            $this->fail('Cannot promise durable history inside another transaction');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('outside an enclosing transaction', $exception->getMessage());
        }
        $transaction->allow_commit();
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        \block_exaport_migrate_legacy_item_content($item);
        $this->assertTrue(migration_issues::pending($item->id));
    }

    public function test_backend_exceptions_are_fatal_and_are_not_recorded_as_damaged_files(): void {
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->file($item);
        $fs = get_file_storage();
        $property = new \ReflectionProperty(\file_storage::class, 'filesystem');
        $filesystem = $fs->get_file_system();
        $fake = $this->createPartialMock(\file_system_filedir::class, ['get_content_file_handle']);
        $fake->method('get_content_file_handle')->willThrowException(new \RuntimeException('Storage unavailable'));
        $property->setValue($fs, $fake);
        try {
            \block_exaport_migrate_legacy_item_content($item);
            $this->fail('Backend outage must remain fatal');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Storage unavailable', $exception->getMessage());
            $this->assertSame(0, migration_issues::counts()['issues_recorded']);
        } finally {
            $property->setValue($fs, $filesystem);
        }
    }

    public function test_pending_item_stays_visible_and_cannot_be_edited(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $item = $this->item($owner->id);
        $this->file($item, true);
        \block_exaport_migrate_legacy_item_content($item);
        $where = \block_exaport_get_item_where();
        $this->assertSame(1, (int)$DB->count_records_sql("SELECT COUNT(1) FROM {block_exaportitem} i WHERE $where"));
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('migrationpending', 'block_exaport'));
        \block_exaport_get_editable_content_item($item->id, SITEID);
    }

    public function test_interrupted_upgrade_creates_issue_schema_before_historical_migration(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->file($item, true);
        $DB->get_manager()->drop_table(new \xmldb_table(migration_issues::TABLE));
        set_config('version', 2026101901, 'block_exaport');
        ob_start();
        try {
            $this->assertTrue(\xmldb_block_exaport_upgrade(2026101901));
        } finally {
            $output = ob_get_clean();
        }
        $this->assertStringContainsString('1 items awaiting content recovery', $output);
        $this->assertTrue(migration_issues::pending($item->id));
        $this->assertSame(2026103000, (int)get_config('block_exaport', 'version'));
        $this->assertSame(1, $DB->count_records('block_exaportmigration'));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
    }

    public function test_completed_sites_receive_schema_without_rewriting_historical_report(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        $reportid = $DB->insert_record('block_exaportmigration', [
            'migrationversion' => migration_issues::VERSION, 'formatversion' => 1,
            'timestarted' => 100, 'timecompleted' => 200, 'summaryjson' => '{"historical":true}',
        ]);
        $DB->get_manager()->drop_table(new \xmldb_table(migration_issues::TABLE));
        set_config('version', 2026102900, 'block_exaport');
        $this->assertTrue(\xmldb_block_exaport_upgrade(2026102900));
        $this->assertSame(0, migration_issues::counts()['issues_recorded']);
        $this->assertSame('{"historical":true}', $DB->get_field('block_exaportmigration', 'summaryjson', ['id' => $reportid]));
    }

    public function test_local_storage_outage_remains_fatal(): void {
        global $CFG;
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->file($item);
        $original = $CFG->filedir ?? null;
        $CFG->filedir = $CFG->dataroot . '/missing-recovery-storage-root';
        try {
            \block_exaport_migrate_legacy_item_content($item);
            $this->fail('Unavailable storage must stop migration');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('file storage is unavailable', $exception->getMessage());
            $this->assertSame(0, migration_issues::counts()['issues_recorded']);
        } finally {
            if ($original === null) {
                unset($CFG->filedir);
            } else {
                $CFG->filedir = $original;
            }
        }
    }

    public function test_privacy_erasure_of_issue_history_is_scoped_to_its_owner(): void {
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        foreach ([$first, $second] as $owner) {
            $item = $this->item($owner->id);
            $this->file($item, true);
            \block_exaport_migrate_legacy_item_content($item);
        }
        \block_exaport\privacy\provider::delete_data_for_all_users_in_context(\context_user::instance($first->id));
        $this->assertSame(1, migration_issues::counts()['issues_recorded']);
    }
}
