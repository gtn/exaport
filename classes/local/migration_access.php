<?php
// This file is part of Moodle - http://moodle.org/.

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** Pause plugin pages while leaving Moodle and the administrator's recovery pages available. */
final class migration_access {
    public static function after_config(\core\hook\after_config $hook): void {
        global $SCRIPT;
        if (CLI_SCRIPT || !preg_match('~^/blocks/(exaport|exacomp)/~', $SCRIPT ?? '')) {
            return;
        }
        if (in_array($SCRIPT, [
                '/blocks/exaport/item_content_migration_status.php',
                '/blocks/exaport/item_content_audit.php',
                '/blocks/exaport/item_content_migration.php',
        ], true)) {
            return; // These pages perform their own login and capability checks.
        }
        item_content_migration::require_ready();
    }
}
