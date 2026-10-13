<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Recognise HTML even when the stored MIME type does not match its filename.
 *
 * @param stored_file $file Item attachment.
 * @return bool
 */
function block_exaport_is_html_item_file(stored_file $file): bool {
    $mimetype = strtolower(trim(explode(';', (string)$file->get_mimetype(), 2)[0]));
    $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
    return in_array($mimetype, ['text/html', 'application/xhtml+xml'], true) ||
        in_array($extension, ['html', 'htm', 'xhtml', 'xht'], true);
}

/**
 * Serve an already-authorised item attachment, sanitising outgoing HTML.
 *
 * Keep the stored original intact. Both pluginfile and portfoliofile must use
 * this boundary so changing the download route cannot bypass HTML cleaning.
 * Other files retain Moodle's normal delivery, including PDF byte ranges.
 *
 * @param stored_file $file Attachment whose parent item has been authorised.
 * @param int|string|null $lifetime Moodle file cache lifetime.
 * @param bool $forcedownload Whether to download rather than display inline.
 * @param array $options Options accepted by send_stored_file().
 */
function block_exaport_send_item_file(stored_file $file, $lifetime = null, bool $forcedownload = false,
        array $options = []): void {
    if (block_exaport_is_html_item_file($file) && !$file->is_directory()) {
        $content = clean_text($file->get_content(), FORMAT_HTML);
        send_file($content, $options['filename'] ?? $file->get_filename(), $lifetime, 0, true,
            $forcedownload, 'text/html; charset=utf-8', !empty($options['dontdie']), $options);
        return;
    }

    send_stored_file($file, $lifetime, 0, $forcedownload, $options);
}
