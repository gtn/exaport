<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Validate pending text blocks submitted with the item form.
 *
 * @param string $json
 * @param int $itemid
 * @return array
 */
function block_exaport_validate_pending_text_blocks(string $json, int $itemid = 0): array {
    global $DB;

    if (trim($json) === '') {
        return [];
    }

    $drafts = json_decode($json, true);
    if (!is_array($drafts) || json_last_error() !== JSON_ERROR_NONE) {
        throw new invalid_parameter_exception('Invalid pending item content blocks.');
    }

    if (count($drafts) > 50) {
        throw new invalid_parameter_exception('Too many pending item content blocks.');
    }

    if ($itemid > 0) {
        $item = $DB->get_record('block_exaportitem', [
            'id' => $itemid,
            'userid' => $GLOBALS['USER']->id,
        ]);
        if (!$item || !block_exaport_item_is_editable($itemid)) {
            throw new required_capability_exception(
                context_system::instance(),
                'block/exaport:use',
                'nopermissions',
                ''
            );
        }
    }

    $formats = [FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN, FORMAT_WIKI, FORMAT_MARKDOWN];
    $validated = [];
    foreach ($drafts as $draft) {
        if (!is_array($draft) || ($draft['type'] ?? null) !== 'text') {
            throw new invalid_parameter_exception('Invalid pending item content block type.');
        }

        $title = clean_param((string)($draft['title'] ?? ''), PARAM_TEXT);
        if (core_text::strlen($title) > 255) {
            throw new invalid_parameter_exception('Pending item content block title is too long.');
        }

        $content = clean_param((string)($draft['content'] ?? ''), PARAM_RAW);
        if (core_text::strlen($content) > 1048576) {
            throw new invalid_parameter_exception('Pending item content block content is too long.');
        }

        $format = clean_param($draft['contentformat'] ?? FORMAT_HTML, PARAM_INT);
        if (!in_array($format, $formats, true)) {
            throw new invalid_parameter_exception('Invalid pending item content block format.');
        }

        $validated[] = [
            'type' => 'text',
            'title' => $title,
            'content' => $content,
            'contentformat' => $format,
        ];
    }

    return $validated;
}

/**
 * Persist already validated pending text blocks after the parent item exists.
 *
 * @param array $blocks
 * @param int $itemid
 */
function block_exaport_persist_pending_text_blocks(array $blocks, int $itemid): void {
    global $DB, $USER;

    if (!$blocks) {
        return;
    }

    $item = $DB->get_record('block_exaportitem', [
        'id' => $itemid,
        'userid' => $USER->id,
    ], '*', MUST_EXIST);
    if (!block_exaport_item_is_editable($itemid)) {
        throw new required_capability_exception(
            context_system::instance(),
            'block/exaport:use',
            'nopermissions',
            ''
        );
    }

    $lastblock = $DB->get_record(
        'block_exaportitemblock',
        ['itemid' => $itemid],
        'sortorder DESC, id DESC',
        'id, sortorder',
        IGNORE_MULTIPLE
    );
    $sortorder = $lastblock ? (int)$lastblock->sortorder + 1 : 0;
    $time = time();

    foreach ($blocks as $block) {
        $DB->insert_record('block_exaportitemblock', (object)[
            'itemid' => $itemid,
            'type' => 'text',
            'sortorder' => $sortorder++,
            'title' => $block['title'],
            'content' => $block['content'],
            'contentformat' => $block['contentformat'],
            'url' => '',
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
    }
}
