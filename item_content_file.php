<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/item_content_form.php');
require_once(__DIR__ . '/lib/item_content_helpers.php');

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);

require_login($courseid);
require_capability('block/exaport:use', context_system::instance());
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
block_exaport_get_editable_item($itemid, $courseid);

$PAGE->set_url('/blocks/exaport/item_content_file.php', ['courseid' => $courseid, 'itemid' => $itemid]);
$returnurl = block_exaport_content_return_url($courseid, $itemid);
$fileoptions = [
    'subdirs' => false,
    'maxfiles' => !empty($CFG->block_exaport_multiple_files_in_item) ? 10 : 1,
    'maxbytes' => $CFG->block_exaport_max_uploadfile_size,
    'accepted_types' => '*',
];
$form = new block_exaport_item_content_file_form(null, ['fileoptions' => $fileoptions]);
$data = (object)['courseid' => $courseid, 'itemid' => $itemid, 'title' => '', 'files' => ''];
$data = file_prepare_standard_filemanager(
    $data,
    'files',
    $fileoptions,
    context_user::instance($USER->id),
    'block_exaport',
    'item_content_file',
    0
);
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($fromform = $form->get_data()) {
    require_sesskey();
    block_exaport_get_editable_item($itemid, $courseid);

    $transaction = $DB->start_delegated_transaction();
    $block = block_exaport_new_content_block($itemid, 'file', $fromform->title);
    $block->id = $DB->insert_record('block_exaportitemblock', $block);
    file_postupdate_standard_filemanager(
        $fromform,
        'files',
        $fileoptions,
        context_user::instance($USER->id),
        'block_exaport',
        'item_content_file',
        $block->id
    );
    $transaction->allow_commit();
    redirect($returnurl, get_string('contentblockadded', 'block_exaport'), null, \core\output\notification::NOTIFY_SUCCESS);
}

block_exaport_print_header('bookmarks' . block_exaport_get_plural_item_type('all'), 'edit');
echo $OUTPUT->heading(get_string('addfileblock', 'block_exaport'));
$form->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
