<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/** Exaport is standalone, but an existing Exacomp must understand structured content. */
function xmldb_block_exaport_install() {
    \block_exaport\local\exacomp_compatibility::require_compatible_code();
}

/** Recheck after an installation was interrupted by the compatibility guard. */
function xmldb_block_exaport_install_recovery() {
    xmldb_block_exaport_install();
}
