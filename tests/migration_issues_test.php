<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

use block_exaport\local\migration_issues;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../db/upgradelib.php');
require_once(__DIR__ . '/../lib/item_content_helpers.php');

/** Findings from earlier scanning attempts remain durable during metadata-only retries. */
final class migration_issues_test extends \advanced_testcase {
    private array $backups = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
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
        $item = (object)['userid' => $ownerid, 'type' => 'note', 'name' => 'Migration fixture',
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
            $backup = $path . '.migration-test-backup';
            $this->assertTrue(rename($path, $backup));
            $this->backups[$file->get_id()] = [$path, $backup];
            // Seed evidence from an older attempt; the current migration must not rediscover or erase it.
            migration_issues::record($item, $file, 'source_unreadable');
        }
        return $file;
    }

    private function restore(\stored_file $file): void {
        [$path, $backup] = $this->backups[$file->get_id()];
        $this->assertTrue(rename($backup, $path));
        unset($this->backups[$file->get_id()]);
    }

    public function test_findings_survive_later_failure_and_are_included_after_retry(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $first = $this->item($owner->id);
        $firstfile = $this->file($first, true);
        $fatal = $this->item($owner->id);
        $fatalfile = $this->file($fatal, true);
        $later = $this->item($owner->id);
        try {
            \block_exaport_migrate_legacy_item_content_with_report(1, static function($item) use ($fatal) {
                return \block_exaport_migrate_legacy_item_content($item, static function($stage) use ($item, $fatal) {
                    if ($item->id == $fatal->id && $stage === 'file_copied') {
                        throw new \coding_exception('Unexpected write failure');
                    }
                });
            });
            $this->fail('Unexpected errors must stop the run');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('Legacy content migration failed', $exception->getMessage());
        }
        $firstissue = $DB->get_record(migration_issues::TABLE, ['fileid' => $firstfile->get_id()], '*', MUST_EXIST);
        $fatalissue = $DB->get_record(migration_issues::TABLE, ['fileid' => $fatalfile->get_id()], '*', MUST_EXIST);
        $this->assertGreaterThan(0, (int)$firstissue->destinationid);
        $this->assertSame(0, (int)$fatalissue->destinationid);
        $this->assertSame(0, (int)$fatalissue->timecopied);
        $this->assertSame(0, $DB->count_records('block_exaportmigration'));
        $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $fatal->id]));
        $this->assertEquals($first->id, get_config('block_exaport', 'item_content_migration_2026101902_lastid'));

        $third = $this->item($owner->id);
        $this->file($third, true);
        $report = \block_exaport_migrate_legacy_item_content_with_report(1);
        $summary = json_decode($report->summaryjson, true);
        $this->assertTrue($summary['status']['requires_review']);
        $this->assertFalse($summary['status']['clean']);
        $this->assertSame(3, $summary['issue_history_at_successful_run_end']['files_affected']);
        // First item's warning survives even though that item is a clean no-op on this attempt.
        $this->assertSame(0, $summary['operations_in_successful_run']['files_with_content_issues']);
        foreach ([$firstissue, $fatalissue] as $old) {
            $now = $DB->get_record(migration_issues::TABLE, ['id' => $old->id], '*', MUST_EXIST);
            $this->assertSame((int)$old->firstseen, (int)$now->firstseen);
            $this->assertSame($old->contenthash, $now->contenthash);
            $this->assertGreaterThan(0, (int)$now->destinationid);
        }
        $this->assertSame(1, (int)$DB->get_field(migration_issues::TABLE, 'attempts', ['id' => $firstissue->id]));
        $this->assertSame(1, (int)$DB->get_field(migration_issues::TABLE, 'attempts', ['id' => $fatalissue->id]));
        $this->assertSame(1, $DB->count_records('block_exaportitemblock', ['itemid' => $later->id]));
        $this->assertSame(2, $DB->count_records('block_exaportitemblock', ['itemid' => $first->id]));
    }

    public function test_missing_files_and_healthy_siblings_migrate_and_restore_without_retry(): void {
        global $DB, $CFG;
        $owner = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $CFG->block_exaport_app_alloweditdelete = true;
        $item = $this->item($owner->id);
        $missing = $this->file($item, true);
        $secondmissing = $this->file($item, true);
        $healthy = $this->file($item);
        $healthybytes = $healthy->get_content();
        $report = \block_exaport_migrate_legacy_item_content_with_report();
        $this->assertSame(2, migration_issues::counts()['files_affected']);
        $this->assertSame('', $DB->get_field('block_exaportitem', 'url', ['id' => $item->id]));
        $this->assertSame('historical marker', $DB->get_field('block_exaportitem', 'attachment', ['id' => $item->id]));
        $this->assertSame(3, $DB->count_records_select('files',
            'component = ? AND filearea = ? AND filename <> ?', ['block_exaport', 'item_content_file', '.']));
        $this->assertSame($item->id, (int)\block_exaport_get_editable_content_item($item->id, SITEID)->id);
        $this->assertSame(1, (int)$DB->count_records_sql('SELECT COUNT(1) FROM {block_exaportitem} i WHERE ' .
            \block_exaport_get_item_where()));
        $desthealthy = $DB->get_record('files', ['component' => 'block_exaport',
            'filearea' => 'item_content_file', 'filename' => $healthy->get_filename()], '*', MUST_EXIST);
        $this->assertSame($healthybytes, get_file_storage()->get_file_by_id($desthealthy->id)->get_content());
        foreach ([$missing, $secondmissing] as $source) {
            $issue = $DB->get_record(migration_issues::TABLE, ['fileid' => $source->get_id()], '*', MUST_EXIST);
            $destination = get_file_storage()->get_file_by_id($issue->destinationid);
            $this->assertSame($source->get_contenthash(), $issue->contenthash);
            $this->assertSame($source->get_contenthash(), $destination->get_contenthash());
            $this->assertSame($source->get_filepath(), $destination->get_filepath());
            $this->assertFalse(get_file_storage()->get_file_by_id($source->get_id()));
            $this->restore($source);
            $this->assertSame($issue->contenthash, sha1($destination->get_content()));
        }
        $this->assertSame(2, migration_issues::counts()['issues_recorded']);
        $this->assertSame($report->summaryjson, $DB->get_field('block_exaportmigration', 'summaryjson', ['id' => $report->id]));
    }

    public function test_crash_after_commit_keeps_destination_mapping_and_cannot_erase_warning(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $file = $this->file($item, true);
        try {
            \block_exaport_migrate_legacy_item_content_with_report(1, static function($item) {
                return \block_exaport_migrate_legacy_item_content($item, static function($stage) {
                    if ($stage === 'content_committed') {
                        throw new \coding_exception('Interruption after content commit');
                    }
                });
            });
            $this->fail('Expected interruption');
        } catch (\coding_exception $exception) {
            $issue = $DB->get_record(migration_issues::TABLE, ['fileid' => $file->get_id()], '*', MUST_EXIST);
            $this->assertGreaterThan(0, (int)$issue->destinationid);
            $this->assertGreaterThan(0, (int)$issue->timecopied);
        }
        // A crash after commit but before checkpointing may replay one item; it must never duplicate it.
        $this->assertFalse(get_config('block_exaport', 'item_content_migration_2026101902_lastid'));
        $report = \block_exaport_migrate_legacy_item_content_with_report(1);
        $summary = json_decode($report->summaryjson, true);
        $this->assertSame(0, $summary['operations_in_successful_run']['files_with_content_issues']);
        $this->assertSame(1, $summary['issue_history_at_successful_run_end']['files_affected']);
        $this->assertTrue($summary['status']['requires_review']);
        $this->assertSame(2, $DB->count_records('block_exaportitemblock'));
    }

    public function test_enclosing_transaction_cannot_erase_findings(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $file = $this->file($item, true);
        $transaction = $DB->start_delegated_transaction();
        try {
            migration_issues::record($item, $file, 'source_unreadable');
            $this->fail('Cannot promise durable findings inside another transaction');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('outside an enclosing transaction', $exception->getMessage());
        }
        $transaction->allow_commit();
        $this->assertSame(0, $DB->count_records('block_exaportitemblock'));
        \block_exaport_migrate_legacy_item_content($item);
        $this->assertSame(1, migration_issues::counts()['issues_recorded']);
    }

    public function test_source_content_is_not_opened_and_backend_mutation_errors_remain_fatal(): void {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->file($item);
        $fs = get_file_storage();
        $property = new \ReflectionProperty(\file_storage::class, 'filesystem');
        $filesystem = $fs->get_file_system();
        $fake = $this->createPartialMock(\file_system_filedir::class, ['get_content_file_handle']);
        $fake->expects($this->never())->method('get_content_file_handle');
        $property->setValue($fs, $fake);
        try {
            \block_exaport_migrate_legacy_item_content($item);
        } finally {
            $property->setValue($fs, $filesystem);
        }
        $item = $this->item($owner->id);
        $source = $this->file($item);
        $fake = $this->createPartialMock(\file_system_filedir::class, ['remove_file']);
        $fake->method('remove_file')->willThrowException(new \LogicException('Backend write failure'));
        $property->setValue($fs, $fake);
        try {
            \block_exaport_migrate_legacy_item_content($item);
            $this->fail('Unexpected backend mutation error was swallowed');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('Backend write failure', $exception->debuginfo);
            $this->assertSame(0, migration_issues::counts()['issues_recorded']);
            $this->assertNotFalse($fs->get_file_by_id($source->get_id()));
            $this->assertSame(0, $DB->count_records('block_exaportitemblock', ['itemid' => $item->id]));
        } finally {
            $property->setValue($fs, $filesystem);
        }
    }

    public function test_first_upgrade_creates_finding_table_before_migrating_files(): void {
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
        $this->assertStringContainsString('0 files with historical findings', $output);
        $this->assertSame(2026110502, (int)get_config('block_exaport', 'version'));
        $this->assertSame(0, migration_issues::counts()['issues_recorded']);
        $this->assertSame(2, $DB->count_records('block_exaportitemblock'));
    }

    public function test_retry_of_the_same_upgrade_keeps_committed_findings(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        $owner = $this->getDataGenerator()->create_user();
        $first = $this->item($owner->id);
        $missing = $this->file($first, true);
        $later = $this->item($owner->id);
        $laterfile = $this->file($later);
        set_config('version', 2026101901, 'block_exaport');
        $fs = get_file_storage();
        $filesystem = $fs->get_file_system();
        $property = new \ReflectionProperty(\file_storage::class, 'filesystem');
        $fake = $this->createPartialMock(\file_system_filedir::class, ['remove_file']);
        $fake->method('remove_file')->willReturnCallback(
            static function($hash) use ($filesystem, $laterfile) {
                if ($hash === $laterfile->get_contenthash()) {
                    throw new \LogicException('Interrupted upgrade');
                }
                return $filesystem->remove_file($hash);
            });
        $property->setValue($fs, $fake);
        ob_start();
        try {
            \xmldb_block_exaport_upgrade(2026101901);
            $this->fail('Expected upgrade interruption');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('Interrupted upgrade', $exception->debuginfo);
        } finally {
            $property->setValue($fs, $filesystem);
            ob_end_clean();
        }
        $original = $DB->get_record(migration_issues::TABLE, ['fileid' => $missing->get_id()], '*', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('block_exaportmigration'));
        $this->assertSame(2026101901, (int)get_config('block_exaport', 'version'));
        ob_start();
        try {
            $this->assertTrue(\xmldb_block_exaport_upgrade(2026101901));
        } finally {
            ob_end_clean();
        }
        $this->assertEquals($original, $DB->get_record(migration_issues::TABLE, ['id' => $original->id]));
        $report = $DB->get_record('block_exaportmigration', ['migrationversion' => migration_issues::VERSION], '*', MUST_EXIST);
        $summary = json_decode($report->summaryjson, true);
        $this->assertTrue($summary['status']['requires_review']);
        $this->assertSame(1, $summary['issue_history_at_successful_run_end']['files_affected']);
        $this->assertSame(2026110502, (int)get_config('block_exaport', 'version'));
    }

    public function test_local_storage_outage_remains_fatal(): void {
        global $CFG;
        $owner = $this->getDataGenerator()->create_user();
        $item = $this->item($owner->id);
        $this->file($item);
        $original = $CFG->filedir ?? null;
        $CFG->filedir = $CFG->dataroot . '/missing-storage-root';
        try {
            \block_exaport_migrate_legacy_item_content($item);
            $this->fail('Unavailable storage must stop migration');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('file storage is unavailable', $exception->getMessage());
            $this->assertSame(0, migration_issues::counts()['issues_recorded']);
        } finally {
            if ($original === null) { unset($CFG->filedir); } else { $CFG->filedir = $original; }
        }
    }

    public function test_privacy_erasure_is_scoped_to_the_owner(): void {
        foreach ([$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()] as $owner) {
            $item = $this->item($owner->id);
            $this->file($item, true);
            \block_exaport_migrate_legacy_item_content($item);
        }
        \block_exaport\privacy\provider::delete_data_for_all_users_in_context(\context_user::instance($owner->id));
        $this->assertSame(1, migration_issues::counts()['issues_recorded']);
    }
}
