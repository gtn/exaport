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
 * @return array{linkblockid: int|null, fileblockid: int|null, filecount: int}
 */
function block_exaport_migrate_legacy_item_content(stdClass $item): array {
    global $DB;

    $itemid = (int)($item->id ?? 0);
    $ownerid = (int)($item->userid ?? 0);
    if (!$itemid || !$ownerid) {
        throw new coding_exception('Legacy item content migration requires item and owner IDs');
    }

    try {
        $context = context_user::instance($ownerid, MUST_EXIST);
    } catch (Throwable $exception) {
        throw new coding_exception(
            "Cannot migrate legacy content for item {$itemid}: owner {$ownerid} has no user context",
            $exception->getMessage()
        );
    }

    $transaction = $DB->start_delegated_transaction();
    try {
        // Re-read inside the transaction so a stale batch record cannot recreate already migrated content.
        $current = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $fs = get_file_storage();
        $sourcefiles = array_values($fs->get_area_files(
            $context->id,
            'block_exaport',
            'item_file',
            $itemid,
            'filepath ASC, filename ASC, id ASC',
            false
        ));

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
        $result = ['linkblockid' => null, 'fileblockid' => null, 'filecount' => count($sourcefiles)];

        if ($hasurl) {
            $link = (object)array_merge($common, [
                'itemid' => $itemid,
                'type' => 'link',
                'sortorder' => $nextsortorder++,
                'url' => $storedurl,
            ]);
            $result['linkblockid'] = (int)$DB->insert_record('block_exaportitemblock', $link);
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
            }

            // Verify identity, placement, and bytes for each file; a count alone can hide collisions.
            foreach ($sourcefiles as $sourcefile) {
                $destination = $fs->get_file(
                    $context->id,
                    'block_exaport',
                    'item_content_file',
                    $fileblock->id,
                    $sourcefile->get_filepath(),
                    $sourcefile->get_filename()
                );
                if (!$destination || $destination->is_directory() ||
                        $destination->get_contextid() !== $context->id ||
                        $destination->get_component() !== 'block_exaport' ||
                        $destination->get_filearea() !== 'item_content_file' ||
                        (int)$destination->get_itemid() !== $fileblock->id ||
                        (int)$destination->get_userid() !== $ownerid ||
                        $destination->get_contenthash() !== $sourcefile->get_contenthash() ||
                        $destination->get_filesize() !== $sourcefile->get_filesize()) {
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
        $fs->delete_area_files($context->id, 'block_exaport', 'item_file', $itemid);

        $transaction->allow_commit();
        return $result;
    } catch (Throwable $exception) {
        $transaction->rollback(new coding_exception(
            "Legacy content migration failed for item {$itemid} (owner {$ownerid})",
            $exception->getMessage()
        ));
    }
}
