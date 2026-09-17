<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

require_once(__DIR__ . '/inc.php');

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);

$context = context_system::instance();
require_login($courseid);
require_capability('block/exaport:use', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
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

redirect(new moodle_url('/blocks/exaport/item.php', [
    'courseid' => $courseid,
    'id' => $itemid,
    'action' => 'edit',
]));
