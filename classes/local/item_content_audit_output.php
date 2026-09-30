<?php
// This file is part of Moodle - http://moodle.org/

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** Privacy-safe web and JSON output helpers for the item-content audit. */
final class item_content_audit_output {
    /** Add execution metadata suitable for JSON automation. */
    public static function with_metadata(array $result, string $pluginversion, ?int $generatedat = null): array {
        return ['auditformatversion' => item_content_audit::FORMAT_VERSION, 'pluginversion' => $pluginversion,
            'generatedat' => gmdate('c', $generatedat ?? time())] + $result;
    }

    /** Render a completed historical migration report containing aggregate counts only. */
    public static function migration_report_html(?\stdClass $record): string {
        if (!$record) {
            return html_writer::tag('p', get_string('audititemcontentnoreport', 'block_exaport'));
        }
        $summary = json_decode($record->summaryjson, true);
        if (!is_array($summary)) {
            return html_writer::tag('p', get_string('audititemcontentinvalidreport', 'block_exaport'));
        }

        $html = html_writer::tag('p', get_string('audititemcontentreportmeta', 'block_exaport', (object)[
            'version' => (int)$record->migrationversion,
            'completed' => userdate((int)$record->timecompleted),
        ]));
        foreach ($summary as $section => $counts) {
            if (!is_array($counts)) {
                continue;
            }
            $table = new \html_table();
            $table->attributes['class'] = 'generaltable';
            $table->head = [get_string('audititemcontentcode', 'block_exaport'),
                get_string('audititemcontentcount', 'block_exaport')];
            foreach ($counts as $code => $count) {
                $table->data[] = [html_writer::tag('code', s($code)), (int)$count];
            }
            $html .= html_writer::tag('h4', s(str_replace('_', ' ', ucfirst($section))));
            $html .= html_writer::table($table);
        }
        return $html;
    }

    /** Format an accessible, privacy-safe report for a Moodle administration page. */
    public static function html(array $result, bool $verbose = false): string {
        global $OUTPUT;

        $status = strtoupper($result['status']);
        $notificationtype = $result['status'] === 'error' ? 'error' :
            ($result['status'] === 'warning' ? 'warning' : 'success');
        $summary = get_string('audititemcontentsummary', 'block_exaport', (object)[
            'status' => $status,
            'errors' => $result['errorcount'],
            'warnings' => $result['warningcount'],
        ]);
        $html = $OUTPUT->notification($summary, $notificationtype, false);

        if (empty($result['findings'])) {
            return $html . html_writer::tag('p', get_string('audititemcontentnofindings', 'block_exaport'));
        }

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [get_string('audititemcontentseverity', 'block_exaport'),
            get_string('audititemcontentcode', 'block_exaport'), get_string('audititemcontentcount', 'block_exaport'),
            get_string('audititemcontentsamples', 'block_exaport'), get_string('audititemcontentguidance', 'block_exaport')];
        foreach ($result['findings'] as $finding) {
            $samples = implode(', ', array_map('strval', $finding['sampleids'])) ?: get_string('none');
            if ($verbose && !empty($finding['secondarysampleids'])) {
                $samples .= html_writer::empty_tag('br') . get_string('audititemcontentrelatedids', 'block_exaport') . ': ' .
                    implode(', ', array_map('strval', $finding['secondarysampleids']));
            }
            $count = (string)$finding['count'];
            if (isset($finding['affecteditemcount'])) {
                $count .= html_writer::empty_tag('br') . get_string('audititemcontentaffecteditems', 'block_exaport') .
                    ': ' . $finding['affecteditemcount'];
            }
            $guidance = s($finding['description']) . html_writer::empty_tag('br') .
                html_writer::tag('strong', get_string('audititemcontentaction', 'block_exaport') . ':') . ' ' .
                s($finding['action']);
            $table->data[] = [s(strtoupper($finding['severity'])), html_writer::tag('code', s($finding['code'])),
                $count, $samples, $guidance];
        }
        $html .= html_writer::table($table);

        if ($verbose) {
            $counttable = new \html_table();
            $counttable->attributes['class'] = 'generaltable';
            $counttable->head = [get_string('audititemcontentcode', 'block_exaport'),
                get_string('audititemcontentcount', 'block_exaport')];
            foreach ($result['counts'] as $code => $count) {
                $counttable->data[] = [html_writer::tag('code', s($code)), (int)$count];
            }
            $html .= html_writer::tag('h3', get_string('audititemcontentinformation', 'block_exaport'));
            $html .= html_writer::table($counttable);
        }
        return $html;
    }
}
