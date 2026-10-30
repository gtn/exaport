<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** Durable, administrator-only recovery history, separate from immutable upgrade summaries. */
final class migration_issues {
    public const VERSION = 2026101902;
    public const TABLE = 'block_exaportmigissue';

    /** Also called before the historical migration, including interrupted upgrades. */
    public static function ensure_schema(): void {
        global $DB;
        $table = new \xmldb_table(self::TABLE);
        if ($DB->get_manager()->table_exists($table)) {
            return;
        }
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        foreach (['migrationversion', 'itemid', 'ownerid', 'fileid', 'firstseen', 'lastseen', 'attempts',
                'timeresolved'] as $name) {
            $table->add_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        }
        $table->add_field('reason', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('identity', XMLDB_INDEX_UNIQUE, ['migrationversion', 'itemid', 'fileid', 'reason']);
        $table->add_index('pendingitem', XMLDB_INDEX_NOTUNIQUE, ['itemid', 'timeresolved']);
        $table->add_index('ownerid', XMLDB_INDEX_NOTUNIQUE, ['ownerid']);
        $DB->get_manager()->create_table($table);
    }

    /** Never claim durability while an enclosing transaction can undo the issue history. */
    public static function require_independent_commits(): void {
        global $DB;
        if ($DB->is_transaction_started()) {
            throw new \coding_exception('Legacy content recovery must run outside an enclosing transaction');
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
            $issue->timeresolved = 0;
            $DB->update_record(self::TABLE, $issue);
        } else {
            $DB->insert_record(self::TABLE, $identity + ['ownerid' => $item->userid,
                'firstseen' => $now, 'lastseen' => $now, 'attempts' => 1, 'timeresolved' => 0]);
        }
    }

    public static function pending(int $itemid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, [
            'migrationversion' => self::VERSION, 'itemid' => $itemid, 'timeresolved' => 0,
        ]);
    }

    public static function require_editable(int $itemid): void {
        if (self::pending($itemid)) {
            throw new \moodle_exception('migrationpending', 'block_exaport');
        }
    }

    /** Only after the content transaction has actually committed. History is never deleted here. */
    public static function resolve(int $itemid): void {
        global $DB;
        if (self::pending($itemid)) {
            self::require_independent_commits();
            $DB->set_field(self::TABLE, 'timeresolved', time(), [
                'migrationversion' => self::VERSION, 'itemid' => $itemid, 'timeresolved' => 0,
            ]);
        }
    }

    /** Aggregate history across every attempt; no item or file identifiers. */
    public static function counts(): array {
        global $DB;
        $params = ['migrationversion' => self::VERSION];
        return [
            'issues_recorded' => $DB->count_records(self::TABLE, $params),
            'issues_unresolved' => $DB->count_records(self::TABLE, $params + ['timeresolved' => 0]),
            'items_pending' => (int)$DB->count_records_sql('SELECT COUNT(DISTINCT itemid) FROM {' . self::TABLE . '}
                WHERE migrationversion = ? AND timeresolved = 0', [self::VERSION]),
        ];
    }
}
