<?php
// This file is part of Moodle - http://moodle.org/.

namespace block_exaport;

use block_exaport\local\item_content_migration as migration;

defined('MOODLE_INTERNAL') || die();

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @covers \block_exaport\local\item_content_migration */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class item_content_background_migration_test extends \advanced_testcase {
    private function item(int $userid, string $url = ''): \stdClass {
        global $DB;
        $item = (object)['userid' => $userid, 'type' => 'file', 'name' => 'Background fixture',
            'url' => $url, 'intro' => '', 'attachment' => 'historical marker',
            'courseid' => 0, 'timecreated' => 100, 'timemodified' => 200];
        $item->id = $DB->insert_record('block_exaportitem', $item);
        return $item;
    }

    private function ready_versions(): void {
        set_config('version', migration::SCHEMA_VERSION, 'block_exaport');
        set_config('version', \block_exaport\local\exacomp_compatibility::MIN_VERSION, 'block_exacomp');
    }

    public function test_scheduling_does_not_convert_content_and_retry_deduplicates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ready_versions();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->item($user->id, 'https://example.invalid/original');
        migration::schedule();
        migration::retry();
        migration::retry();
        $this->assertTrue(migration::is_pending());
        $this->assertSame($item->url, $DB->get_field('block_exaportitem', 'url', ['id' => $item->id]));
        $this->assertFalse($DB->record_exists('block_exaportitemblock', []));
        $this->assertSame(1, $DB->count_records('task_adhoc', ['classname' => '\\block_exaport\\task\\migrate_item_content']));
        $this->assertFalse($DB->record_exists('block_exaportmigration', []));
    }

    public function test_completed_job_preserves_bytes_order_identity_and_immutable_report(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ready_versions();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->item($user->id, 'https://example.invalid/original?A=1');
        $context = \context_user::instance($user->id);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'block_exaport', 'filearea' => 'item_file',
            'itemid' => $item->id, 'filepath' => '/nested path/', 'filename' => '雪 file.txt', 'userid' => $user->id,
        ], 'Distinct original bytes');
        $upgraderunning = get_config(null, 'upgraderunning');
        migration::schedule();
        $this->expectOutputRegex('/Exaport background migration:/');
        $this->assertFalse(migration::run_batch());
        $this->assertFalse(migration::is_pending());
        $this->assertSame($upgraderunning, get_config(null, 'upgraderunning'));
        $parent = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
        $this->assertSame((int)$item->userid, (int)$parent->userid);
        $this->assertSame('historical marker', $parent->attachment);
        $this->assertSame('', $parent->url);
        $blocks = array_values($DB->get_records('block_exaportitemblock', ['itemid' => $item->id], 'sortorder'));
        $this->assertSame(['link', 'file'], array_column($blocks, 'type'));
        $this->assertSame($item->url, $blocks[0]->url);
        $copied = array_values(get_file_storage()->get_area_files($context->id, 'block_exaport',
            'item_content_file', $blocks[1]->id, 'id', false));
        $this->assertCount(1, $copied);
        $this->assertSame('Distinct original bytes', $copied[0]->get_content());
        $this->assertSame($file->get_contenthash(), $copied[0]->get_contenthash());
        $this->assertSame('/nested path/', $copied[0]->get_filepath());
        $this->assertSame('雪 file.txt', $copied[0]->get_filename());
        $this->assertSame((int)$user->id, (int)$copied[0]->get_userid());
        $report = $DB->get_record('block_exaportmigration', ['migrationversion' => migration::VERSION], '*', MUST_EXIST);
        $summary = json_decode($report->summaryjson, true);
        $this->assertSame(5, (int)$report->formatversion);
        $this->assertSame(1, $summary['operations_in_background_job']['files_copied']);
        migration::schedule();
        $this->assertFalse(migration::run_batch());
        $this->assertEquals($report, $DB->get_record('block_exaportmigration', ['id' => $report->id]));
        $this->assertSame(2, $DB->count_records('block_exaportitemblock'));
    }

    public function test_checkpoint_continues_across_bounded_batches_and_counts_all_committed_work(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ready_versions();
        $user = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < 501; $i++) {
            $this->item($user->id, 'https://example.invalid/' . $i);
        }
        migration::schedule();
        $this->expectOutputRegex('/Exaport background migration:/');
        $this->assertTrue(migration::run_batch());
        $this->assertTrue(migration::is_pending());
        $this->assertSame(500, migration::state()['operations_in_background_job']['items_processed']);
        $this->assertSame(500, $DB->count_records('block_exaportitemblock'));
        $this->assertFalse($DB->record_exists('block_exaportmigration', []));
        $this->assertFalse(migration::run_batch());
        $this->assertFalse(migration::is_pending());
        $this->assertSame(501, $DB->count_records('block_exaportitemblock'));
        $report = $DB->get_record('block_exaportmigration', ['migrationversion' => migration::VERSION], '*', MUST_EXIST);
        $counts = json_decode($report->summaryjson, true)['operations_in_background_job'];
        $this->assertSame(501, $counts['items_processed']);
        $this->assertSame(501, $counts['link_blocks_created']);
    }

    public function test_pending_blocks_even_preloaded_integration_library(): void {
        $this->resetAfterTest();
        if (!class_exists('\\block_exacomp\\local\\exaport_compatibility')) {
            $this->markTestSkipped('Optional Exacomp is not installed.');
        }
        $this->ready_versions();
        \block_exacomp\local\exaport_compatibility::require_available();
        migration::schedule();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('itemcontentmigrationpending', 'block_exacomp'));
        \block_exacomp\local\exaport_compatibility::require_available();
    }

    public function test_upgrade_of_a_fresh_structured_release_does_not_pause_or_invent_a_report(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        $this->assertFalse($DB->record_exists('block_exaportmigration', []));
        set_config('version', 2026111202, 'block_exaport');
        $this->assertTrue(\xmldb_block_exaport_upgrade(2026111202));
        $this->assertFalse(migration::is_pending());
        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\block_exaport\\task\\migrate_item_content']));
        $this->assertFalse($DB->record_exists('block_exaportmigration', []));
    }

    public function test_background_task_waits_for_the_global_upgrade_to_finish(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ready_versions();
        migration::schedule();
        set_config('upgraderunning', time() + 60);
        try {
            migration::run_batch();
            $this->fail('Migration ran while Moodle was still upgrading.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('itemcontentmigrationwaitingupgrade', $exception->errorcode);
        }
        $this->assertTrue(migration::is_pending());
        $this->assertFalse($DB->record_exists('block_exaportmigration', []));
        $this->assertSame([], migration::state());
    }

    public function test_registered_services_refuse_calls_before_business_logic(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ready_versions();
        migration::schedule();
        $functions = $DB->get_records('external_functions', ['component' => 'block_exaport']);
        $this->assertNotEmpty($functions);
        foreach ($functions as $function) {
            $method = new \ReflectionMethod($function->classname, $function->methodname);
            $args = [];
            foreach ($method->getParameters() as $parameter) {
                $args[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() :
                    match ((string)$parameter->getType()) {
                        'int' => 0, 'string' => '', 'array' => [], 'bool' => false, 'float' => 0.0, default => null,
                    };
            }
            try {
                $method->invokeArgs(null, $args);
                $this->fail('Pending migration allowed ' . $function->name);
            } catch (\moodle_exception $exception) {
                $this->assertSame('itemcontentmigrationpending', $exception->errorcode, $function->name);
            }
        }
        $this->assertNull(\block_exaport_get_tagged_items((object)['id' => 0]));
        $this->assertSame(0, $DB->count_records('block_exaportitem'));
    }
}
