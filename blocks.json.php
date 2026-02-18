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
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

require_once(__DIR__ . '/inc.php');

use block_exaport\blockedit;

// AJAX endpoint for block editing (save actions still use this directly).

$courseid = optional_param('courseid', 0, PARAM_INT);
$url = '/blocks/exaport/blocks.json.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
$PAGE->set_context(context_system::instance());

require_login($courseid);

$type = optional_param('type_block', '', PARAM_RAW);
$id = optional_param('item_id', 0, PARAM_INT);

$formdata = blockedit::load_form($id, $type);

header('Content-Type: application/json');
echo json_encode([
    'html' => $formdata->html,
    'modalTitle' => $formdata->title,
]);
