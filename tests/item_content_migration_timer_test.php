<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

use block_exaport\local\item_content_migration_timer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');

/** Exercise timeout policy with an injected clock rather than real delays. */
final class item_content_migration_timer_test extends \advanced_testcase {
    public function test_each_nine_minute_item_gets_a_fresh_ten_minute_window(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $now = 0.0;
        $timer = new item_content_migration_timer(static function() use (&$now): float { return $now; });
        for ($i = 0; $i < 3; $i++) {
            unset_config('upgraderunning');
            $before = time();
            $timer->before_item();
            $this->assertGreaterThanOrEqual($before + 600, (int)$CFG->upgraderunning);
            $this->assertLessThanOrEqual(time() + 600, (int)$CFG->upgraderunning);
            $now += 540;
        }
    }

    public function test_renewal_does_not_extend_the_overall_budget(): void {
        $this->resetAfterTest(true);
        $now = 0.0;
        $timer = new item_content_migration_timer(static function() use (&$now): float { return $now; });
        $timer->before_item();
        $now = 6480; // At 1h48 there is still a full item window plus cleanup time.
        $timer->before_item();
        $now += 540; // The nine-minute item completes at 1h57; no further item may start.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('itemcontentmigrationpaused', 'block_exaport'));
        $timer->before_item();
    }

    public function test_expired_budget_cannot_be_reported_as_complete(): void {
        $this->resetAfterTest(true);
        $now = 0.0;
        $timer = new item_content_migration_timer(static function() use (&$now): float { return $now; });
        $now = 7200;
        $this->expectException(\moodle_exception::class);
        $timer->check_deadline();
    }

    public function test_last_item_can_finish_inside_the_cleanup_reserve(): void {
        $this->resetAfterTest(true);
        $now = 0.0;
        $timer = new item_content_migration_timer(static function() use (&$now): float { return $now; });
        $now = 6540.0; // Exactly ten minutes plus one minute of cleanup remain.
        $timer->before_item();
        $now += 540;
        $timer->check_deadline();
        $this->assertSame(7080.0, $now);
    }
}
