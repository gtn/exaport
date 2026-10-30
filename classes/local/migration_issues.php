<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** Durable administrator evidence, separate from immutable upgrade summaries and current file health. */
final class migration_issues {
    public const VERSION = 2026101902;
    public const TABLE = 'block_exaportmigissue';

    /** Never claim durability while an enclosing transaction can undo the findings. */
    public static function require_independent_commits(): void {
        global $DB;
        if ($DB->is_transaction_started()) {
            throw new \coding_exception('Migration findings must be recorded outside an enclosing transaction');
        }
    }

    /** Called under the item's migration lock, before any content writes. */
    public static function record(\stdClass $item, \stored_file $file, string $reason): void {
        global $DB;
        self::require_independent_commits();
        $identity = ['migrationversion' => self::VERSION, 'itemid' => $item->id,
            'fileid' => $file->get_id(), 'reason' => $reason];
        $issue = $DB->get_record(self::TABLE, $identity);
        $now = time();
        if ($issue) {
            $issue->lastseen = $now;
            $issue->attempts++;
            $DB->update_record(self::TABLE, $issue);
        } else {
            $DB->insert_record(self::TABLE, $identity + ['ownerid' => $item->userid,
                'contenthash' => $file->get_contenthash(), 'firstseen' => $now, 'lastseen' => $now,
                'attempts' => 1, 'destinationid' => 0, 'timecopied' => 0]);
        }
    }

    /** Commit the exact destination ID atomically with its verified file record, even for missing bytes. */
    public static function link_destination(int $itemid, \stored_file $source, \stored_file $destination): void {
        global $DB;
        $DB->execute('UPDATE {' . self::TABLE . '} SET destinationid = ?, timecopied = ?
            WHERE migrationversion = ? AND itemid = ? AND fileid = ?', [
                $destination->get_id(), time(), self::VERSION, $itemid, $source->get_id(),
            ]);
    }

    /** Historical counts across every attempt; these do not assert current file availability. */
    public static function counts(): array {
        global $DB;
        $params = ['migrationversion' => self::VERSION];
        return [
            'issues_recorded' => $DB->count_records(self::TABLE, $params),
            'files_affected' => (int)$DB->count_records_sql('SELECT COUNT(DISTINCT fileid) FROM {' . self::TABLE . '}
                WHERE migrationversion = ?', [self::VERSION]),
            'items_affected' => (int)$DB->count_records_sql('SELECT COUNT(DISTINCT itemid) FROM {' . self::TABLE . '}
                WHERE migrationversion = ?', [self::VERSION]),
        ];
    }
}
