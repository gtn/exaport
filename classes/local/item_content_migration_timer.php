<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** Per-item timeout renewal and a fixed elapsed-time budget for one upgrade attempt. */
final class item_content_migration_timer {
    private const ITEM_SECONDS = 600;
    private const RUN_SECONDS = 7200;
    private const CLEANUP_SECONDS = 60;

    /** @var \Closure Monotonic seconds; injectable so tests never need to wait. */
    private \Closure $clock;
    private float $deadline;

    public function __construct(?callable $clock = null) {
        // Wall-clock adjustments and PHP's CPU-time accounting must not extend the two-hour budget.
        $this->clock = $clock === null ? static fn(): float => hrtime(true) / 1e9 : \Closure::fromCallable($clock);
        $this->deadline = ($this->clock)() + self::RUN_SECONDS;
    }

    /** Called between item transactions, so a pause preserves every committed item. */
    public function before_item(): void {
        // Reserve a complete ten-minute item window plus time for the report and normal Moodle cleanup.
        if ($this->remaining() < self::ITEM_SECONDS + self::CLEANUP_SECONDS) {
            throw new \moodle_exception('itemcontentmigrationpaused', 'block_exaport');
        }
        // Renew for each item, independently of the 500-row database fetch size.
        \upgrade_set_timeout(self::ITEM_SECONDS);
    }

    /** Never publish a completed report after the overall budget has expired. */
    public function check_deadline(): void {
        if ($this->remaining() <= 0) {
            throw new \moodle_exception('itemcontentmigrationpaused', 'block_exaport');
        }
    }

    private function remaining(): float {
        return $this->deadline - ($this->clock)();
    }
}
