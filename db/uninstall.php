<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

defined('MOODLE_INTERNAL') || die();

/** Remove the native User tours owned by Exaport. */
function xmldb_block_exaport_uninstall() {
    \block_exaport\local\user_tours::uninstall();
    return true;
}
