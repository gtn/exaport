<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use block_exaport\local\item_content_audit;
use block_exaport\local\item_content_audit_output;

$help = <<<'HELP'
Read-only audit of Exaport legacy and structured item content.

Options:
--help                 Show this help.
--json                 Emit machine-readable JSON.
--verbose              Include informational and related record IDs.
--itemid=<id>          Restrict attributable checks to one positive item ID.
--sample-limit=<count> Bound samples per finding (1..1000, default 20).

Exit codes: 0 clean, 1 warnings only, 2 errors, 3 command/runtime failure.

Example:
  php blocks/exaport/cli/audit_item_content.php --json
HELP;

[$options, $unrecognised] = cli_get_params([
    'help' => false, 'json' => false, 'verbose' => false, 'itemid' => null, 'sample-limit' => 20,
], ['h' => 'help']);

if ($options['help']) {
    cli_writeln($help);
    exit(0);
}
if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognised), 3);
}

try {
    $itemid = $options['itemid'] === null ? null : clean_param($options['itemid'], PARAM_INT);
    $samplelimit = clean_param($options['sample-limit'], PARAM_INT);
    // Reject values which PARAM_INT would otherwise partially normalise.
    if (($itemid !== null && (string)$itemid !== (string)$options['itemid']) ||
            (string)$samplelimit !== (string)$options['sample-limit']) {
        throw new invalid_parameter_exception('Numeric options must contain integers only');
    }
    $result = (new item_content_audit())->run($itemid, $samplelimit);
    if ($options['json']) {
        $plugin = get_config('block_exaport');
        cli_writeln(json_encode(item_content_audit_output::with_metadata($result, (string)$plugin->version),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    } else {
        cli_writeln(item_content_audit_output::human($result, (bool)$options['verbose']), false);
    }
    exit(item_content_audit_output::exit_code($result));
} catch (Throwable $exception) {
    cli_error('Audit failed: ' . $exception->getMessage(), 3);
}
