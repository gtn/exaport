<?php
// This file is part of Exabis Eportfolio (extension for Moodle)

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/item_content_helpers.php');

/** Extract the structured sidecar archive path from an item page. */
function block_exaport_package_sidecar_path(string $html): ?string {
    if (!preg_match('/<!--###EXAPORT_ITEM_CONTENT_V1:([^\r\n<>]+)###-->/', $html, $matches)) {
        return null;
    }
    return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Validate a File API path supplied by the structured package. */
function block_exaport_validate_package_filepath(string $filepath): string {
    if ($filepath === '' || $filepath[0] !== '/' || substr($filepath, -1) !== '/' ||
            strpos($filepath, '\\') !== false || strpos($filepath, "\0") !== false) {
        throw new invalid_parameter_exception('Invalid structured content file path');
    }
    if ($filepath === '/') {
        return $filepath;
    }
    foreach (explode('/', trim($filepath, '/')) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new invalid_parameter_exception('Invalid structured content file path traversal');
        }
    }
    return $filepath;
}

/**
 * Decode and strictly validate a version 1 structured item sidecar.
 *
 * @return array Validated manifest.
 */
function block_exaport_decode_item_package(string $json): array {
    $data = json_decode($json, true);
    if (!is_array($data) || ($data['format'] ?? null) !== 'exaport-item-content' ||
            ($data['version'] ?? null) !== 1 || !is_array($data['parent'] ?? null) ||
            !is_array($data['blocks'] ?? null) || !is_string($data['parent']['type'] ?? null) ||
            !in_array($data['parent']['type'] ?? null, ['text', 'note', 'link', 'file'], true) ||
            !is_string($data['parent']['intro'] ?? null)) {
        throw new invalid_parameter_exception('Malformed or unsupported Exaport structured content metadata');
    }
    foreach ($data['blocks'] as $block) {
        $required = ['type', 'sortorder', 'title', 'content', 'contentformat', 'url', 'files', 'textassets'];
        if (!is_array($block) || array_diff($required, array_keys($block)) ||
                !in_array($block['type'] ?? null, ['text', 'link', 'file'], true) ||
                !is_int($block['sortorder']) || !is_string($block['title']) || !is_string($block['content']) ||
                !is_int($block['contentformat']) || !is_string($block['url']) ||
                !is_array($block['files']) || !is_array($block['textassets']) ||
                ($block['type'] === 'link' && clean_param($block['url'], PARAM_URL) === '') ||
                ($block['type'] === 'file' && !$block['files']) ||
                ($block['type'] !== 'file' && $block['files']) ||
                ($block['type'] !== 'text' && $block['textassets'])) {
            throw new invalid_parameter_exception('Malformed Exaport structured content block');
        }
        foreach (array_merge($block['files'], $block['textassets']) as $file) {
            if (!is_array($file) || !is_string($file['filepath'] ?? null) ||
                    !is_string($file['filename'] ?? null) || !is_string($file['archivepath'] ?? null) ||
                    $file['filename'] === '' || $file['filename'] === '.' || $file['filename'] === '..' ||
                    strpos($file['filename'], '/') !== false || strpos($file['filename'], '\\') !== false ||
                    strpos($file['filename'], "\0") !== false) {
                throw new invalid_parameter_exception('Malformed Exaport structured content asset');
            }
            block_exaport_validate_package_filepath($file['filepath']);
        }
    }
    return $data;
}

/**
 * Create all blocks and files declared by a validated sidecar.
 * The caller owns a transaction containing the parent item creation.
 */
function block_exaport_import_item_package(stdClass $item, string $root, array $manifest): void {
    $resolved = [];
    foreach ($manifest['blocks'] as $blockindex => $block) {
        foreach (['files', 'textassets'] as $area) {
            foreach ($block[$area] as $fileindex => $file) {
                // Archive paths are rooted at the package, never relative to HTML or sidecar directories.
                $resolved[$blockindex][$area][$fileindex] = block_exaport_resolve_import_file_path(
                    $root, $root, $file['archivepath']);
            }
        }
    }
    $fs = get_file_storage();
    $contextid = context_user::instance((int)$item->userid)->id;
    foreach ($manifest['blocks'] as $blockindex => $source) {
        $block = block_exaport_create_content_block((int)$item->id, $source['type'], $source['title'],
            $source['type'] === 'link' ? clean_param($source['url'], PARAM_URL) : '', [
                'sortorder' => $source['sortorder'],
                'content' => $source['content'],
                'contentformat' => $source['contentformat'],
            ]);
        foreach (['files' => 'item_content_file', 'textassets' => 'item_content_text'] as $key => $filearea) {
            foreach ($source[$key] as $fileindex => $file) {
                $fs->create_file_from_pathname([
                    'contextid' => $contextid, 'component' => 'block_exaport', 'filearea' => $filearea,
                    'itemid' => $block->id, 'filepath' => $file['filepath'], 'filename' => $file['filename'],
                    'userid' => (int)$item->userid,
                ], $resolved[$blockindex][$key][$fileindex]['pathname']);
            }
        }
    }
}
