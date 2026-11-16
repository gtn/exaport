<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

namespace block_exaport\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Synchronise native Exaport guides after plugin installation and upgrades finish.
 *
 * @package block_exaport
 * @copyright 2026 GTN GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class register_user_tours extends \core\task\adhoc_task {
    /** Run against the fully upgraded User tours tool. */
    public function execute(): void {
        \block_exaport\local\user_tours::register();
    }
}
