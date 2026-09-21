<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Load an item that the current user may modify through the content editor.
 *
 * @param int $itemid Item ID.
 * @param int $courseid Course ID.
 * @return stdClass
 */
function block_exaport_get_editable_content_item(int $itemid, int $courseid): stdClass {
    global $DB, $USER;

    $item = $DB->get_record('block_exaportitem', [
        'id' => $itemid,
        'userid' => $USER->id,
    ]);
    if (!$item || (int)$item->courseid !== $courseid) {
        print_error('bookmarknotfound', 'block_exaport');
    }
    if (!block_exaport_item_is_editable($item->id)) {
        print_error('nopermissions', 'error');
    }

    return $item;
}

/**
 * Build the common record fields for a newly appended content block.
 *
 * The server calculates the order; callers never accept an order from the browser.
 *
 * @param int $itemid Item ID.
 * @param string $type Supported block type.
 * @param string $title Optional block title.
 * @param string $url Optional URL.
 * @return stdClass
 */
function block_exaport_new_content_block(int $itemid, string $type, string $title = '', string $url = ''): stdClass {
    global $DB;

    if (!in_array($type, ['text', 'link', 'file'], true)) {
        throw new coding_exception('Unsupported Exaport item content block type');
    }

    $maxsortorder = $DB->get_field('block_exaportitemblock', 'MAX(sortorder)', ['itemid' => $itemid]);
    $time = time();

    return (object)[
        'itemid' => $itemid,
        'type' => $type,
        'sortorder' => $maxsortorder === false || $maxsortorder === null ? 0 : (int)$maxsortorder + 1,
        'title' => $title,
        'content' => '',
        'contentformat' => FORMAT_HTML,
        'url' => $url,
        'timecreated' => $time,
        'timemodified' => $time,
    ];
}

/**
 * Return to the parent item editor after a content operation.
 *
 * @param int $courseid Course ID.
 * @param int $itemid Item ID.
 * @return moodle_url
 */
function block_exaport_content_return_url(int $courseid, int $itemid): moodle_url {
    return new moodle_url('/blocks/exaport/item.php', [
        'courseid' => $courseid,
        'id' => $itemid,
        'action' => 'edit',
    ]);
}

/**
 * Create and initialise an add-content form.
 *
 * Keeping this here makes the exact same Moodle form available to both the
 * standalone pages and Fragment API requests.
 *
 * @param string $type text, link or file.
 * @param int $courseid Course ID.
 * @param int $itemid Item ID.
 * @return moodleform
 */
function block_exaport_create_item_content_form(string $type, int $courseid, int $itemid) {
    global $CFG, $USER;

    $usercontext = context_user::instance($USER->id);
    if ($type === 'text') {
        require_once(__DIR__ . '/item_content_text_form.php');
        $options = ['trusttext' => true, 'subdirs' => false, 'maxfiles' => 0,
            'maxbytes' => 0, 'context' => $usercontext];
        $form = new block_exaport_item_content_text_form(null, ['editoroptions' => $options]);
        $data = (object)['courseid' => $courseid, 'itemid' => $itemid, 'title' => '',
            'content' => '', 'contentformat' => FORMAT_HTML];
        $data = file_prepare_standard_editor($data, 'content', $options, $usercontext,
            'block_exaport', 'item_content_text', 0);
    } else if ($type === 'file') {
        require_once(__DIR__ . '/item_content_form.php');
        $options = ['subdirs' => false, 'maxfiles' => !empty($CFG->block_exaport_multiple_files_in_item) ? 10 : 1,
            'maxbytes' => $CFG->block_exaport_max_uploadfile_size, 'accepted_types' => '*'];
        $form = new block_exaport_item_content_file_form(null, ['fileoptions' => $options]);
        $data = (object)['courseid' => $courseid, 'itemid' => $itemid, 'title' => '', 'files' => ''];
        $data = file_prepare_standard_filemanager($data, 'files', $options, $usercontext,
            'block_exaport', 'item_content_file', 0);
    } else if ($type === 'link') {
        require_once(__DIR__ . '/item_content_form.php');
        $form = new block_exaport_item_content_link_form();
        $data = (object)['courseid' => $courseid, 'itemid' => $itemid];
    } else {
        throw new coding_exception('Unsupported Exaport item content block type');
    }
    $form->set_data($data);
    return $form;
}

/** Render the editable content section after an asynchronous save. */
function block_exaport_render_item_content_blocks(int $courseid, stdClass $item): string {
    global $DB, $PAGE;

    $blocks = $DB->get_records('block_exaportitemblock', ['itemid' => $item->id], 'sortorder ASC, id ASC');
    $urls = [];
    foreach (['text', 'link', 'file'] as $type) {
        $urls[$type] = new moodle_url('/blocks/exaport/item_content_' . $type . '.php',
            ['courseid' => $courseid, 'itemid' => $item->id]);
    }
    $renderable = new \block_exaport\output\item_content_blocks($blocks, $urls, (int)$item->userid, true, false);
    return $PAGE->get_renderer('block_exaport')->render($renderable);
}

/** Build a consistent response used by all three content endpoints. */
function block_exaport_item_content_response(bool $success, array $data = []): array {
    return array_merge(['success' => $success], $data);
}

/** Send a consistent JSON response used by all three content endpoints. */
function block_exaport_send_item_content_json(bool $success, array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(block_exaport_item_content_response($success, $data));
    exit;
}
