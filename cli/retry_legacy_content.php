<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../db/upgradelib.php');

use block_exaport\local\migration_issues;

[$options, $unrecognised] = cli_get_params(['help' => false, 'itemid' => 0], ['h' => 'help']);
if ($options['help']) {
    echo "Retry deferred Exaport content after repairing its backing storage.\n" .
        "Usage: php blocks/exaport/cli/retry_legacy_content.php [--itemid=123]\n" .
        "Without --itemid, retry all pending existing items in bounded batches.\n" .
        "History and the original upgrade summary are retained.\n";
    exit(0);
}
if ($unrecognised || !ctype_digit((string)$options['itemid'])) {
    cli_error('Use --help for valid options.');
}
if ((int)get_config('block_exaport', 'version') < 2026103000) {
    cli_error('Complete the Moodle upgrade before running content recovery.');
}
migration_issues::require_independent_commits();
$lastid = 0;
$processed = 0;
do {
    $params = ['migrationversion' => migration_issues::VERSION, 'lastid' => $lastid];
    $where = '';
    if ($options['itemid']) {
        $where = ' AND i.id = :itemid';
        $params['itemid'] = (int)$options['itemid'];
    }
    $items = $DB->get_records_sql('SELECT i.* FROM {block_exaportitem} i
        WHERE i.id > :lastid AND EXISTS (SELECT 1 FROM {block_exaportmigissue} m
            WHERE m.itemid = i.id AND m.migrationversion = :migrationversion AND m.timeresolved = 0)' .
        $where . ' ORDER BY i.id ASC', $params, 0, 50);
    core_php_time_limit::raise(1800);
    foreach ($items as $item) {
        block_exaport_migrate_legacy_item_content($item);
        $lastid = (int)$item->id;
        $processed++;
    }
    mtrace("Exaport content recovery: {$processed} items processed.");
} while (count($items) === 50);
$counts = migration_issues::counts();
mtrace("Exaport content recovery: {$counts['items_pending']} items still pending; history retained.");
exit($counts['items_pending'] ? 1 : 0);
