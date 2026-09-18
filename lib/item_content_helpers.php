<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Load an item that the current user may modify through the content editor.
 *
 * @param int $itemid Item ID.
 * @param int $courseid Course ID.
 * @return stdClass
 */
function block_exaport_get_editable_content_item(int $itemid, int $courseid): stdClass {
    global $DB, $USER;

    $item = $DB->get_record('block_exaportitem', [
        'id' => $itemid,
        'userid' => $USER->id,
    ]);
    if (!$item || (int)$item->courseid !== $courseid) {
        print_error('bookmarknotfound', 'block_exaport');
    }
    if (!block_exaport_item_is_editable($item->id)) {
        print_error('nopermissions', 'error');
    }

    return $item;
}

/**
 * Build the common record fields for a newly appended content block.
 *
 * The server calculates the order; callers never accept an order from the browser.
 *
 * @param int $itemid Item ID.
 * @param string $type Supported block type.
 * @param string $title Optional block title.
 * @param string $url Optional URL.
 * @return stdClass
 */
function block_exaport_new_content_block(int $itemid, string $type, string $title = '', string $url = ''): stdClass {
    global $DB;

    if (!in_array($type, ['text', 'link', 'file'], true)) {
        throw new coding_exception('Unsupported Exaport item content block type');
    }

    $maxsortorder = $DB->get_field('block_exaportitemblock', 'MAX(sortorder)', ['itemid' => $itemid]);
    $time = time();

    return (object)[
        'itemid' => $itemid,
        'type' => $type,
        'sortorder' => $maxsortorder === false || $maxsortorder === null ? 0 : (int)$maxsortorder + 1,
        'title' => $title,
        'content' => '',
        'contentformat' => FORMAT_HTML,
        'url' => $url,
        'timecreated' => $time,
        'timemodified' => $time,
    ];
}

/**
 * Return to the parent item editor after a content operation.
 *
 * @param int $courseid Course ID.
 * @param int $itemid Item ID.
 * @return moodle_url
 */
function block_exaport_content_return_url(int $courseid, int $itemid): moodle_url {
    return new moodle_url('/blocks/exaport/item.php', [
        'courseid' => $courseid,
        'id' => $itemid,
        'action' => 'edit',
    ]);
}
