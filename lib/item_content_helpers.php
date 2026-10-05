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
 * Load a content block through its already-authorised parent item.
 *
 * @param stdClass $item Trusted parent item.
 * @param int $blockid Block ID supplied by the request.
 * @return stdClass
 */
function block_exaport_get_item_content_block(stdClass $item, int $blockid): stdClass {
    global $DB;

    $block = $DB->get_record('block_exaportitemblock', [
        'id' => $blockid,
        'itemid' => $item->id,
    ]);
    if (!$block || !in_array($block->type ?? '', ['text', 'link', 'file'], true)) {
        throw new invalid_parameter_exception('Content block does not belong to this item');
    }
    return $block;
}

/** Delete one block and both of its possible file areas. */
function block_exaport_delete_item_content_block(stdClass $item, stdClass $block): void {
    global $DB;

    if ((int)$block->itemid !== (int)$item->id) {
        throw new invalid_parameter_exception('Content block does not belong to this item');
    }
    $context = context_user::instance((int)$item->userid, MUST_EXIST);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'block_exaport', 'item_content_text', (int)$block->id);
    $fs->delete_area_files($context->id, 'block_exaport', 'item_content_file', (int)$block->id);
    $DB->delete_records('block_exaportitemblock', ['id' => $block->id, 'itemid' => $item->id]);
}

/**
 * Delete all structured blocks and their files for an item.
 *
 * Authorization is the caller's responsibility. This operation does not delete
 * the parent item or any item-level data.
 *
 * @param stdClass $item Trusted item containing id and userid.
 * @return void
 */
function block_exaport_delete_item_content(stdClass $item): void {
    global $DB;

    if (empty($item->id) || empty($item->userid)) {
        throw new invalid_parameter_exception('An item id and owner id are required');
    }

    $context = context_user::instance((int)$item->userid, MUST_EXIST);
    $fs = get_file_storage();
    $blocks = $DB->get_records('block_exaportitemblock', ['itemid' => $item->id], '', 'id');
    foreach ($blocks as $block) {
        $fs->delete_area_files($context->id, 'block_exaport', 'item_content_text', $block->id);
        $fs->delete_area_files($context->id, 'block_exaport', 'item_content_file', $block->id);
    }
    $DB->delete_records('block_exaportitemblock', ['itemid' => $item->id]);
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
 * Build the target-neutral structured-content projection used by exporters.
 *
 * File contents are deliberately not read. Both file lists contain stored_file
 * instances and are ordered deterministically by filepath, filename, then id.
 * Legacy URL and item_file content are intentionally not merged into this
 * projection: exporters must represent those separately during the transition.
 *
 * @param stdClass $item Trusted item record containing id and userid.
 * @return array[] Ordered block projections.
 */
function block_exaport_get_item_content_export_data(stdClass $item): array {
    if (empty($item->id) || empty($item->userid)) {
        throw new coding_exception('Item content export requires item and owner IDs');
    }

    $context = context_user::instance((int)$item->userid);
    $fs = get_file_storage();
    $result = [];
    foreach (block_exaport_get_item_content_blocks((int)$item->id) as $block) {
        $files = [];
        $editorfiles = [];
        if ($block->type === 'file') {
            $files = block_exaport_get_item_content_files((int)$item->userid, (int)$block->id);
        } else if ($block->type === 'text') {
            $editorfiles = array_values($fs->get_area_files(
                $context->id,
                'block_exaport',
                'item_content_text',
                (int)$block->id,
                'filepath ASC, filename ASC, id ASC',
                false
            ));
        }
        $result[] = [
            'itemid' => (int)$item->id,
            'ownerid' => (int)$item->userid,
            'blockid' => (int)$block->id,
            'sortorder' => (int)$block->sortorder,
            'type' => $block->type,
            'title' => (string)($block->title ?? ''),
            'content' => (string)($block->content ?? ''),
            'contentformat' => (int)($block->contentformat ?? FORMAT_HTML),
            'url' => (string)($block->url ?? ''),
            'files' => $files,
            'editorfiles' => $editorfiles,
        ];
    }
    return $result;
}

/**
 * Build the structured and legacy-compatible Exaport web-service projections.
 *
 * Structured blocks are the only runtime source. The legacy URL and files are
 * deliberately lossy projections: URL is the first non-empty link and files
 * flatten every file block. Parent item legacy columns and item_file storage
 * are never inspected. Callers should pass the current web-service token so
 * file links use Moodle's authenticated web-service endpoint.
 *
 * @param stdClass $item Trusted item record containing id and userid.
 * @param string|null $token Current web-service token, if token authenticated.
 * @return array{contentblocks: array, url: string, files: array}
 */
function block_exaport_get_item_content_webservice_data(stdClass $item, ?string $token = null): array {
    $result = ['contentblocks' => [], 'url' => '', 'files' => []];
    $script = $token !== null && $token !== '' ? '/webservice/pluginfile.php' : '/pluginfile.php';

    // Load blocks and their files once, then derive both response shapes from that data.
    foreach (block_exaport_get_item_content_export_data($item) as $contentblock) {
        $resultblock = (object)[
            'id' => $contentblock['blockid'],
            'sortorder' => $contentblock['sortorder'],
            'type' => $contentblock['type'],
            'title' => $contentblock['title'],
            'content' => $contentblock['content'],
            'contentformat' => $contentblock['contentformat'],
            'url' => $contentblock['url'],
            'files' => [],
        ];
        if ($contentblock['type'] === 'link' && $result['url'] === '' && trim($contentblock['url']) !== '') {
            $result['url'] = $contentblock['url'];
        }
        foreach ($contentblock['files'] as $file) {
            // The web-service route deliberately carries the trusted owner ID. pluginfile.php
            // can then apply the same owner/teacher/trainer/shared-view policy as the old
            // portfoliofile.php endpoint instead of treating every token user as an owner.
            $access = $token !== null && $token !== '' ? 'webservice/' . (int)$item->userid : '';
            $fileurl = block_exaport_get_item_content_file_url(
                (int)$item->id, (int)$contentblock['blockid'], $file, $access, $script
            );
            if ($token !== null && $token !== '') {
                $fileurl .= '?token=' . rawurlencode($token);
            }
            $fileentry = [
                'id' => (int)$file->get_id(),
                'filename' => $file->get_filename(),
                'url' => $fileurl,
                'mimetype' => $file->get_mimetype(),
                'isimage' => strpos((string)$file->get_mimetype(), 'image/') === 0,
            ];
            $resultblock->files[] = $fileentry;
            if ($contentblock['type'] === 'file') {
                $result['files'][] = $fileentry;
            }
        }
        $result['contentblocks'][] = $resultblock;
    }
    return $result;
}

/**
 * Copy all supported structured content between two existing items.
 *
 * Blocks are inserted in their existing sortorder/id order and retain their
 * original sortorder and creation time. Their modification time is the time of
 * the copy. File metadata is retained by cloning each stored_file; only its
 * owner context, user, file area itemid, and (where applicable) file area are
 * changed. The caller owns the transaction and must create the parent item.
 *
 * @param stdClass $sourceitem Source item containing trusted id and userid.
 * @param stdClass $destinationitem Destination item containing trusted id and userid.
 * @param callable|null $filecopier Optional stored-file copier receiving target metadata and source file.
 * @return array<int, int> Source block ID to destination block ID map.
 */
function block_exaport_copy_item_content(
    stdClass $sourceitem,
    stdClass $destinationitem,
    ?callable $filecopier = null
): array {
    global $DB;

    if (empty($sourceitem->id) || empty($sourceitem->userid) ||
            empty($destinationitem->id) || empty($destinationitem->userid)) {
        throw new coding_exception('Item content copying requires item and owner IDs');
    }

    $sourcecontext = context_user::instance((int)$sourceitem->userid);
    $destinationcontext = context_user::instance((int)$destinationitem->userid);
    $fs = get_file_storage();
    $filecopier = $filecopier ?? static function(array $fileinfo, stored_file $sourcefile) use ($fs): void {
        $fs->create_file_from_storedfile($fileinfo, $sourcefile);
    };
    $blockmap = [];
    $copytime = time();

    foreach (block_exaport_get_item_content_blocks((int)$sourceitem->id) as $sourceblock) {
        $destinationblock = (object)[
            'itemid' => (int)$destinationitem->id,
            'type' => $sourceblock->type,
            'sortorder' => (int)$sourceblock->sortorder,
            'title' => $sourceblock->title,
            'content' => $sourceblock->content,
            'contentformat' => (int)$sourceblock->contentformat,
            'url' => $sourceblock->url,
            'timecreated' => (int)$sourceblock->timecreated,
            'timemodified' => $copytime,
        ];
        $destinationblock->id = (int)$DB->insert_record('block_exaportitemblock', $destinationblock);
        $blockmap[(int)$sourceblock->id] = $destinationblock->id;

        $fileareas = [];
        if ($sourceblock->type === 'file') {
            $fileareas['item_content_file'] = block_exaport_get_item_content_files(
                (int)$sourceitem->userid,
                (int)$sourceblock->id
            );
        } else if ($sourceblock->type === 'text') {
            $fileareas['item_content_text'] = array_values($fs->get_area_files(
                $sourcecontext->id,
                'block_exaport',
                'item_content_text',
                (int)$sourceblock->id,
                'filepath ASC, filename ASC, id ASC',
                false
            ));
        }

        foreach ($fileareas as $filearea => $files) {
            foreach ($files as $file) {
                $filecopier([
                    'contextid' => $destinationcontext->id,
                    'component' => 'block_exaport',
                    'filearea' => $filearea,
                    'itemid' => $destinationblock->id,
                    'userid' => (int)$destinationitem->userid,
                ], $file);
            }
        }
    }

    return $blockmap;
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
 * Copy a stored file into a new structured file block.
 *
 * The caller owns the transaction which created the parent item. The empty
 * title is intentional: imported artifact titles live on the parent item.
 *
 * @param stdClass $item Trusted destination item, including id and userid.
 * @param stored_file $file Source file.
 * @param string $title Optional distinct content title.
 * @return stdClass Created file block.
 */
function block_exaport_import_stored_file_into_content_block(
    stdClass $item,
    stored_file $file,
    string $title = ''
): stdClass {
    if (empty($item->id) || empty($item->userid)) {
        throw new coding_exception('Structured file import requires item and owner IDs');
    }

    $block = block_exaport_create_file_content_block((int)$item->id, $title);
    get_file_storage()->create_file_from_storedfile([
        'contextid' => context_user::instance((int)$item->userid)->id,
        'component' => 'block_exaport',
        'filearea' => 'item_content_file',
        'itemid' => $block->id,
        'userid' => (int)$item->userid,
    ], $file);
    return $block;
}

/**
 * Build an access-aware URL for a structured block file, preserving its File API filepath.
 *
 * @param int $itemid Parent Exaport item ID.
 * @param int $blockid Structured file block ID.
 * @param stored_file $file Stored file belonging to the block.
 * @param string $access Optional Exaport authorization path.
 * @param string $script Pluginfile script, normally /pluginfile.php or /webservice/pluginfile.php.
 * @return string
 */
function block_exaport_get_item_content_file_url(
    int $itemid,
    int $blockid,
    stored_file $file,
    string $access = '',
    string $script = '/pluginfile.php'
): string {
    global $CFG;

    $parts = [(int)$file->get_contextid(), 'block_exaport', 'item_content_file'];
    if (trim($access, '/') !== '') {
        $parts = array_merge($parts, explode('/', trim($access, '/')));
    }
    $parts[] = 'itemid';
    $parts[] = $itemid;
    $parts[] = 'blockid';
    $parts[] = $blockid;
    $filepath = trim($file->get_filepath(), '/');
    if ($filepath !== '') {
        $parts = array_merge($parts, explode('/', $filepath));
    }
    $parts[] = $file->get_filename();

    return file_encode_url($CFG->wwwroot . $script, '/' . implode('/', $parts), true);
}

/**
 * Parse item_content_file pluginfile arguments without discarding nested paths.
 *
 * @param array $args Decoded pluginfile arguments following the file area.
 * @return array|false Parsed access, itemid, blockid, filepath, and filename; false for malformed input.
 */
function block_exaport_parse_item_content_file_args(array $args) {
    $args = array_values($args);
    $itemmarker = false;
    foreach ($args as $index => $argument) {
        if ($argument === 'itemid' && isset($args[$index + 1], $args[$index + 2], $args[$index + 3]) &&
                $args[$index + 2] === 'blockid' && ctype_digit((string)$args[$index + 1]) &&
                ctype_digit((string)$args[$index + 3])) {
            $itemmarker = $index;
            break;
        }
    }
    if ($itemmarker === false || (int)$args[$itemmarker + 1] < 1 || (int)$args[$itemmarker + 3] < 1) {
        return false;
    }
    $blockmarker = $itemmarker + 2;

    $pathargs = array_values(array_slice($args, $blockmarker + 2));
    if (!$pathargs) {
        return false;
    }
    $filename = array_pop($pathargs);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return false;
    }
    foreach ($pathargs as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return [
        'access' => implode('/', array_slice($args, 0, $itemmarker)),
        'itemid' => (int)$args[$itemmarker + 1],
        'blockid' => (int)$args[$blockmarker + 1],
        'filepath' => $pathargs ? '/' . implode('/', $pathargs) . '/' : '/',
        'filename' => $filename,
    ];
}

/**
 * Resolve an archive-relative path while keeping it beneath the extraction root.
 *
 * @param string $root Extraction directory.
 * @param string $basedir Directory containing the referring package document.
 * @param string $path Untrusted package-relative path.
 * @return array{pathname: string, filepath: string, filename: string}
 */
function block_exaport_resolve_import_file_path(string $root, string $basedir, string $path): array {
    $decodedpath = html_entity_decode($path, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decodedpath = str_replace('\\', '/', $decodedpath);
    if ($decodedpath === '' || $decodedpath[0] === '/' || preg_match('/^[a-zA-Z]:\//', $decodedpath)) {
        throw new invalid_parameter_exception('Invalid absolute package file path');
    }
    $segments = explode('/', $decodedpath);
    if (in_array('..', $segments, true) || in_array('', $segments, true)) {
        throw new invalid_parameter_exception('Invalid package file path traversal');
    }

    $rootpath = realpath($root);
    $basepath = realpath($basedir);
    $candidate = realpath($basedir . '/' . implode('/', array_map(static function(string $segment): string {
        return clean_param($segment, PARAM_FILE);
    }, $segments)));
    if ($rootpath === false || $basepath === false || $candidate === false || !is_file($candidate)) {
        throw new invalid_parameter_exception('Package file does not exist');
    }
    $rootprefix = rtrim($rootpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $baseprefix = rtrim($basepath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($candidate, $rootprefix) !== 0 || strpos($basepath . DIRECTORY_SEPARATOR, $rootprefix) !== 0 ||
            strpos($candidate, $baseprefix) !== 0) {
        throw new invalid_parameter_exception('Package file is outside the import directory');
    }

    $relative = substr($candidate, strlen($baseprefix));
    $dirname = dirname($relative);
    return [
        'pathname' => $candidate,
        'filepath' => $dirname === '.' ? '/' : '/' . trim($dirname, '/') . '/',
        'filename' => basename($relative),
    ];
}

/**
 * Import a declared group of package files into one structured file block.
 *
 * All paths are validated before the block is created, so a missing or unsafe
 * required file cannot produce an empty or partially populated block. The
 * caller owns the surrounding delegated transaction.
 *
 * @param stdClass $item Trusted destination item, including id and userid.
 * @param string $root Extraction directory boundary.
 * @param string $basedir Directory containing the referring package document.
 * @param string[] $paths Declared package-relative paths.
 * @param string $title Optional distinct content title.
 * @return stdClass Created file block.
 */
function block_exaport_import_path_files_into_content_block(
    stdClass $item,
    string $root,
    string $basedir,
    array $paths,
    string $title = ''
): stdClass {
    if (empty($item->id) || empty($item->userid) || !$paths) {
        throw new coding_exception('Structured package import requires an item, owner, and files');
    }
    $resolvedfiles = [];
    foreach ($paths as $path) {
        $resolvedfiles[] = block_exaport_resolve_import_file_path($root, $basedir, (string)$path);
    }

    $block = block_exaport_create_file_content_block((int)$item->id, $title);
    $contextid = context_user::instance((int)$item->userid)->id;
    $fs = get_file_storage();
    foreach ($resolvedfiles as $resolvedfile) {
        $fs->create_file_from_pathname([
            'contextid' => $contextid,
            'component' => 'block_exaport',
            'filearea' => 'item_content_file',
            'itemid' => $block->id,
            'filepath' => $resolvedfile['filepath'],
            'filename' => $resolvedfile['filename'],
            'userid' => (int)$item->userid,
        ], $resolvedfile['pathname']);
    }
    return $block;
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
function block_exaport_item_content_file_options(?stdClass $item = null, ?stdClass $block = null): array {
    global $CFG;
    $maxfiles = !empty($CFG->block_exaport_multiple_files_in_item) ? 10 : 1;
    $subdirs = false;
    if ($item && $block) {
        $files = block_exaport_get_item_content_files((int)$item->userid, (int)$block->id);
        // Never truncate migrated files, while retaining room for the configured number of new uploads.
        $maxfiles = max($maxfiles, count($files) + $maxfiles);
        foreach ($files as $file) {
            $subdirs = $subdirs || $file->get_filepath() !== '/';
        }
    }
    return ['subdirs' => $subdirs, 'maxfiles' => $maxfiles,
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
function block_exaport_create_item_content_form(string $type, int $courseid, int $itemid, int $blockid = 0) {
    $item = block_exaport_get_editable_content_item($itemid, $courseid);
    $block = $blockid ? block_exaport_get_item_content_block($item, $blockid) : null;
    if ($block && $block->type !== $type) {
        throw new invalid_parameter_exception('Content block type does not match the form');
    }
    $usercontext = context_user::instance((int)$item->userid);
    if ($type === 'text') {
        require_once(__DIR__ . '/item_content_text_form.php');
        $options = block_exaport_item_content_editor_options();
        $form = new block_exaport_item_content_text_form(null, ['editoroptions' => $options]);
        $data = (object)['courseid' => $courseid, 'itemid' => $itemid, 'blockid' => $blockid,
            'title' => $block->title ?? '', 'content' => $block->content ?? '',
            'contentformat' => $block->contentformat ?? FORMAT_HTML];
        $data = file_prepare_standard_editor($data, 'content', $options, $usercontext,
            'block_exaport', 'item_content_text', $blockid);
    } else if ($type === 'file') {
        require_once(__DIR__ . '/item_content_form.php');
        $options = block_exaport_item_content_file_options($item, $block);
        $form = new block_exaport_item_content_file_form(null, ['fileoptions' => $options, 'blockid' => $blockid]);
        $data = (object)['courseid' => $courseid, 'itemid' => $itemid, 'blockid' => $blockid,
            'title' => $block->title ?? '', 'files' => ''];
        $data = file_prepare_standard_filemanager($data, 'files', $options, $usercontext,
            'block_exaport', 'item_content_file', $blockid);
    } else if ($type === 'link') {
        require_once(__DIR__ . '/item_content_form.php');
        $form = new block_exaport_item_content_link_form();
        $data = (object)['courseid' => $courseid, 'itemid' => $itemid, 'blockid' => $blockid,
            'title' => $block->title ?? '', 'url' => $block->url ?? ''];
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
