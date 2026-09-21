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
require_once(__DIR__ . '/lib/item_content_text_form.php');
require_once(__DIR__ . '/lib/item_content_helpers.php');

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);

$context = context_system::instance();
require_login($courseid);
require_capability('block/exaport:use', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$item = block_exaport_get_editable_content_item($itemid, $courseid);

$PAGE->set_url(new moodle_url('/blocks/exaport/item_content_text.php', [
    'courseid' => $courseid,
    'itemid' => $itemid,
]));

$returnurl = block_exaport_content_return_url($courseid, $itemid);
$editoroptions = [
    'trusttext' => true,
    'subdirs' => false,
    'maxfiles' => 0,
    'maxbytes' => 0,
    'context' => context_user::instance($USER->id),
];

$form = new block_exaport_item_content_text_form(null, [
    'editoroptions' => $editoroptions,
]);
$data = (object)[
    'courseid' => $courseid,
    'itemid' => $itemid,
    'title' => '',
    'content' => '',
    'contentformat' => FORMAT_HTML,
];
$data = file_prepare_standard_editor(
    $data,
    'content',
    $editoroptions,
    context_user::instance($USER->id),
    'block_exaport',
    'item_content_text',
    0
);
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($fromform = $form->get_data()) {
    require_sesskey();

    // Re-check ownership and editability at save time.
    block_exaport_get_editable_content_item($itemid, $courseid);

    $transaction = $DB->start_delegated_transaction();
    $block = block_exaport_new_content_block($itemid, 'text', $fromform->title);
    $block->id = $DB->insert_record('block_exaportitemblock', $block);

    $fromform = file_postupdate_standard_editor(
        $fromform,
        'content',
        $editoroptions,
        context_user::instance($USER->id),
        'block_exaport',
        'item_content_text',
        $block->id
    );
    $DB->update_record('block_exaportitemblock', (object)[
        'id' => $block->id,
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
