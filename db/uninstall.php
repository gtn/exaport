<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

defined('MOODLE_INTERNAL') || die();

/** Remove Exaport-owned data before Moodle drops the plugin's tables. */
function xmldb_block_exaport_uninstall() {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    try {
        // Exacomp keeps these tables. Remove our links, including orphans, before item IDs can restart.
        $tables = [
            'block_exacompcompactiv_mm' => ['eportfolioitem' => 1],
            'block_exacompcompuser_mm' => ['eportfolioitem' => 1],
            'block_exacompitem_mm' => [],
            'block_exacompitemcollab_mm' => [],
            'block_exacompprofilesettings' => ['block' => 'exaport'],
        ];
        $dbman = $DB->get_manager();
        foreach ($tables as $table => $conditions) {
            // Exacomp is optional; uninstall must also work when its code is disabled or absent.
            if ($dbman->table_exists(new xmldb_table($table))) {
                // The two item-only tables contain exclusively Exaport links. [] keeps this transactional DELETE.
                $DB->delete_records($table, $conditions);
            }
        }
        \block_exaport\local\user_tours::uninstall();
        $transaction->allow_commit();
    } catch (\Throwable $exception) {
        $transaction->rollback($exception);
    }
    return true;
}
