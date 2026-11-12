<?php
// This file is part of Moodle - http://moodle.org/.

namespace block_exaport\task;

defined('MOODLE_INTERNAL') || die();

/** Convert legacy content without keeping an administrator's browser request open. */
final class migrate_item_content extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('itemcontentmigrationtask', 'block_exaport');
    }

    public function execute(): void {
        $lock = \core\lock\lock_config::get_lock_factory('block_exaport')->get_lock('item_content_migration', 0);
        if (!$lock) {
            throw new \moodle_exception('itemcontentmigrationbusy', 'block_exaport');
        }
        try {
            if (\block_exaport\local\item_content_migration::run_batch()) {
                // The current task still exists. De-duplicating here would discard its continuation.
                \core\task\manager::queue_adhoc_task(new self());
            }
        } catch (\Throwable $exception) {
            \block_exaport\local\item_content_migration::record_error($exception);
            throw $exception; // Moodle retains the failed task and retries it normally.
        } finally {
            $lock->release();
        }
    }
}
