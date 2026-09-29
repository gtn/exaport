<?php
// This file is part of Moodle - http://moodle.org/

namespace block_exaport\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only integrity audit for legacy and structured item content.
 *
 * This class deliberately uses only Moodle DML read methods. In particular it
 * does not use the File API or context APIs, both of which have creation paths.
 *
 * @package block_exaport
 */
final class item_content_audit {
    /** Audit result format version. */
    public const FORMAT_VERSION = 1;

    /** Maximum number of diagnostic identifiers returned per finding. */
    public const MAX_SAMPLE_LIMIT = 1000;

    /** @var \moodle_database */
    private $db;

    /** @var int */
    private $samplelimit;

    /** @var int|null */
    private $itemid;

    /** @var array */
    private $findings = [];

    /**
     * @param \moodle_database|null $db Database connection, primarily injectable for tests.
     */
    public function __construct(?\moodle_database $db = null) {
        global $DB;
        $this->db = $db ?? $DB;
    }

    /**
     * Inspect item content without changing any data.
     *
     * When an item ID is supplied, site-wide orphan checks are intentionally
     * omitted because those records cannot be attributed to that parent item.
     *
     * @param int|null $itemid Optional parent item ID.
     * @param int $samplelimit Maximum sample identifiers (1..1000).
     * @return array Stable, machine-readable audit result.
     */
    public function run(?int $itemid = null, int $samplelimit = 20): array {
        if ($itemid !== null && $itemid < 1) {
            throw new \invalid_parameter_exception('Item ID must be a positive integer');
        }
        if ($samplelimit < 1 || $samplelimit > self::MAX_SAMPLE_LIMIT) {
            throw new \invalid_parameter_exception('Sample limit must be between 1 and ' . self::MAX_SAMPLE_LIMIT);
        }

        $this->itemid = $itemid;
        $this->samplelimit = $samplelimit;
        $this->findings = [];

        $this->audit_legacy_columns();
        $this->audit_blocks();
        $this->audit_file_area('item_file');
        $this->audit_file_area('item_content_file');
        $this->audit_file_area('item_content_text');
        $this->audit_missing_contexts();

        $errors = 0;
        $warnings = 0;
        foreach ($this->findings as $finding) {
            if ($finding['severity'] === 'error') {
                $errors++;
            } else if ($finding['severity'] === 'warning') {
                $warnings++;
            }
        }

        $counts = $this->information_counts();
        foreach ($this->findings as $finding) {
            $counts[$finding['code']] = ($counts[$finding['code']] ?? 0) + $finding['count'];
        }

        return [
            'formatversion' => self::FORMAT_VERSION,
            'status' => $errors ? 'error' : ($warnings ? 'warning' : 'clean'),
            'errorcount' => $errors,
            'warningcount' => $warnings,
            'counts' => $counts,
            'findings' => $this->findings,
            'filter' => ['itemid' => $itemid, 'samplelimit' => $samplelimit],
        ];
    }

    /** Audit URL and attachment columns. */
    private function audit_legacy_columns(): void {
        [$where, $params] = $this->item_condition('i');
        $this->add_query_finding('legacy_url', 'error',
            "FROM {block_exaportitem} i WHERE $where AND TRIM(i.url) <> '' AND TRIM(i.url) <> :falsevalue",
            $params + ['falsevalue' => 'false'], 'i.id', 'itemids',
            'Meaningful legacy item URLs remain.',
            'Do not remove compatibility fallbacks. Back up the site and investigate the 2026092900 migration.');
        $this->add_query_finding('legacy_url_sentinel', 'warning',
            "FROM {block_exaportitem} i WHERE $where AND i.url <> '' AND (TRIM(i.url) = '' OR TRIM(i.url) = :falsevalue)",
            $params + ['falsevalue' => 'false'], 'i.id', 'itemids',
            'Historical sentinel or whitespace-only legacy URLs remain.',
            'Inspect the migration history; these values contain no link content and should not be repaired by this audit.');
        $this->add_query_finding('legacy_attachment', 'warning',
            "FROM {block_exaportitem} i WHERE $where AND i.attachment <> ''", $params, 'i.id', 'itemids',
            'Stale legacy attachment metadata remains.',
            'Do not treat this value as a File API ID; inspect it together with the item file areas.');
    }

    /** Audit content block rows. */
    private function audit_blocks(): void {
        [$where, $params] = $this->item_condition('i');
        if ($this->itemid === null) {
            $this->add_query_finding('orphan_content_block', 'error',
                'FROM {block_exaportitemblock} b LEFT JOIN {block_exaportitem} i ON i.id = b.itemid WHERE i.id IS NULL',
                [], 'b.id', 'blockids', 'Structured blocks without a parent item exist.',
                'Preserve a database backup and determine the intended parent before any manual repair.');
        }
        $base = "FROM {block_exaportitemblock} b JOIN {block_exaportitem} i ON i.id = b.itemid WHERE $where";
        $this->add_query_finding('unsupported_block_type', 'warning',
            "$base AND b.type NOT IN (:texttype, :linktype, :filetype)",
            $params + ['texttype' => 'text', 'linktype' => 'link', 'filetype' => 'file'], 'b.id', 'blockids',
            'Blocks use a type not understood by this plugin version.',
            'Preserve the records and check whether they were created by a newer compatible version.');
        $this->add_query_finding('empty_link_block', 'warning', "$base AND b.type = :linktype AND TRIM(b.url) = ''",
            $params + ['linktype' => 'link'], 'b.id', 'blockids', 'Link blocks have an empty URL.',
            'Inspect whether a usable link was intended; do not populate it from the legacy parent URL automatically.');
        $fileparams = $params + ['filetype' => 'file', 'component' => 'block_exaport',
            'filearea' => 'item_content_file', 'directory' => '.'];
        $this->add_query_finding('empty_file_block', 'warning', "$base AND b.type = :filetype AND NOT EXISTS (
                SELECT 1 FROM {files} f WHERE f.component = :component AND f.filearea = :filearea
                  AND f.itemid = b.id AND f.filename <> :directory)", $fileparams, 'b.id', 'blockids',
            'File blocks contain no non-directory files.',
            'Inspect the item in the content editor; this may be an intentionally unfinished block.');
        $this->add_query_finding('unexpected_block_fields', 'warning', "$base AND (
                (b.type = :linktype AND b.content IS NOT NULL AND b.content <> '') OR
                (b.type = :filetype AND ((b.content IS NOT NULL AND b.content <> '') OR (b.url IS NOT NULL AND b.url <> ''))) OR
                (b.type = :texttype AND b.url IS NOT NULL AND b.url <> ''))",
            $params + ['linktype' => 'link', 'filetype' => 'file', 'texttype' => 'text'], 'b.id', 'blockids',
            'Supported blocks contain fields unexpected for their type.',
            'Review the block without discarding fields; historical data may still be intentional.');
    }

    /**
     * Audit one plugin file area. Directory placeholders are never content.
     *
     * @param string $filearea File area.
     */
    private function audit_file_area(string $filearea): void {
        $params = ['component' => 'block_exaport', 'filearea' => $filearea, 'directory' => '.'];
        $nonfile = 'f.component = :component AND f.filearea = :filearea AND f.filename <> :directory';

        if ($filearea === 'item_file') {
            if ($this->itemid === null) {
                $this->add_query_finding('orphan_legacy_file', 'error',
                    "FROM {files} f LEFT JOIN {block_exaportitem} i ON i.id = f.itemid WHERE $nonfile AND i.id IS NULL",
                    $params, 'f.id', 'fileids', 'Legacy files without a parent item exist.',
                    'Preserve Moodledata and database backups and identify ownership before any manual repair.', 'f.itemid');
            }
            $filter = $this->itemid === null ? '1 = 1' : 'i.id = :filteritemid';
            if ($this->itemid !== null) {
                $params['filteritemid'] = $this->itemid;
            }
            $base = "FROM {files} f JOIN {block_exaportitem} i ON i.id = f.itemid WHERE $nonfile AND $filter";
            $this->add_query_finding('legacy_item_file', 'error', $base, $params, 'i.id', 'itemids',
                'Non-directory files remain in the legacy item file area.',
                'Do not remove compatibility fallbacks. Back up the site and investigate the 2026092900 migration.', 'f.id');
            $lastindex = count($this->findings) - 1;
            if ($lastindex >= 0 && $this->findings[$lastindex]['code'] === 'legacy_item_file') {
                $this->findings[$lastindex]['affecteditemcount'] = (int)$this->db->count_records_sql(
                    "SELECT COUNT(DISTINCT i.id) $base", $params);
            }
            $this->add_query_finding('legacy_file_context_mismatch', 'error', "$base AND NOT EXISTS (
                    SELECT 1 FROM {context} c WHERE c.contextlevel = :contextlevel
                      AND c.instanceid = i.userid AND c.id = f.contextid)",
                $params + ['contextlevel' => CONTEXT_USER], 'i.id', 'itemids',
                'Legacy files are not stored in their owner user context.',
                'Preserve the file and investigate its owner and context before any repair.', 'f.id');
            return;
        }

        $expectedtype = $filearea === 'item_content_file' ? 'file' : 'text';
        $orphancode = $filearea === 'item_content_file' ? 'orphan_structured_file' : 'orphan_text_file';
        $wrongcode = $filearea === 'item_content_file' ? 'structured_file_wrong_block_type' : 'text_file_wrong_block_type';
        $contextcode = $filearea === 'item_content_file' ? 'structured_file_context_mismatch' : 'text_file_context_mismatch';
        if ($this->itemid === null) {
            $this->add_query_finding($orphancode, 'error',
                "FROM {files} f LEFT JOIN {block_exaportitemblock} b ON b.id = f.itemid WHERE $nonfile AND b.id IS NULL",
                $params, 'f.id', 'fileids', 'Structured files without a content block exist.',
                'Preserve Moodledata and database backups; determine whether each file belongs to a deleted block.');
            $this->add_query_finding('structured_owner_unresolved', 'error',
                "FROM {files} f JOIN {block_exaportitemblock} b ON b.id = f.itemid
                   LEFT JOIN {block_exaportitem} i ON i.id = b.itemid
                  WHERE $nonfile AND i.id IS NULL", $params, 'f.id', 'fileids',
                'A structured file block cannot be tied to a parent item and owner.',
                'Preserve the block and file and establish their provenance before any manual repair.', 'b.id');
        }
        $filter = $this->itemid === null ? '1 = 1' : 'i.id = :filteritemid';
        if ($this->itemid !== null) {
            $params['filteritemid'] = $this->itemid;
        }
        $base = "FROM {files} f JOIN {block_exaportitemblock} b ON b.id = f.itemid
                   JOIN {block_exaportitem} i ON i.id = b.itemid WHERE $nonfile AND $filter";
        $this->add_query_finding($wrongcode, 'error', "$base AND b.type <> :expectedtype",
            $params + ['expectedtype' => $expectedtype], 'f.id', 'fileids',
            'Structured files are attached to an incompatible block type.',
            'Preserve the records and investigate the block/file relationship before any repair.', 'b.id');
        $this->add_query_finding($contextcode, 'error', "$base AND NOT EXISTS (
                SELECT 1 FROM {context} c WHERE c.contextlevel = :contextlevel
                  AND c.instanceid = i.userid AND c.id = f.contextid)",
            $params + ['contextlevel' => CONTEXT_USER], 'f.id', 'fileids',
            'Structured files are not stored in their parent item owner context.',
            'Preserve the file and investigate parent ownership and context before moving anything.', 'b.id');
    }

    /** Audit absent user contexts, without calling context_user::instance(). */
    private function audit_missing_contexts(): void {
        [$where, $params] = $this->item_condition('i');
        $base = "FROM {block_exaportitem} i LEFT JOIN {context} c
                   ON c.contextlevel = :contextlevel AND c.instanceid = i.userid
                 WHERE $where AND c.id IS NULL";
        $params['contextlevel'] = CONTEXT_USER;
        $hasfiles = "EXISTS (SELECT 1 FROM {files} lf WHERE lf.component = :component
                AND lf.filearea = :legacyarea AND lf.itemid = i.id AND lf.filename <> :directory)
            OR EXISTS (SELECT 1 FROM {block_exaportitemblock} b JOIN {files} sf ON sf.itemid = b.id
                WHERE b.itemid = i.id AND sf.component = :component2
                  AND (sf.filearea = :structuredarea OR sf.filearea = :textarea) AND sf.filename <> :directory2)";
        $fileparams = $params + ['component' => 'block_exaport', 'legacyarea' => 'item_file', 'directory' => '.',
            'component2' => 'block_exaport', 'structuredarea' => 'item_content_file',
            'textarea' => 'item_content_text', 'directory2' => '.'];
        $description = 'Items refer to an owner whose user context is absent.';
        $this->add_query_finding('missing_owner_context', 'error', "$base AND ($hasfiles)", $fileparams,
            'i.id', 'itemids', $description, 'Files may be inaccessible. Back up the site and investigate the owner context.');
        $this->add_query_finding('missing_owner_context', 'warning', "$base AND NOT ($hasfiles)", $fileparams,
            'i.id', 'itemids', $description,
            'Database-only content is retained, but ownership should be investigated before cleanup.');
    }

    /** Return informational population counts. */
    private function information_counts(): array {
        [$where, $params] = $this->item_condition('i');
        $counts = [];
        $counts['total_items'] = (int)$this->db->count_records_sql(
            "SELECT COUNT(1) FROM {block_exaportitem} i WHERE $where", $params);
        $base = "FROM {block_exaportitemblock} b JOIN {block_exaportitem} i ON i.id = b.itemid WHERE $where";
        $counts['total_structured_blocks'] = (int)$this->db->count_records_sql("SELECT COUNT(1) $base", $params);
        foreach (['text', 'link', 'file'] as $type) {
            $counts['total_' . $type . '_blocks'] = (int)$this->db->count_records_sql(
                "SELECT COUNT(1) $base AND b.type = :infotype", $params + ['infotype' => $type]);
        }
        $filefilter = $this->itemid === null ? '' : ' AND i.id = :fileitemid';
        $fileparams = ['component' => 'block_exaport', 'directory' => '.'];
        if ($this->itemid !== null) {
            $fileparams['fileitemid'] = $this->itemid;
        }
        $counts['total_structured_files'] = (int)$this->db->count_records_sql("SELECT COUNT(1)
              FROM {files} f JOIN {block_exaportitemblock} b ON b.id = f.itemid
              JOIN {block_exaportitem} i ON i.id = b.itemid
             WHERE f.component = :component AND f.filename <> :directory
               AND (f.filearea = 'item_content_file' OR f.filearea = 'item_content_text')$filefilter", $fileparams);
        return $counts;
    }

    /**
     * Add a finding using a complete FROM/WHERE SQL fragment.
     */
    private function add_query_finding(string $code, string $severity, string $fragment, array $params,
            string $samplefield, string $samplekey, string $description, string $action,
            ?string $secondaryfield = null): void {
        $count = (int)$this->db->count_records_sql("SELECT COUNT(1) $fragment", $params);
        if (!$count) {
            return;
        }
        $select = "$samplefield AS sampleid";
        if ($secondaryfield !== null) {
            $select .= ", $secondaryfield AS secondaryid";
        }
        $records = $this->db->get_records_sql("SELECT $select $fragment ORDER BY $samplefield ASC",
            $params, 0, $this->samplelimit);
        $samples = [];
        $secondary = [];
        foreach ($records as $record) {
            $samples[(int)$record->sampleid] = (int)$record->sampleid;
            if ($secondaryfield !== null) {
                $secondary[(int)$record->secondaryid] = (int)$record->secondaryid;
            }
        }
        $finding = ['code' => $code, 'severity' => $severity, 'count' => $count,
            'sampleids' => array_values($samples), 'samplekey' => $samplekey,
            'description' => $description, 'action' => $action];
        if ($secondaryfield !== null) {
            $finding['secondarysampleids'] = array_values($secondary);
        }
        $this->findings[] = $finding;
    }

    /** Return a parent-item filter with collision-safe named parameters. */
    private function item_condition(string $alias): array {
        if ($this->itemid === null) {
            return ['1 = 1', []];
        }
        return ["$alias.id = :audititemid", ['audititemid' => $this->itemid]];
    }
}
