<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Dynamic forms are loaded directly by Moodle's external API, without going
// through the plugin's inc.php bootstrap used by the standalone pages.
require_once(__DIR__ . '/lib.php');

/**
 * Load an item that the current user may modify through the content editor.
 *
 * @param int $itemid Item ID.
 * @param int $courseid Navigation course ID; item authorization uses the trusted item record.
 * @return stdClass
 */
function block_exaport_get_editable_content_item(int $itemid, int $courseid): stdClass {
    return block_exaport_get_editable_item($itemid);
}

/**
 * Load the supported structured content blocks belonging to an item.
 *
 * The returned array is always a zero-based list. Unknown block types are
 * deliberately ignored so that a newer block type cannot break older import,
 * copy, or display code.
 *
 * @param int $itemid Item ID.
 * @return stdClass[] Blocks ordered by sortorder ASC, id ASC.
 */
function block_exaport_get_item_content_blocks(int $itemid): array {
    global $DB;

    $blocks = $DB->get_records('block_exaportitemblock', ['itemid' => $itemid], 'sortorder ASC, id ASC');
    return array_values(array_filter($blocks, static function(stdClass $block): bool {
        return in_array($block->type ?? '', ['text', 'link', 'file'], true);
    }));
}

/**
 * Backwards-compatible loader for text blocks only.
 *
 * @param int $itemid Item ID.
 * @return stdClass[] Text blocks ordered by sortorder ASC, id ASC.
 */
function block_exaport_get_item_content_text_blocks(int $itemid): array {
    return array_values(array_filter(block_exaport_get_item_content_blocks($itemid),
        static function(stdClass $block): bool {
            return ($block->type ?? '') === 'text';
        }));
}

/**
 * Load the files stored for a file content block.
 *
 * Directories are excluded. Ordering by filepath first makes this helper safe
 * if subdirectories are enabled later; filename and file id provide stable
 * tie-breakers for imports which contain duplicate names in different paths.
 *
 * @param int $userid Owner of the user-context files.
 * @param int $blockid Content block ID (the file area's itemid).
 * @return stored_file[] Files ordered by filepath ASC, filename ASC, id ASC.
 */
function block_exaport_get_item_content_files(int $userid, int $blockid): array {
    $files = get_file_storage()->get_area_files(
        context_user::instance($userid)->id,
        'block_exaport',
        'item_content_file',
        $blockid,
        'filepath ASC, filename ASC, id ASC',
        false
    );
    return array_values($files);
}

/**
 * Load a file block only when it belongs to the requested item.
 *
 * This is the authoritative relationship check used before serving structured
 * files; callers must still authorize access to the returned block's item.
 *
 * @param int $itemid Item expected to own the block.
 * @param int $blockid Content block ID.
 * @return stdClass|false Matching file block, or false.
 */
function block_exaport_get_item_content_file_block(int $itemid, int $blockid) {
    global $DB;

    return $DB->get_record('block_exaportitemblock', [
        'id' => $blockid,
        'itemid' => $itemid,
        'type' => 'file',
    ]);
}

/**
 * Whether an item contains link or file blocks.
 *
 * Text-only structured content does not count because legacy item content can
 * represent text, but has no equivalent representation for links or files.
 *
 * @param int $itemid Item ID.
 * @return bool
 */
function block_exaport_item_has_structured_link_or_file_content(int $itemid): bool {
    global $DB;

    return $DB->record_exists_select(
        'block_exaportitemblock',
        'itemid = :itemid AND (type = :linktype OR type = :filetype)',
        ['itemid' => $itemid, 'linktype' => 'link', 'filetype' => 'file']
    );
}

/**
 * Obtain the sort order to use when appending a block to an item.
 *
 * @param int $itemid Item ID.
 * @return int Zero for the first block, otherwise the current maximum plus one.
 */
function block_exaport_get_next_item_content_sortorder(int $itemid): int {
    global $DB;

    $maximum = $DB->get_field('block_exaportitemblock', 'MAX(sortorder)', ['itemid' => $itemid]);
    return $maximum === false || $maximum === null ? 0 : (int)$maximum + 1;
}

/**
 * Build the common record fields for a newly appended content block.
 *
 * Defaults are suitable for interactive append operations. Trusted callers such
 * as migrations, imports, and copy operations may supply explicit storage
 * fields; HTTP parameters must never be passed through as the fields array.
 *
 * @param int $itemid Item ID.
 * @param string $type Supported block type.
 * @param string $title Optional block title.
 * @param string $url Optional URL.
 * @param array $fields Optional sortorder, content, contentformat, timecreated,
 *     and timemodified overrides selected by trusted server-side code.
 * @return stdClass
 */
function block_exaport_new_content_block(
    int $itemid,
    string $type,
    string $title = '',
    string $url = '',
    array $fields = []
): stdClass {
    if (!in_array($type, ['text', 'link', 'file'], true)) {
        throw new coding_exception('Unsupported Exaport item content block type');
    }

    $supportedfields = ['sortorder', 'content', 'contentformat', 'timecreated', 'timemodified'];
    $unsupportedfields = array_diff(array_keys($fields), $supportedfields);
    if ($unsupportedfields) {
        throw new coding_exception('Unsupported Exaport item content block field: ' . reset($unsupportedfields));
    }

    $time = time();

    $record = [
        'itemid' => $itemid,
        'type' => $type,
        'sortorder' => block_exaport_get_next_item_content_sortorder($itemid),
        'title' => $title,
        'content' => '',
        'contentformat' => FORMAT_HTML,
        'url' => $url,
        'timecreated' => $time,
        'timemodified' => $time,
    ];
    return (object)array_replace($record, $fields);
}

/**
 * Create and insert a structured content block without depending on form data.
 *
 * @param int $itemid Item ID.
 * @param string $type Supported block type.
 * @param string $title Optional block title.
 * @param string $url Optional URL.
 * @param array $fields Trusted storage-field overrides; see block_exaport_new_content_block().
 * @return stdClass Inserted block, including its integer ID.
 */
function block_exaport_create_content_block(
    int $itemid,
    string $type,
    string $title = '',
    string $url = '',
    array $fields = []
): stdClass {
    global $DB;

    $block = block_exaport_new_content_block($itemid, $type, $title, $url, $fields);
    $block->id = (int)$DB->insert_record('block_exaportitemblock', $block);
    return $block;
}

/**
 * Create a link block without requiring form or request data.
 *
 * @param int $itemid Item ID.
 * @param string $title Optional title.
 * @param string $url Link URL.
 * @param array $fields Trusted storage-field overrides.
 * @return stdClass Inserted block, including its ID.
 */
function block_exaport_create_link_content_block(
    int $itemid,
    string $title,
    string $url,
    array $fields = []
): stdClass {
    return block_exaport_create_content_block($itemid, 'link', $title, $url, $fields);
}

/**
 * Create a file block record without requiring form, draft-area, or request data.
 *
 * Callers such as upgrades, imports, and copy operations can use the returned ID
 * as the itemid of the item_content_file file area and populate it with the File
 * API. Interactive forms can likewise move their draft files after this call.
 *
 * @param int $itemid Item ID.
 * @param string $title Optional title.
 * @param array $fields Trusted storage-field overrides.
 * @return stdClass Inserted block, including its ID.
 */
function block_exaport_create_file_content_block(int $itemid, string $title, array $fields = []): stdClass {
    return block_exaport_create_content_block($itemid, 'file', $title, '', $fields);
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

/** Return options shared by standalone and dynamic text forms. */
function block_exaport_item_content_editor_options(): array {
    global $USER;
    return ['trusttext' => true, 'subdirs' => false, 'maxfiles' => 0, 'maxbytes' => 0,
        'context' => context_user::instance($USER->id)];
}

/** Return options shared by standalone and dynamic file forms. */
function block_exaport_item_content_file_options(): array {
    global $CFG;
    return ['subdirs' => false, 'maxfiles' => !empty($CFG->block_exaport_multiple_files_in_item) ? 10 : 1,
        'maxbytes' => $CFG->block_exaport_max_uploadfile_size, 'accepted_types' => '*'];
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
    global $USER;

    $usercontext = context_user::instance($USER->id);
    if ($type === 'text') {
        require_once(__DIR__ . '/item_content_text_form.php');
        $options = block_exaport_item_content_editor_options();
        $form = new block_exaport_item_content_text_form(null, ['editoroptions' => $options]);
        $data = (object)['courseid' => $courseid, 'itemid' => $itemid, 'title' => '',
            'content' => '', 'contentformat' => FORMAT_HTML];
        $data = file_prepare_standard_editor($data, 'content', $options, $usercontext,
            'block_exaport', 'item_content_text', 0);
    } else if ($type === 'file') {
        require_once(__DIR__ . '/item_content_form.php');
        $options = block_exaport_item_content_file_options();
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
    global $PAGE;

    $blocks = block_exaport_get_item_content_blocks((int)$item->id);
    $urls = [];
    foreach (['text', 'link', 'file'] as $type) {
        $urls[$type] = new moodle_url('/blocks/exaport/item_content_' . $type . '.php',
            ['courseid' => $courseid, 'itemid' => $item->id]);
    }
    $renderable = new \block_exaport\output\item_content_blocks($blocks, $urls, (int)$item->userid, true, false);
    return $PAGE->get_renderer('block_exaport')->render($renderable);
}
