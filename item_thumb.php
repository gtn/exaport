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

/**
 * Sends a static thumbnail fallback file and exits.
 *
 * @param string $relativepath
 * @param string $mimetype
 * @return void
 */
function block_exaport_send_thumb_static_fallback(string $relativepath, string $mimetype): void {
    global $CFG;

    header('Content-Type: ' . $mimetype);
    readfile($CFG->dirroot . '/blocks/exaport/' . ltrim($relativepath, '/'));
    exit;
}

$itemid = optional_param('item_id', -1, PARAM_INT);
$access = optional_param('access', '', PARAM_TEXT);
// sometimes for artifacts with multiple images
$imageindex = optional_param('imindex', '', PARAM_INT);

$ispdf = optional_param('ispdf', 0, PARAM_INT);
$is_for_pdf = false;
$pdfuserid = 0;
if ($ispdf) {
    $vhash = optional_param('vhash', 0, PARAM_RAW);
    $vid = optional_param('vid', 0, PARAM_INT);
    $pdfuserid = optional_param('uid', 0, PARAM_INT);
    $view = $DB->get_record('block_exaportview', ['id' => $vid]);
    if ($view && $view->hash == $vhash && $pdfuserid > 0) {
        $is_for_pdf = true;
    }
}

$item = null;

// Thumbnails for BackEnd (editing the view part).
if ($access == '') {
    if ($sharable = block_exaport_can_user_access_shared_item($USER->id, $itemid)) {
        // Get thumbnails if item was shared for current user.
        $item = $DB->get_record('block_exaportitem', array('id' => $itemid));
    } else {
        // Get only for self (owner).
        $item = $DB->get_record('block_exaportitem', array('id' => $itemid, 'userid' => $USER->id));
    }
} else {
    // Checking access to item by access to view.
    if (!$view = block_exaport_get_view_from_access($access, $is_for_pdf, $pdfuserid)) {
        die("view not found");
    }
    $viewownerid = $view->userid;
    $item = $DB->get_record('block_exaportitem', array('id' => $itemid));
    if (empty($item)) {
        throw new moodle_exception('item not found');
    }
    $sharable = block_exaport_can_user_access_shared_item($viewownerid, $itemid);
    if ($viewownerid != $item->userid && !$sharable) {
        throw new moodle_exception('item not found');
    }
}
if (empty($item)) {
    throw new moodle_exception('item not found');
}

// Custom Icon file.
if (($iconfile = block_exaport_get_single_file($item, 'item_iconfile')) && $iconfile->is_valid_image()) {
    send_stored_file($iconfile);
    exit;
}

switch ($item->type) {
    case "file":
        $files = array_values(block_exaport_get_item_files_array($item));
        $file = false;

        if ($files && ($imageindex || $imageindex === 0)) {
            $file = $files[$imageindex] ?? false;
            if ($file && $file->is_valid_image()) {
                send_stored_file($file, 1);
                exit;
            }
            if (!$file) {
                $file = reset($files);
            }
        } else {
            $file = block_exaport_get_item_thumbnail_file($item);
            if ($file) {
                send_stored_file($file, 1);
                exit;
            }
            $file = reset($files);
        }

        $output = block_exaport_get_renderer();
        // Needed for pix_url.
        $PAGE->set_context(context_system::instance());
        $icon = $output->image_url(file_file_icon($file, 90));
        // TODO: If Pdf will have a problems - look a solution with readfile below
        header('Location: ' . $icon);
        break;

    case "link":
        block_exaport_send_thumb_static_fallback('pix/link_tile.svg', 'image/svg+xml');
        break;

    case "note":
        block_exaport_send_thumb_static_fallback('pix/note_tile.svg', 'image/svg+xml');
        break;
    default:
        die('wrong type');
}
