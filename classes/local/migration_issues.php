<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/** Durable administrator evidence, separate from immutable upgrade summaries and current file health. */
final class migration_issues {
    public const VERSION = 2026101902;
    public const TABLE = 'block_exaportmigissue';

    /** Available before the historical migration, including interrupted upgrades. */
    public static function ensure_schema(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(self::TABLE);
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            foreach (['migrationversion', 'itemid', 'ownerid', 'fileid', 'firstseen', 'lastseen', 'attempts',
                    'timecopied', 'destinationid'] as $name) {
                $table->add_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            }
            $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('reason', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('identity', XMLDB_INDEX_UNIQUE, ['migrationversion', 'itemid', 'fileid', 'reason']);
            $table->add_index('ownerid', XMLDB_INDEX_NOTUNIQUE, ['ownerid']);
            $dbman->create_table($table);
            return;
        }
        // Preserve any history from the earlier development build of this PR.
        $oldfield = new \xmldb_field('timeresolved', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if ($dbman->field_exists($table, $oldfield)) {
            $index = new \xmldb_index('pendingitem', XMLDB_INDEX_NOTUNIQUE, ['itemid', 'timeresolved']);
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }
            $dbman->rename_field($table, $oldfield, 'timecopied');
        }
        foreach ([new \xmldb_field('destinationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
                new \xmldb_field('contenthash', XMLDB_TYPE_CHAR, '40', null, null, null, null)] as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
    }

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
            if (empty($issue->contenthash)) {
                $issue->contenthash = $file->get_contenthash();
            }
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
        $DB->execute('UPDATE {' . self::TABLE . '} SET destinationid = ?, timecopied = ?, contenthash = COALESCE(contenthash, ?)
            WHERE migrationversion = ? AND itemid = ? AND fileid = ?', [
                $destination->get_id(), time(), $source->get_contenthash(), self::VERSION, $itemid, $source->get_id(),
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
