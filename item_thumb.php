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

require_login(0, true);

/**
 * Sends a static thumbnail fallback file and exits.
 *
 * @param string $relativepath
 * @return void
 */
function block_exaport_send_thumb_static_fallback(string $relativepath): void {
    global $CFG;

    $filepath = $CFG->dirroot . '/blocks/exaport/' . ltrim($relativepath, '/');
    send_file($filepath, basename($filepath), DAYSECS, $CFG->filteruploadedfiles, false, true);
    exit;
}

$itemid = optional_param('item_id', -1, PARAM_INT);
$access = optional_param('access', '', PARAM_TEXT);
// sometimes for artifacts with multiple images
$imageindex = optional_param('imindex', '', PARAM_INT);

$item = null;

// Thumbnails for BackEnd (editing the view part).
if ($access == '') {
    require_capability('block/exaport:use', context_system::instance());
    if ($sharable = block_exaport_can_user_access_shared_item($USER->id, $itemid)) {
        // Get thumbnails if item was shared for current user.
        $item = $DB->get_record('block_exaportitem', array('id' => $itemid));
    } else {
        // Get only for self (owner).
        $item = $DB->get_record('block_exaportitem', array('id' => $itemid, 'userid' => $USER->id));
    }
} else {
    // A collection grant covers its selected items, not all of its owner's images.
    $item = block_exaport_get_item($itemid, 'view/' . $access);
}
if (empty($item)) {
    throw new moodle_exception('filenotfound', 'error');
}

// Custom icons always override indexed and automatically selected item files.
if (($iconfile = block_exaport_get_single_file($item, 'item_iconfile')) && $iconfile->is_valid_image()) {
    send_stored_file($iconfile);
    exit;
}

$file = false;
if ($imageindex !== '') {
    // Structured files form the indexed list when present; otherwise indexes
    // retain their historical meaning in the deterministic legacy file list.
    $file = block_exaport_get_item_thumbnail_candidate($item, $imageindex);
} else {
    $file = block_exaport_get_item_thumbnail_file($item);
    if (!$file) {
        $files = block_exaport_get_item_thumbnail_candidates($item);
        $file = reset($files);
    }
}

if ($file && $file->is_valid_image()) {
    send_stored_file($file, 1);
    exit;
}

if ($file) {
    $output = block_exaport_get_renderer();
    // Needed for image_url(). Never pass an absent file to file_file_icon().
    $PAGE->set_context(context_system::instance());
    $icon = $output->image_url(file_file_icon($file, 90));
    redirect($icon);
}

switch ($item->type) {
    case 'link':
        block_exaport_send_thumb_static_fallback('pix/link_tile.svg');
        break;
    case 'note':
        block_exaport_send_thumb_static_fallback('pix/note_tile.svg');
        break;
    default:
        throw new moodle_exception('filenotfound', 'error');
}
