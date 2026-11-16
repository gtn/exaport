<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/** Exaport is standalone, but an existing Exacomp must understand structured content. */
function xmldb_block_exaport_install() {
    \block_exaport\local\exacomp_compatibility::require_compatible_code();
    // Synchronise through the tool's API only after all plugin schemas are ready.
    \core\task\manager::queue_adhoc_task(new \block_exaport\task\register_user_tours(), true);
}

/** Recheck after an installation was interrupted by the compatibility guard. */
function xmldb_block_exaport_install_recovery() {
    xmldb_block_exaport_install();
}
