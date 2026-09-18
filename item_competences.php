<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/item_helpers.php');
require_once(__DIR__ . '/lib/item_competence_helpers.php');

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);
$competenceids = optional_param_array('competenceids', [], PARAM_INT);

require_login($courseid);
require_capability('block/exaport:use', context_system::instance());
require_sesskey();

if (!block_exaport_check_competence_interaction()) {
    throw new moodle_exception('nopermissions', 'error');
}

$item = block_exaport_get_editable_item($itemid, $courseid);
$competenceids = block_exaport_normalize_competenceids($competenceids);
block_exaport_sync_item_competences($item, $competenceids);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);
