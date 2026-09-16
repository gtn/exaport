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

require_once(__DIR__ . '/inc.php');

global $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);
$action = required_param('action', PARAM_ACTION);
$type = optional_param('type', '', PARAM_ALPHA);
$blockid = optional_param('blockid', 0, PARAM_INT);
$backtype = optional_param('backtype', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$item, $block] = \block_exaport\item_content_mutation_helper::require_manage_context($courseid, $itemid, $blockid);
$access = \block_exaport\item_content_mutation_helper::get_manage_access($item);
$returnurl = \block_exaport\item_content_mutation_helper::get_return_url($item, $access, $backtype);

if ($action === 'delete') {
    if (!$block) {
        throw new moodle_exception('itemblocknotfound', 'block_exaport');
    }

    if (data_submitted()) {
        require_sesskey();
        if ($confirm) {
            \block_exaport\item_content_mutation_helper::delete_block($item, $block);
            redirect($returnurl);
        }

        redirect(new moodle_url('/blocks/exaport/item_content_block.php', [
            'courseid' => $courseid,
            'itemid' => $itemid,
            'blockid' => $block->id,
            'action' => 'delete',
            'backtype' => $backtype,
        ]));
    }

    block_exaport_print_header('myportfolio');
    echo $OUTPUT->box_start();
    echo html_writer::tag('p', \block_exaport\item_content_mutation_helper::get_delete_confirmation_text($block));
    echo html_writer::start_div('d-flex flex-wrap gap-2');
    echo $OUTPUT->single_button(new moodle_url('/blocks/exaport/item_content_block.php', [
        'courseid' => $courseid,
        'itemid' => $itemid,
        'blockid' => $block->id,
        'action' => 'delete',
        'backtype' => $backtype,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]), get_string('delete', 'core'), 'post');
    echo $OUTPUT->single_button($returnurl, get_string('cancel', 'core'), 'get');
    echo html_writer::end_div();
    echo $OUTPUT->box_end();
    echo block_exaport_wrapperdivend();
    echo $OUTPUT->footer();
    die;
}

if ($action === 'edit') {
    if (!$block) {
        throw new moodle_exception('itemblocknotfound', 'block_exaport');
    }
    $type = (string)$block->type;
}

$type = \block_exaport\item_content_mutation_helper::require_supported_block_type($type);
$PAGE->set_url('/blocks/exaport/item_content_block.php', [
    'courseid' => $courseid,
    'itemid' => $itemid,
    'blockid' => $blockid,
    'action' => $action,
    'type' => $type,
    'backtype' => $backtype,
]);

$form = new \block_exaport\form\item_content_block_edit_form(
    $PAGE->url,
    [
        'action' => $action,
        'type' => $type,
        'item' => $item,
    ]
);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($fromform = $form->get_data()) {
    require_sesskey();

    if ($action === 'add') {
        \block_exaport\item_content_mutation_helper::create_block($item, $type, $fromform);
    } else if ($action === 'edit') {
        \block_exaport\item_content_mutation_helper::update_block($item, $block, $fromform);
    } else {
        throw new moodle_exception('unknownaction', 'block_exaport');
    }

    redirect($returnurl);
}

$data = \block_exaport\item_content_mutation_helper::prepare_form_data($item, $courseid, $type, $block, $backtype);
block_exaport_print_header('myportfolio');
$form->set_data($data);
$form->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
