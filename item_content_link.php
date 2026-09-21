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
$ajax = optional_param('ajax', 0, PARAM_BOOL);

require_login($courseid);
require_capability('block/exaport:use', context_system::instance());
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$item = block_exaport_get_editable_content_item($itemid, $courseid);

$PAGE->set_url('/blocks/exaport/item_content_link.php', ['courseid' => $courseid, 'itemid' => $itemid]);
$returnurl = block_exaport_content_return_url($courseid, $itemid);
$form = block_exaport_create_item_content_form('link', $courseid, $itemid);

if ($form->is_cancelled()) {
    if ($ajax) {
        block_exaport_send_item_content_json(false, ['cancelled' => true]);
    }
    redirect($returnurl);
} else if ($fromform = $form->get_data()) {
    require_sesskey();
    block_exaport_get_editable_content_item($itemid, $courseid);

    $transaction = $DB->start_delegated_transaction();
    $block = block_exaport_new_content_block($itemid, 'link', $fromform->title, $fromform->url);
    $DB->insert_record('block_exaportitemblock', $block);
    $transaction->allow_commit();
    if ($ajax) {
        block_exaport_send_item_content_json(true, [
            'content' => block_exaport_render_item_content_blocks($courseid, $item),
        ]);
    }
    redirect($returnurl, get_string('contentblockadded', 'block_exaport'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($ajax && data_submitted()) {
    block_exaport_send_item_content_json(false, [
        'validation' => true,
        'form' => $form->render(),
        'javascript' => $PAGE->requires->get_end_code(),
    ]);
}

block_exaport_print_header('bookmarks' . block_exaport_get_plural_item_type('all'), 'edit');
echo $OUTPUT->heading(get_string('addlinkblock', 'block_exaport'));
$form->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
