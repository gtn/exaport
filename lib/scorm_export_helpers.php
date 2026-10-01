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
 * Render legacy and normalized structured content for one SCORM item.
 *
 * The callback packages a file under the supplied trusted base and returns its
 * final archive path. This keeps rendering independently testable from ZipArchive.
 *
 * @param stdClass $item Parent item.
 * @param stored_file[] $legacyfiles Legacy item_file files.
 * @param array[] $blocks Normalized structured blocks.
 * @param string $pagepath Generated item page archive path.
 * @param callable $packagefile Callback accepting stored_file and archive base.
 * @return array{html: string, assets: string[]}
 */
function block_exaport_scorm_render_item_content(
    stdClass $item,
    array $legacyfiles,
    array $blocks,
    string $pagepath,
    callable $packagefile
): array {
    $html = '';
    $assets = [];
    $hasstructuredlink = block_exaport_item_content_has_usable_link($blocks);
    $hasstructuredfiles = block_exaport_item_content_has_usable_files($blocks);
    if (!$hasstructuredlink && !empty($item->url) && $item->url !== 'false') {
        $url = clean_param($item->url, PARAM_URL);
        if ($url !== '') {
            $html .= '<div class="legacy-url"><a href="' . s($url) . '"><!--###BOOKMARK_EXT_URL###-->' .
                s($url) . '<!--###BOOKMARK_EXT_URL###--></a></div>' . "\n";
        }
    }
    foreach ($hasstructuredfiles ? [] : $legacyfiles as $file) {
        $asset = $packagefile($file, 'items/' . $item->id . '/legacy');
        $assets[] = $asset;
        $html .= '<div class="legacy-file"><a href="' . s(block_exaport_scorm_relative_url($pagepath, $asset)) .
            '"><!--###BOOKMARK_FILE_URL###-->' . s($file->get_filename()) .
            '<!--###BOOKMARK_FILE_URL###--></a></div>' . "\n";
    }
    foreach ($blocks as $block) {
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
                $html .= '<a class="structured-file" href="' .
                    s(block_exaport_scorm_relative_url($pagepath, $asset)) . '">' . s($file->get_filename()) . '</a>';
            }
        } else if ($block['type'] === 'text') {
            $text = clean_text($block['content'], $block['contentformat']);
            foreach ($block['editorfiles'] as $file) {
                $asset = $packagefile($file,
                    'items/' . $item->id . '/blocks/' . $block['blockid'] . '/editor');
                $assets[] = $asset;
                $reference = ltrim($file->get_filepath(), '/') . $file->get_filename();
                $replacement = block_exaport_scorm_relative_url($pagepath, $asset);
                $text = str_replace('@@PLUGINFILE@@/' . $reference, $replacement, $text);
                $text = str_replace('@@PLUGINFILE@@' . $file->get_filepath() . $file->get_filename(), $replacement, $text);
            }
            $html .= '<div class="structured-text">' . $text . '</div>';
        }
        $html .= '</section>' . "\n";
    }
    return ['html' => $html, 'assets' => $assets];
}
