<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Normalize submitted competence ids.
 *
 * @param array $competenceids Submitted competence ids.
 * @return int[] Unique positive ids.
 */
function block_exaport_normalize_competenceids(array $competenceids): array {
    $competenceids = array_map('intval', $competenceids);
    return array_values(array_unique(array_filter($competenceids, function($competenceid) {
        return $competenceid > 0;
    })));
}

/**
 * Replace the learner's competence selection for an eportfolio item.
 *
 * @param stdClass $item Item whose competences are being replaced.
 * @param int[] $competenceids Selected competence ids.
 */
function block_exaport_sync_item_competences(stdClass $item, array $competenceids): void {
    global $DB, $USER;

    $course = $DB->get_record('course', ['id' => $item->courseid], 'id,shortname', MUST_EXIST);
    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
        ['activityid' => $item->id, 'eportfolioitem' => 1]);
    $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
        ['activityid' => $item->id, 'eportfolioitem' => 1, 'reviewerid' => $USER->id]);

    foreach ($competenceids as $competenceid) {
        $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, [
            'compid' => $competenceid,
            'activityid' => $item->id,
            'eportfolioitem' => 1,
            'activitytitle' => $item->name,
            'coursetitle' => $course->shortname,
        ]);
        $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM, [
            'compid' => $competenceid,
            'activityid' => $item->id,
            'eportfolioitem' => 1,
            'reviewerid' => $USER->id,
            'userid' => $USER->id,
            'role' => 0,
        ]);
    }

    $transaction->allow_commit();
}

/**
 * Keep denormalized Exacomp activity data in sync when item metadata changes.
 *
 * Competence selections are saved by the picker, but the item title is still saved by the main form.
 *
 * @param stdClass $item Updated portfolio item.
 */
function block_exaport_update_item_competence_metadata(stdClass $item): void {
    global $DB;

    $DB->set_field(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, 'activitytitle', $item->name, [
        'activityid' => $item->id,
        'eportfolioitem' => 1,
    ]);
}
