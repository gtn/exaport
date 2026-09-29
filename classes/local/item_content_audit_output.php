<?php
// This file is part of Moodle - http://moodle.org/

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** CLI-independent output and exit-code helpers for the item content audit. */
final class item_content_audit_output {
    /** Add execution metadata suitable for JSON automation. */
    public static function with_metadata(array $result, string $pluginversion, ?int $generatedat = null): array {
        return ['auditformatversion' => item_content_audit::FORMAT_VERSION, 'pluginversion' => $pluginversion,
            'generatedat' => gmdate('c', $generatedat ?? time())] + $result;
    }

    /** Format privacy-safe human-readable output. */
    public static function human(array $result, bool $verbose = false): string {
        $lines = ['Exaport structured item-content audit', '', 'Status: ' . strtoupper($result['status']),
            'Errors: ' . $result['errorcount'], 'Warnings: ' . $result['warningcount']];
        if (!empty($result['filter']['itemid'])) {
            $lines[] = 'Item filter: ' . $result['filter']['itemid'];
        }
        foreach ($result['findings'] as $finding) {
            $lines[] = '';
            $lines[] = '[' . strtoupper($finding['severity']) . '] ' . $finding['code'];
            $lines[] = '  Count: ' . $finding['count'];
            if (isset($finding['affecteditemcount'])) {
                $lines[] = '  Affected items: ' . $finding['affecteditemcount'];
            }
            $label = ucfirst(str_replace('ids', ' IDs', $finding['samplekey']));
            $lines[] = '  Sample ' . $label . ': ' . (implode(', ', $finding['sampleids']) ?: '(none)');
            $lines[] = '  Detail: ' . $finding['description'];
            $lines[] = '  Action: ' . $finding['action'];
            if ($verbose && !empty($finding['secondarysampleids'])) {
                $lines[] = '  Related record IDs: ' . implode(', ', $finding['secondarysampleids']);
            }
        }
        if (empty($result['findings'])) {
            $lines[] = '';
            $lines[] = 'No warning or error findings.';
        }
        if ($verbose) {
            $lines[] = '';
            $lines[] = 'Informational counts:';
            foreach ($result['counts'] as $name => $count) {
                $lines[] = '  ' . $name . ': ' . $count;
            }
        }
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /** Map audit status to the documented process exit status. */
    public static function exit_code(array $result): int {
        return $result['status'] === 'error' ? 2 : ($result['status'] === 'warning' ? 1 : 0);
    }
}
