<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/item_content_helpers.php');

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);
$blockid = optional_param('blockid', 0, PARAM_INT);
$operation = optional_param('operation', 'save', PARAM_ALPHA);

require_login($courseid);
require_capability('block/exaport:use', context_system::instance());
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$item = block_exaport_get_editable_content_item($itemid, $courseid);
$block = $blockid ? block_exaport_get_item_content_block($item, $blockid) : null;

$PAGE->set_url('/blocks/exaport/item_content_file.php', ['courseid' => $courseid, 'itemid' => $itemid,
    'blockid' => $blockid, 'operation' => $operation]);
$returnurl = block_exaport_content_return_url($courseid, $itemid);
$fileoptions = block_exaport_item_content_file_options($item, $block);
$form = block_exaport_create_item_content_form('file', $courseid, $itemid, $blockid);

if ($operation === 'delete' && $block) {
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        $transaction = $DB->start_delegated_transaction();
        block_exaport_delete_item_content_block($item, $block);
        $transaction->allow_commit();
        redirect($returnurl);
    }
    block_exaport_print_header('bookmarks' . block_exaport_get_plural_item_type('all'), 'edit');
    echo $OUTPUT->confirm(get_string('deletecontentblockconfirm', 'block_exaport'), new moodle_url($PAGE->url,
        ['confirm' => 1, 'sesskey' => sesskey()]), $returnurl);
    echo $OUTPUT->footer($course);
    exit;
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($fromform = $form->get_data()) {
    require_sesskey();
    $item = block_exaport_get_editable_content_item($itemid, $courseid);
    $block = $blockid ? block_exaport_get_item_content_block($item, $blockid) : null;

    // Recheck before creating or updating a block if the draft or quota changed.
    if ($error = block_exaport_validate_item_content_files(
        (int)$fromform->files_filemanager, $itemid, $courseid, $blockid
    )) {
        throw $error;
    }
    $transaction = $DB->start_delegated_transaction();
    $block = $block ?: block_exaport_create_file_content_block($itemid, $fromform->title);
    $DB->update_record('block_exaportitemblock', (object)['id' => $block->id, 'title' => $fromform->title,
        'timemodified' => time()]);
    file_postupdate_standard_filemanager(
        $fromform,
        'files',
        $fileoptions,
        context_user::instance((int)$item->userid),
        'block_exaport',
        'item_content_file',
        $block->id
    );
    if (!block_exaport_get_item_content_files((int)$item->userid, (int)$block->id)) {
        block_exaport_delete_item_content_block($item, $block);
    }
    $transaction->allow_commit();
    redirect($returnurl, get_string('contentblockadded', 'block_exaport'), null, \core\output\notification::NOTIFY_SUCCESS);
}

block_exaport_print_header('bookmarks' . block_exaport_get_plural_item_type('all'), 'edit');
echo $OUTPUT->heading(get_string('addfileblock', 'block_exaport'));
$form->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
