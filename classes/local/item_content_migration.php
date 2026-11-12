<?php
// This file is part of Moodle - http://moodle.org/.

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** Durable background migration state, independent of Moodle's upgrade lock. */
final class item_content_migration {
    public const VERSION = 2026101902;
    public const SCHEMA_VERSION = 2026111203;
    private const PENDING = 'item_content_migration_pending';
    private const STATE = 'item_content_migration_state';
    private const CHECKPOINT = 'item_content_migration_2026101902_lastid';

    /** Read committed database state; a rolled-back transaction must not publish cached progress. */
    private static function config(string $name) {
        global $DB;
        return $DB->get_field('config_plugins', 'value', ['plugin' => 'block_exaport', 'name' => $name]);
    }

    public static function is_pending(): bool {
        $version = (int)self::config('version');
        // Also protect ordinary users between deploying the new code and running the upgrade.
        return ($version > 0 && $version < self::VERSION) || self::config(self::PENDING) === '1';
    }

    public static function require_ready(): void {
        if (self::is_pending()) {
            throw new \moodle_exception('itemcontentmigrationpending', 'block_exaport');
        }
    }

    public static function state(): array {
        return json_decode(self::config(self::STATE) ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }

    private static function save(array $state): void {
        global $DB;
        $conditions = ['plugin' => 'block_exaport', 'name' => self::STATE];
        $value = json_encode($state, JSON_THROW_ON_ERROR);
        // This private cursor is deliberately never published to MUC before its transaction commits.
        if ($DB->record_exists('config_plugins', $conditions)) {
            $DB->set_field('config_plugins', 'value', $value, $conditions);
        } else {
            $DB->insert_record('config_plugins', (object)($conditions + ['value' => $value]));
        }
    }

    /** Called only after the upgrade has prepared all required tables. Existing reports stay immutable. */
    public static function schedule(): bool {
        global $DB;
        if ($DB->record_exists('block_exaportmigration', ['migrationversion' => self::VERSION])) {
            return false;
        }
        set_config(self::PENDING, 1, 'block_exaport');
        self::retry();
        return true;
    }

    /** Reuse a queued/failed task without changing content, versions, reports or running locks. */
    public static function retry(): void {
        if (!self::is_pending()) {
            return;
        }
        $task = new \block_exaport\task\migrate_item_content();
        $task->set_next_run_time(time() - 1);
        \core\task\manager::reschedule_or_queue_adhoc_task($task);
    }

    public static function record_error(\Throwable $exception): void {
        $state = self::state();
        $state['error'] = $exception->getMessage();
        $state['updated'] = time();
        self::save($state);
    }

    /** One bounded task execution. The caller holds the migration lock. Returns whether more work remains. */
    public static function run_batch(): bool {
        global $CFG, $DB;
        if (!self::is_pending()) {
            return false;
        }
        // An already-running cron can pick up a task before the enclosing Moodle upgrade finishes.
        if ((int)self::config('version') < self::SCHEMA_VERSION ||
                (int)$DB->get_field('config', 'value', ['name' => 'upgraderunning']) > time()) {
            throw new \moodle_exception('itemcontentmigrationwaitingupgrade', 'block_exaport');
        }
        exacomp_compatibility::require_compatible_code();
        if (is_dir($CFG->dirroot . '/blocks/exacomp') &&
                (int)get_config('block_exacomp', 'version') < exacomp_compatibility::MIN_VERSION) {
            throw new \moodle_exception('itemcontentmigrationwaitingupgrade', 'block_exaport');
        }
        require_once($CFG->dirroot . '/blocks/exaport/db/upgradelib.php');
        $state = self::state();
        if (empty($state['started'])) {
            $state = [
                'started' => time(), 'phase' => 'items',
                'lastitemid' => max(0, (int)self::config(self::CHECKPOINT)),
                'source_counts_at_background_job_start' => block_exaport_legacy_item_content_counts(),
                'operations_in_background_job' => array_fill_keys([
                    'items_processed', 'items_already_clean', 'link_blocks_created', 'file_blocks_created',
                    'files_copied', 'urls_cleared', 'legacy_file_areas_cleared',
                ], 0),
            ];
            $state['initial_checkpoint'] = $state['lastitemid'];
            self::save($state);
        }
        unset($state['error']);
        if ($state['phase'] === 'items') {
            $items = $DB->get_records_select('block_exaportitem', 'id > ?', [$state['lastitemid']],
                'id ASC', 'id, userid', 0, 500);
            // Yield between items as well as between batches, without setting Moodle's global upgrade lock.
            $deadline = hrtime(true) + 60 * 1000000000;
            foreach ($items as $item) {
                $transaction = $DB->start_delegated_transaction();
                try {
                    $result = block_exaport_migrate_legacy_item_content($item);
                    $counts =& $state['operations_in_background_job'];
                    $counts['items_processed']++;
                    $counts['items_already_clean'] += $result['alreadyclean'];
                    $counts['link_blocks_created'] += empty($result['linkblockid']) ? 0 : 1;
                    $counts['file_blocks_created'] += empty($result['fileblockid']) ? 0 : 1;
                    $counts['files_copied'] += $result['filecount'];
                    $counts['urls_cleared'] += $result['urlcleared'];
                    $counts['legacy_file_areas_cleared'] += $result['legacyfileareacleared'];
                    unset($counts);
                    $state['lastitemid'] = (int)$item->id;
                    $state['updated'] = time();
                    // The cursor and counters commit with the content, including on process death.
                    self::save($state);
                    $transaction->allow_commit();
                } catch (\Throwable $exception) {
                    $transaction->rollback($exception);
                }
                if (hrtime(true) >= $deadline) {
                    return true;
                }
            }
            mtrace('Exaport background migration: ' . $state['operations_in_background_job']['items_processed'] .
                ' items committed');
            if (count($items) === 500) {
                return true;
            }
            $state['phase'] = 'events';
            self::save($state);
        }

        $events = '\\block_exacomp\\local\\deferred_events';
        if (class_exists($events) && !$events::drain(100)) {
            return true;
        }
        // Aggregate queries can be slow. Never hold the observer gate while scanning the file table.
        self::write_report($state);
        $finish = static function() use (&$state, $DB): void {
            $transaction = $DB->start_delegated_transaction();
            try {
                $state['phase'] = 'completed';
                $state['updated'] = time();
                self::save($state);
                unset_config(self::CHECKPOINT, 'block_exaport');
                $transaction->allow_commit();
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
            // Keep the stable pause flag until the report and deferred events have really committed.
            unset_config(self::PENDING, 'block_exaport');
        };
        if (class_exists($events)) {
            // Enqueuing an event and the final empty-queue check share a short lock: no lost last event.
            return !$events::finish($finish);
        }
        $finish();
        return false;
    }

    /** Report content conversion; plugin readiness additionally requires the observer queue to be drained. */
    private static function write_report(array $state): void {
        global $DB;
        if ($DB->record_exists('block_exaportmigration', ['migrationversion' => self::VERSION])) {
            return;
        }
        $after = block_exaport_legacy_item_content_counts();
        $residuals = $after;
        unset($residuals['total_items']);
        $issues = migration_issues::counts();
        $clean = !array_filter($residuals) && !$issues['issues_recorded'];
        $summary = [
            'status' => ['clean' => $clean, 'requires_review' => !$clean],
            'source_counts_at_background_job_start' => $state['source_counts_at_background_job_start'],
            'operations_in_background_job' => $state['operations_in_background_job'],
            'residual_counts_at_background_job_end' => $after,
            'issue_history_at_background_job_end' => $issues,
            'resume' => ['initial_checkpoint' => $state['initial_checkpoint']],
        ];
        $DB->insert_record('block_exaportmigration', (object)[
            'migrationversion' => self::VERSION, 'formatversion' => 5,
            'timestarted' => $state['started'], 'timecompleted' => time(),
            'summaryjson' => json_encode($summary, JSON_THROW_ON_ERROR),
        ]);
    }
}
