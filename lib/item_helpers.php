<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Load an item that the current user may modify through a separate item editor.
 *
 * @param int $itemid Item ID.
 * @param int $courseid Course ID.
 * @return stdClass
 */
function block_exaport_get_editable_item(int $itemid, int $courseid): stdClass {
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
