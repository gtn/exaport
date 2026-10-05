<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Return a safe SCORM archive path component.
 *
 * @param string $value Untrusted stored path component.
 * @return string
 */
function block_exaport_scorm_path_component(string $value): string {
    $value = clean_param(rawurldecode($value), PARAM_FILE);
    $value = str_replace(['/', '\\', '..'], '_', $value);
    return $value === '' ? 'file' : $value;
}

/**
 * Find the relative URL from a generated page to an archive asset.
 *
 * @param string $page Generated page archive path.
 * @param string $asset Asset archive path.
 * @return string
 */
function block_exaport_scorm_relative_url(string $page, string $asset): string {
    $directory = dirname($page);
    $depth = substr_count(trim($directory, './'), '/');
    if ($directory !== '.' && trim($directory, './') !== '') {
        $depth++;
    }
    return str_repeat('../', $depth) . implode('/', array_map('rawurlencode', explode('/', $asset)));
}

/**
 * Allocate a collision-safe archive path for a stored file.
 *
 * @param stored_file $file File being packaged.
 * @param string $base Trusted archive base.
 * @param string[] $existingpaths Paths already allocated in the archive.
 * @return string
 */
function block_exaport_scorm_archive_path(stored_file $file, string $base, array $existingpaths): string {
    $parts = array_filter(explode('/', trim($file->get_filepath(), '/')), 'strlen');
    $parts = array_map('block_exaport_scorm_path_component', $parts);
    $path = rtrim($base, '/') . '/' . ($parts ? implode('/', $parts) . '/' : '') .
        block_exaport_scorm_path_component($file->get_filename());
    $candidate = $path;
    $suffix = 1;
    while (in_array($candidate, $existingpaths, true)) {
        $candidate = $path . '-' . $suffix++;
    }
    return $candidate;
}

/**
 * Render normalized structured content for one SCORM item.
 *
 * The callback packages a file under the supplied trusted base and returns its
 * final archive path. This keeps rendering independently testable from ZipArchive.
 *
 * @param stdClass $item Parent item.
 * @param array[] $blocks Normalized structured blocks.
 * @param string $pagepath Generated item page archive path.
 * @param callable $packagefile Callback accepting stored_file and archive base.
 * @return array{html: string, assets: string[], assetmap: array[]}
 */
function block_exaport_scorm_render_item_content(
    stdClass $item,
    array $blocks,
    string $pagepath,
    callable $packagefile
): array {
    $html = '';
    $assets = [];
    $assetmap = [];
    foreach ($blocks as $blockindex => $block) {
        $assetmap[$blockindex] = ['files' => [], 'editorfiles' => []];
        $html .= '<section class="item-content-block item-content-' . s($block['type']) .
            '" data-block-id="' . (int)$block['blockid'] . '">';
        if ($block['title'] !== '') {
            $html .= '<h2>' . s(format_string($block['title'])) . '</h2>';
        }
        if ($block['type'] === 'link') {
            $url = clean_param($block['url'], PARAM_URL);
            if ($url !== '') {
                $html .= '<a href="' . s($url) . '">' . s($url) . '</a>';
            }
        } else if ($block['type'] === 'file') {
            foreach ($block['files'] as $file) {
                $asset = $packagefile($file, 'items/' . $item->id . '/blocks/' . $block['blockid']);
                $assets[] = $asset;
                $assetmap[$blockindex]['files'][] = $asset;
                $html .= '<a class="structured-file" href="' .
                    s(block_exaport_scorm_relative_url($pagepath, $asset)) . '">' . s($file->get_filename()) . '</a>';
            }
        } else if ($block['type'] === 'text') {
            $text = clean_text($block['content'], $block['contentformat']);
            foreach ($block['editorfiles'] as $file) {
                $asset = $packagefile($file,
                    'items/' . $item->id . '/blocks/' . $block['blockid'] . '/editor');
                $assets[] = $asset;
                $assetmap[$blockindex]['editorfiles'][] = $asset;
                $reference = ltrim($file->get_filepath(), '/') . $file->get_filename();
                $replacement = block_exaport_scorm_relative_url($pagepath, $asset);
                $encodedreference = implode('/', array_map('rawurlencode', explode('/', $reference)));
                // format_text()/clean_text() may URL-encode a valid placeholder
                // before export (notably spaces and # in editor-file names).
                $text = str_replace([
                    '@@PLUGINFILE@@/' . $reference,
                    '@@PLUGINFILE@@/' . $encodedreference,
                    '@@PLUGINFILE@@' . $file->get_filepath() . $file->get_filename(),
                ], $replacement, $text);
            }
            $html .= '<div class="structured-text">' . $text . '</div>';
        }
        $html .= '</section>' . "\n";
    }
    return ['html' => $html, 'assets' => $assets, 'assetmap' => $assetmap];
}

/**
 * Build the version 1 structured-content sidecar while rendering its viewable HTML.
 *
 * Database identifiers are deliberately absent from the sidecar. Blocks are an
 * ordered JSON array and files point at archive members allocated by the exporter.
 *
 * @param stdClass $item Parent item.
 * @param array[] $blocks Export projections.
 * @param string $pagepath Item HTML archive path.
 * @param callable $packagefile File packager.
 * @return array{html:string, assets:string[], manifest:array}
 */
function block_exaport_scorm_build_item_package(
    stdClass $item,
    array $blocks,
    string $pagepath,
    callable $packagefile
): array {
    // Replace database block IDs in archive paths and display-only attributes
    // with export-local sequence numbers before rendering.
    $portableblocks = [];
    foreach ($blocks as $index => $block) {
        $block['blockid'] = $index + 1;
        $portableblocks[] = $block;
    }
    $rendered = block_exaport_scorm_render_item_content($item, $portableblocks, $pagepath, $packagefile);
    $manifestblocks = [];
    foreach ($blocks as $index => $block) {
        $manifestblock = [
            'type' => $block['type'],
            'sortorder' => (int)$block['sortorder'],
            'title' => (string)$block['title'],
            'content' => (string)$block['content'],
            'contentformat' => (int)$block['contentformat'],
            'url' => (string)$block['url'],
            'files' => [],
            'textassets' => [],
        ];
        foreach (['files' => 'files', 'editorfiles' => 'textassets'] as $source => $destination) {
            foreach ($block[$source] as $fileindex => $file) {
                $manifestblock[$destination][] = [
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                    'archivepath' => $rendered['assetmap'][$index][$source][$fileindex],
                ];
            }
        }
        $manifestblocks[] = $manifestblock;
    }
    return [
        'html' => $rendered['html'],
        'assets' => $rendered['assets'],
        'manifest' => [
            'format' => 'exaport-item-content',
            'version' => 1,
            'parent' => ['type' => (string)$item->type, 'intro' => (string)$item->intro],
            'blocks' => $manifestblocks,
        ],
    ];
}
