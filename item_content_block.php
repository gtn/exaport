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

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);
$action = required_param('action', PARAM_ACTION);
$access = required_param('access', PARAM_TEXT);
$type = optional_param('type', '', PARAM_ALPHA);
$blockid = optional_param('blockid', 0, PARAM_INT);
$backtype = optional_param('backtype', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$item, $block] = \block_exaport\item_content_mutation_helper::require_manage_context($courseid, $itemid, $blockid);
$returnurl = \block_exaport\item_content_mutation_helper::get_return_url($item, $access, $backtype);

if ($action === 'delete') {
    if (!$block) {
        throw new moodle_exception('itemblocknotfound', 'block_exaport');
    }

    if ($confirm && confirm_sesskey()) {
        require_sesskey();
        \block_exaport\item_content_mutation_helper::delete_block($item, $block);
        redirect($returnurl);
    }

    block_exaport_print_header('myportfolio');
    echo $OUTPUT->confirm(
        \block_exaport\item_content_mutation_helper::get_delete_confirmation_text($block),
        new moodle_url('/blocks/exaport/item_content_block.php', [
            'courseid' => $courseid,
            'itemid' => $itemid,
            'blockid' => $block->id,
            'action' => 'delete',
            'access' => $access,
            'backtype' => $backtype,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        $returnurl
    );
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
    'access' => $access,
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

$data = \block_exaport\item_content_mutation_helper::prepare_form_data($item, $courseid, $access, $type, $block, $backtype);
block_exaport_print_header('myportfolio');
$form->set_data($data);
$form->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
