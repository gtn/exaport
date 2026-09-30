<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Migrate one item's legacy URL and files to structured content blocks.
 *
 * The legacy File API area, rather than the attachment column, is authoritative.
 * All changes for an item use a delegated transaction. This nests with an upgrade
 * transaction when one exists; it never issues a raw commit which could defeat it.
 * Once the transaction is durable, cleared source fields make a rerun a no-op.
 *
 * @param stdClass $item Trusted block_exaportitem record.
 * @param callable|null $progresscallback Optional test/diagnostic callback receiving the stage and related value.
 * @return array Migration operation counts and created block IDs.
 */
function block_exaport_migrate_legacy_item_content(stdClass $item, ?callable $progresscallback = null): array {
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
        // do not recreate the context merely to migrate a URL or clear stale attachment metadata.
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
            'attachmentcleared' => (string)($current->attachment ?? '') !== '' ? 1 : 0,
            'legacyfileareacleared' => $sourcefiles ? 1 : 0,
            'alreadyclean' => $storedurl === '' && (string)($current->attachment ?? '') === '' && !$sourcefiles ? 1 : 0,
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

            // Verify identity, placement, and bytes for each file; a count alone can hide collisions.
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
            }
        }

        $update = (object)['id' => $itemid];
        $needsupdate = false;
        if ($storedurl !== '') {
            // This clears meaningful URLs only after their block exists, plus whitespace/sentinel values.
            $update->url = '';
            $needsupdate = true;
        }
        if ((string)($current->attachment ?? '') !== '') {
            // Attachment is compatibility metadata, never a file ID or evidence that a file exists.
            $update->attachment = '';
            $needsupdate = true;
        }
        if ($needsupdate) {
            $DB->update_record('block_exaportitem', $update);
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
 * @return array Privacy-safe aggregate operation counts.
 */
function block_exaport_migrate_legacy_item_content_batches(
    int $batchsize = 500,
    ?callable $migrator = null
): array {
    global $DB;

    if ($batchsize < 1) {
        throw new coding_exception('Legacy item content migration batch size must be positive');
    }
    $migrator = $migrator ?? 'block_exaport_migrate_legacy_item_content';
    $counts = [
        'items_processed' => 0,
        'items_already_clean' => 0,
        'link_blocks_created' => 0,
        'file_blocks_created' => 0,
        'files_copied' => 0,
        'urls_cleared' => 0,
        'attachments_cleared' => 0,
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
        foreach ($items as $item) {
            $result = $migrator($item);
            // Injectable test migrators written before telemetry may return void.
            $result = is_array($result) ? $result : [];
            $counts['items_processed']++;
            $counts['items_already_clean'] += (int)($result['alreadyclean'] ?? 0);
            $counts['link_blocks_created'] += empty($result['linkblockid']) ? 0 : 1;
            $counts['file_blocks_created'] += empty($result['fileblockid']) ? 0 : 1;
            $counts['files_copied'] += (int)($result['filecount'] ?? 0);
            $counts['urls_cleared'] += (int)($result['urlcleared'] ?? 0);
            $counts['attachments_cleared'] += (int)($result['attachmentcleared'] ?? 0);
            $counts['legacy_file_areas_cleared'] += (int)($result['legacyfileareacleared'] ?? 0);
            $lastprocessedid = (int)$item->id;
        }
        if ($items) {
            // Aggregate progress only: never expose item, owner, URL, or file details.
            mtrace("Exaport item-content migration: {$counts['items_processed']} items processed");
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
    return [
        'total_items' => $DB->count_records('block_exaportitem'),
        'meaningful_legacy_urls' => (int)$DB->count_records_sql("SELECT COUNT(1)
              FROM {block_exaportitem}
             WHERE TRIM(url) <> '' AND TRIM(url) <> :falsevalue", ['falsevalue' => 'false']),
        'sentinel_legacy_urls' => (int)$DB->count_records_sql("SELECT COUNT(1)
              FROM {block_exaportitem}
             WHERE url <> '' AND (TRIM(url) = '' OR TRIM(url) = :falsevalue)", ['falsevalue' => 'false']),
        'legacy_attachments' => $DB->count_records_select('block_exaportitem', "attachment <> ''"),
        'legacy_files' => (int)$DB->count_records_select('files', $filewhere, $fileparams),
        'items_with_legacy_files' => (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT itemid) FROM {files} WHERE $filewhere", $fileparams),
    ];
}

/**
 * Run migration 2026092900 and persist its completed aggregate report.
 *
 * No report row is written if migration or post-migration counting fails.
 * Existing completed evidence is never overwritten on an upgrade retry.
 *
 * @param int $batchsize Maximum items fetched at once.
 * @param callable|null $migrator Optional migrator for tests.
 * @return stdClass Completed report record.
 */
function block_exaport_migrate_legacy_item_content_with_report(
    int $batchsize = 500,
    ?callable $migrator = null
): stdClass {
    global $DB;

    $migrationversion = 2026092900;
    $existing = $DB->get_record('block_exaportmigration', ['migrationversion' => $migrationversion]);
    if ($existing) {
        return $existing;
    }

    $timestarted = time();
    $before = block_exaport_legacy_item_content_counts();
    $operations = block_exaport_migrate_legacy_item_content_batches($batchsize, $migrator);
    $after = block_exaport_legacy_item_content_counts();
    $summary = [
        'source_counts_at_successful_run_start' => $before,
        'operations_in_successful_run' => $operations,
        'residual_counts_at_successful_run_end' => $after,
    ];
    $summaryjson = json_encode($summary, JSON_UNESCAPED_SLASHES);
    if ($summaryjson === false) {
        throw new coding_exception('Unable to encode the item-content migration report');
    }
    $record = (object)[
        'migrationversion' => $migrationversion,
        'formatversion' => 1,
        'timestarted' => $timestarted,
        'timecompleted' => time(),
        'summaryjson' => $summaryjson,
    ];
    $record->id = (int)$DB->insert_record('block_exaportmigration', $record);
    return $record;
}
