<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Inspect source bytes once during upgrade; known damage is recorded before metadata migration.
 *
 * File API copies can share backing storage, so matching record metadata alone
 * does not establish that either file is readable or has its expected content.
 *
 * @param stored_file $file Legacy source file.
 * @return array Actual byte count and independently calculated content digest.
 */
function block_exaport_verify_migration_file_content(stored_file $file): array {
    // File systems may warn and return false rather than throw on I/O failures.
    $handle = @$file->get_content_file_handle();
    if (!is_resource($handle)) {
        throw new \block_exaport\local\migration_source_exception('source_unreadable');
    }

    try {
        $digest = hash_init('sha1');
        $bytes = 0;
        while (!feof($handle)) {
            $chunk = @fread($handle, 1048576);
            if ($chunk === false || ($chunk === '' && !feof($handle))) {
                throw new \block_exaport\local\migration_source_exception('source_read_failed');
            }
            $bytes += strlen($chunk);
            hash_update($digest, $chunk);
        }
        $contenthash = hash_final($digest);
        if ($bytes !== (int)$file->get_filesize() || $contenthash !== $file->get_contenthash()) {
            throw new \block_exaport\local\migration_source_exception('source_content_mismatch');
        }
        return ['bytes' => $bytes, 'contenthash' => $contenthash];
    } finally {
        fclose($handle);
    }
}

/**
 * Inspect sources and record known byte failures durably before moving any file records.
 * Explicit source damage does not block metadata migration. Unexpected errors remain fatal.
 * A lock covers inspection and metadata migration. File references retain their content hashes.
 *
 * @param stdClass $item Trusted item record.
 * @param callable|null $progresscallback Optional diagnostic/test stage callback.
 * @return array Operation counts, including files with recorded content issues.
 */
function block_exaport_migrate_legacy_item_content(stdClass $item, ?callable $progresscallback = null): array {
    global $DB, $CFG;
    if (empty($item->id) || empty($item->userid)) {
        throw new coding_exception('Legacy item content migration requires item and owner IDs');
    }
    $factory = \core\lock\lock_config::get_lock_factory('block_exaport_migration');
    $lock = $factory->get_lock('item_' . (int)$item->id, 10);
    if (!$lock) {
        throw new coding_exception('Another process is migrating this item');
    }
    try {
        $item = $DB->get_record('block_exaportitem', ['id' => $item->id], '*', MUST_EXIST);
        $contextid = $DB->get_field('context', 'id', ['contextlevel' => CONTEXT_USER, 'instanceid' => $item->userid]);
        if ($contextid && !$DB->record_exists('user', ['id' => $item->userid])) {
            throw new coding_exception('Legacy migration context owner is missing');
        }
        $fs = get_file_storage();
        $files = $contextid ? $fs->get_area_files($contextid, 'block_exaport', 'item_file',
            $item->id, 'filepath ASC, filename ASC, id ASC', false) : [];
        // A missing/unreadable local storage root is an outage, not individual damaged content.
        // Remote backends must surface outages as exceptions; these are deliberately not caught.
        if ($files && get_class($fs->get_file_system()) === file_system_filedir::class) {
            $filedir = $CFG->filedir ?? $CFG->dataroot . '/filedir';
            if (!is_dir($filedir) || !is_readable($filedir)) {
                throw new coding_exception('Legacy migration file storage is unavailable');
            }
        }
        $fileissues = 0;
        foreach ($files as $file) {
            try {
                block_exaport_verify_migration_file_content($file);
            } catch (\block_exaport\local\migration_source_exception $exception) {
                \block_exaport\local\migration_issues::record($item, $file, $exception->reason);
                $fileissues++;
            }
        }
        $result = block_exaport_migrate_legacy_item_records($item, $progresscallback);
        $result['fileissues'] = $fileissues;
        if ($progresscallback) {
            $progresscallback('content_committed', $result);
        }
        return $result;
    } finally {
        $lock->release();
    }
}

/**
 * Migrate one item's URL and file records to structured content without altering backing bytes.
 *
 * @internal Called after the public migrator has inspected and documented the sources.
 *
 * The legacy File API area, rather than the attachment column, is authoritative.
 * Historical attachment metadata is preserved verbatim for later investigation.
 * All changes for an item use a delegated transaction. This nests with an upgrade
 * transaction when one exists; it never issues a raw commit which could defeat it.
 * Once the transaction is durable, cleared source fields make a rerun a no-op.
 *
 * @param stdClass $item Trusted block_exaportitem record.
 * @param callable|null $progresscallback Optional test/diagnostic callback receiving the stage and related value.
 * @return array Migration operation counts and created block IDs.
 */
function block_exaport_migrate_legacy_item_records(stdClass $item, ?callable $progresscallback = null): array {
    global $DB;

    $itemid = (int)($item->id ?? 0);
    $ownerid = (int)($item->userid ?? 0);
    if (!$itemid || !$ownerid) {
        throw new coding_exception('Legacy item content migration requires item and owner IDs');
    }

    // Do not use context_user::instance(): it recreates a missing context for an active user. A
    // soft-deleted user may legitimately retain a context and files, which are still safe to migrate.
    $contextrecord = $DB->get_record('context', [
        'contextlevel' => CONTEXT_USER,
        'instanceid' => $ownerid,
    ]);
    $context = null;
    if ($contextrecord) {
        if (!$DB->record_exists('user', ['id' => $ownerid])) {
            throw new coding_exception(
                "Cannot migrate legacy content for item {$itemid}: context owner {$ownerid} is missing"
            );
        }
        try {
            $context = context::instance_by_id((int)$contextrecord->id, MUST_EXIST);
        } catch (Throwable $exception) {
            throw new coding_exception(
                "Cannot migrate legacy content for item {$itemid}: owner {$ownerid} has an invalid user context",
                $exception->getMessage()
            );
        }
    }

    $transaction = $DB->start_delegated_transaction();
    try {
        // Re-read inside the transaction so a stale batch record cannot recreate already migrated content.
        $current = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $fs = get_file_storage();
        // File records are context-bound. If the context was deleted, Moodle deletes its file areas too;
        // do not recreate the context merely to migrate a URL.
        $sourcefiles = $context ? array_values($fs->get_area_files(
            $context->id,
            'block_exaport',
            'item_file',
            $itemid,
            'filepath ASC, filename ASC, id ASC',
            false
        )) : [];

        $storedurl = (string)($current->url ?? '');
        $testedurl = trim($storedurl);
        // Historically Exaport used the exact lower-case string "false" as the no-URL sentinel.
        $hasurl = $testedurl !== '' && $testedurl !== 'false';
        $maxsortorder = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {block_exaportitemblock} WHERE itemid = ?',
            [$itemid]
        );
        $nextsortorder = ($maxsortorder === false || $maxsortorder === null) ? 0 : (int)$maxsortorder + 1;

        $migrationtime = time();
        $timecreated = !empty($current->timecreated) ? (int)$current->timecreated :
            (!empty($current->timemodified) ? (int)$current->timemodified : $migrationtime);
        $timemodified = !empty($current->timemodified) ? (int)$current->timemodified : $timecreated;
        $common = [
            'title' => '',
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'timecreated' => $timecreated,
            'timemodified' => $timemodified,
        ];
        $result = [
            'linkblockid' => null,
            'fileblockid' => null,
            'filecount' => count($sourcefiles),
            'urlcleared' => $storedurl !== '' ? 1 : 0,
            'legacyfileareacleared' => $sourcefiles ? 1 : 0,
            'alreadyclean' => $storedurl === '' && !$sourcefiles ? 1 : 0,
        ];

        if ($hasurl) {
            $link = (object)array_merge($common, [
                'itemid' => $itemid,
                'type' => 'link',
                'sortorder' => $nextsortorder++,
                'url' => $storedurl,
            ]);
            $result['linkblockid'] = (int)$DB->insert_record('block_exaportitemblock', $link);
            if ($progresscallback) {
                $progresscallback('link_created', $result['linkblockid']);
            }
        }

        if ($sourcefiles) {
            $fileblock = (object)array_merge($common, [
                'itemid' => $itemid,
                'type' => 'file',
                'sortorder' => $nextsortorder,
                'url' => '',
            ]);
            $fileblock->id = (int)$DB->insert_record('block_exaportitemblock', $fileblock);
            $result['fileblockid'] = $fileblock->id;

            foreach ($sourcefiles as $sourcefile) {
                $fs->create_file_from_storedfile([
                    'contextid' => $context->id,
                    'component' => 'block_exaport',
                    'filearea' => 'item_content_file',
                    'itemid' => $fileblock->id,
                    'userid' => $ownerid,
                ], $sourcefile);
                if ($progresscallback) {
                    $progresscallback('file_copied', $sourcefile);
                }
            }

            // Verify every record's identity and placement. Source and destination share a content
            // hash: backing bytes are not copied or rewritten, even when already damaged.
            $hasissues = $DB->record_exists(\block_exaport\local\migration_issues::TABLE, [
                'migrationversion' => \block_exaport\local\migration_issues::VERSION, 'itemid' => $itemid,
            ]);
            if ($progresscallback) {
                $progresscallback('before_verification', $fileblock->id);
            }
            foreach ($sourcefiles as $sourcefile) {
                $destination = $fs->get_file(
                    $context->id,
                    'block_exaport',
                    'item_content_file',
                    $fileblock->id,
                    $sourcefile->get_filepath(),
                    $sourcefile->get_filename()
                );
                // Numeric DML fields may be returned as numeric strings, depending on the database driver.
                if (!$destination || $destination->is_directory() ||
                        (int)$destination->get_contextid() !== (int)$context->id ||
                        $destination->get_component() !== 'block_exaport' ||
                        $destination->get_filearea() !== 'item_content_file' ||
                        (int)$destination->get_itemid() !== $fileblock->id ||
                        (int)$destination->get_userid() !== $ownerid ||
                        $destination->get_contenthash() !== $sourcefile->get_contenthash() ||
                        (int)$destination->get_filesize() !== (int)$sourcefile->get_filesize()) {
                    throw new coding_exception("File verification failed while migrating item {$itemid}");
                }
                if ($hasissues) {
                    // The destination mapping commits atomically with the content records. On rollback,
                    // the independently recorded issue survives without a dangling destination ID.
                    \block_exaport\local\migration_issues::link_destination($itemid, $sourcefile, $destination);
                }
            }
        }

        if ($storedurl !== '') {
            // This clears meaningful URLs only after their block exists, plus whitespace/sentinel values.
            $DB->set_field('block_exaportitem', 'url', '', ['id' => $itemid]);
        }
        // Also removes harmless directory placeholders after the (possibly empty) verification set succeeds.
        if ($context) {
            $fs->delete_area_files($context->id, 'block_exaport', 'item_file', $itemid);
        }

        $transaction->allow_commit();
        return $result;
    } catch (Throwable $exception) {
        $transaction->rollback(new coding_exception(
            "Legacy content migration failed for item {$itemid} (owner {$ownerid})",
            $exception->getMessage()
        ));
    }
}

/**
 * Migrate all legacy items in bounded ascending-ID batches.
 *
 * The injectable migrator keeps interruption/restart behavior testable without
 * making the production upgrade depend on PHPUnit or request state.
 *
 * @param int $batchsize Maximum items fetched at once.
 * @param callable|null $migrator Optional item migrator, used by tests to simulate interruption.
 * @param callable|null $progresscallback Optional callback receiving cumulative count and aggregate counters.
 * @return array Privacy-safe aggregate operation counts, including content findings.
 */
function block_exaport_migrate_legacy_item_content_batches(
    int $batchsize = 500,
    ?callable $migrator = null,
    ?callable $progresscallback = null
): array {
    global $DB;

    if ($batchsize < 1) {
        throw new coding_exception('Legacy item content migration batch size must be positive');
    }
    $migrator = $migrator ?? 'block_exaport_migrate_legacy_item_content';
    $counts = [
        'items_processed' => 0,
        'items_already_clean' => 0,
        'files_with_content_issues' => 0,
        'link_blocks_created' => 0,
        'file_blocks_created' => 0,
        'files_copied' => 0,
        'urls_cleared' => 0,
        'legacy_file_areas_cleared' => 0,
    ];
    $lastprocessedid = 0;
    do {
        $items = $DB->get_records_select(
            'block_exaportitem',
            'id > ?',
            [$lastprocessedid],
            'id ASC',
            '*',
            0,
            $batchsize
        );
        if ($items) {
            // Renew immediately before this bounded batch; there is no whole-migration time ceiling.
            upgrade_set_timeout(1800);
        }
        foreach ($items as $item) {
            $result = $migrator($item);
            // Injectable test migrators written before telemetry may return void.
            $result = is_array($result) ? $result : [];
            $counts['items_processed']++;
            $counts['files_with_content_issues'] += (int)($result['fileissues'] ?? 0);
            $counts['items_already_clean'] += (int)($result['alreadyclean'] ?? 0);
            $counts['link_blocks_created'] += empty($result['linkblockid']) ? 0 : 1;
            $counts['file_blocks_created'] += empty($result['fileblockid']) ? 0 : 1;
            $counts['files_copied'] += (int)($result['filecount'] ?? 0);
            $counts['urls_cleared'] += (int)($result['urlcleared'] ?? 0);
            $counts['legacy_file_areas_cleared'] += (int)($result['legacyfileareacleared'] ?? 0);
            $lastprocessedid = (int)$item->id;
        }
        if ($items && $progresscallback) {
            $progresscallback($counts['items_processed'], $counts);
        }
    } while (count($items) === $batchsize);
    return $counts;
}

/**
 * Count legacy migration sources or residuals without reading sensitive values.
 *
 * @return array Aggregate counts only.
 */
function block_exaport_legacy_item_content_counts(): array {
    global $DB;

    $filewhere = "component = :component AND filearea = :filearea AND filename <> :directory";
    $fileparams = ['component' => 'block_exaport', 'filearea' => 'item_file', 'directory' => '.'];
    $urllength = $DB->sql_length('url');
    // Match migration's exact lower-case sentinel rule regardless of database collation.
    $issentinel = $DB->sql_equal('TRIM(url)', ':falsevalue', true);
    return [
        'total_items' => $DB->count_records('block_exaportitem'),
        'meaningful_legacy_urls' => (int)$DB->count_records_sql("SELECT COUNT(1)
              FROM {block_exaportitem}
             WHERE TRIM(url) <> '' AND NOT ($issentinel)", ['falsevalue' => 'false']),
        'sentinel_legacy_urls' => (int)$DB->count_records_sql("SELECT COUNT(1)
              FROM {block_exaportitem}
             WHERE $urllength > 0 AND (TRIM(url) = '' OR ($issentinel))", ['falsevalue' => 'false']),
        'legacy_files' => (int)$DB->count_records_select('files', $filewhere, $fileparams),
        'items_with_legacy_files' => (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT itemid) FROM {files} WHERE $filewhere", $fileparams),
    ];
}

/**
 * Migrate legacy item content and persist its completed aggregate report.
 * Content findings are independently committed and survive a failed run; the summary is immutable.
 *
 * The report identifier 2026101902 matches the content-migration savepoint.
 * It identifies this migration and does not track later plugin versions.
 *
 * No report row is written if migration or post-migration counting fails.
 * Existing completed evidence is never overwritten on an upgrade retry.
 *
 * @param int $batchsize Maximum items fetched at once.
 * @param callable|null $migrator Optional migrator for tests.
 * @param callable|null $progresscallback Optional aggregate batch progress callback.
 * @return stdClass Completed report record.
 */
function block_exaport_migrate_legacy_item_content_with_report(
    int $batchsize = 500,
    ?callable $migrator = null,
    ?callable $progresscallback = null
): stdClass {
    global $DB;

    // Identify the report by the content-migration savepoint, not the final plugin version.
    $migrationversion = 2026101902;
    $existing = $DB->get_record('block_exaportmigration', ['migrationversion' => $migrationversion]);
    if ($existing) {
        return $existing;
    }

    $timestarted = time();
    $before = block_exaport_legacy_item_content_counts();
    $operations = block_exaport_migrate_legacy_item_content_batches(
        $batchsize,
        $migrator,
        $progresscallback
    );
    $after = block_exaport_legacy_item_content_counts();
    // Total parent items are informational, not legacy residuals.
    $residuals = $after;
    unset($residuals['total_items']);
    $issuecounts = \block_exaport\local\migration_issues::counts();
    $clean = !array_filter($residuals) && !$issuecounts['issues_recorded'];
    $summary = [
        'status' => [
            'clean' => $clean,
            'requires_review' => !$clean,
        ],
        'source_counts_at_successful_run_start' => $before,
        'operations_in_successful_run' => $operations,
        'residual_counts_at_successful_run_end' => $after,
        'issue_history_at_successful_run_end' => $issuecounts,
    ];
    $summaryjson = json_encode($summary, JSON_UNESCAPED_SLASHES);
    if ($summaryjson === false) {
        throw new coding_exception('Unable to encode the item-content migration report');
    }
    $record = (object)[
        'migrationversion' => $migrationversion,
        'formatversion' => 3,
        'timestarted' => $timestarted,
        'timecompleted' => time(),
        'summaryjson' => $summaryjson,
    ];
    $record->id = (int)$DB->insert_record('block_exaportmigration', $record);
    return $record;
}
