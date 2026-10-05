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
require_once(__DIR__ . '/lib/item_content_helpers.php');

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);
$blockid = optional_param('blockid', 0, PARAM_INT);
$operation = optional_param('operation', 'save', PARAM_ALPHA);

$context = context_system::instance();
require_login($courseid);
require_capability('block/exaport:use', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$item = block_exaport_get_editable_content_item($itemid, $courseid);
$block = $blockid ? block_exaport_get_item_content_block($item, $blockid) : null;

$PAGE->set_url(new moodle_url('/blocks/exaport/item_content_text.php', [
    'courseid' => $courseid,
    'itemid' => $itemid,
    'blockid' => $blockid,
    'operation' => $operation,
]));

$returnurl = block_exaport_content_return_url($courseid, $itemid);
$editoroptions = block_exaport_item_content_editor_options();

$form = block_exaport_create_item_content_form('text', $courseid, $itemid, $blockid);

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

    // Re-check ownership and editability at save time.
    $item = block_exaport_get_editable_content_item($itemid, $courseid);
    $block = $blockid ? block_exaport_get_item_content_block($item, $blockid) : null;

    $transaction = $DB->start_delegated_transaction();
    $block = $block ?: block_exaport_create_content_block($itemid, 'text', $fromform->title);

    $fromform = file_postupdate_standard_editor(
        $fromform,
        'content',
        $editoroptions,
        context_user::instance((int)$item->userid),
        'block_exaport',
        'item_content_text',
        $block->id
    );
    $DB->update_record('block_exaportitemblock', (object)[
        'id' => $block->id,
        'title' => $fromform->title,
        'content' => $fromform->content,
        'contentformat' => $fromform->contentformat,
        'timemodified' => time(),
    ]);

    $transaction->allow_commit();
    redirect($returnurl, get_string('contentblockadded', 'block_exaport'), null, \core\output\notification::NOTIFY_SUCCESS);
}

block_exaport_print_header('bookmarks' . block_exaport_get_plural_item_type('all'), 'edit');
echo $OUTPUT->heading(get_string('view_specialitem_text', 'block_exaport'));
$form->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
