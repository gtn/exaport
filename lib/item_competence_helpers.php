<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Dynamic forms are instantiated by Moodle's external API and do not pass
// through the plugin's inc.php bootstrap used by standalone pages.
require_once(__DIR__ . '/lib.php');

/**
 * Load an item that the current user may modify through the competence picker.
 *
 * @param int $itemid Item ID.
 * @param int $courseid Course ID.
 * @return stdClass
 */
function block_exaport_get_editable_competence_item(int $itemid, int $courseid): stdClass {
    return block_exaport_get_editable_item($itemid, $courseid);
}

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
 * Parse the comma-separated dynamic-form value without silently accepting malformed input.
 *
 * @param string $value Submitted hidden-field value.
 * @return int[] Unique positive ids.
 */
function block_exaport_parse_competenceids(string $value): array {
    if ($value === '') {
        return [];
    }
    if (!preg_match('/^-?\d+(,-?\d+)*$/', $value)) {
        throw new invalid_parameter_exception('Malformed competence selection');
    }
    return block_exaport_normalize_competenceids(explode(',', $value));
}

/**
 * Collect descriptor ids from an Exacomp competence tree.
 *
 * @param array $items Exacomp tree nodes.
 * @return int[] Descriptor ids available in the tree.
 */
function block_exaport_competence_tree_descriptorids(array $items): array {
    $descriptorids = [];
    foreach ($items as $item) {
        if ($item instanceof \block_exacomp\descriptor) {
            $descriptorids[] = (int)$item->id;
        }
        $descriptorids = array_merge(
            $descriptorids,
            block_exaport_competence_tree_descriptorids($item->get_subs() ?: [])
        );
    }
    return $descriptorids;
}

/**
 * Reject submitted competence ids that are not available to the user.
 *
 * @param int[] $competenceids Submitted competence ids.
 * @param int[] $availableids Descriptor ids available to the user.
 * @return int[] Validated competence ids.
 */
function block_exaport_validate_competenceids(array $competenceids, array $availableids): array {
    if (array_diff($competenceids, $availableids)) {
        throw new invalid_parameter_exception('Invalid competence selection');
    }
    return $competenceids;
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
 * Keep Exacomp's denormalized activity title in sync after an item rename.
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
