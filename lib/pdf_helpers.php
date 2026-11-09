<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Embed an authorised Exaport image without an unauthenticated HTTP request.
 *
 * @param string $source Pluginfile URL from the rendered collection.
 * @return string Image data URL, or an empty string when unavailable.
 */
function block_exaport_pdf_image_data_url(string $source): string {
    try {
        $url = new moodle_url($source);
        $base = new moodle_url('/pluginfile.php');
        // Never fetch remote URLs or forward the viewer's session to an image server.
        if ($url->get_scheme() !== $base->get_scheme() || $url->get_host() !== $base->get_host()
                || $url->get_port() !== $base->get_port()) {
            return '';
        }

        if ($url->get_path() === $base->get_path()) {
            $path = $url->get_param('file');
        } else if (str_starts_with($url->get_path(), $base->get_path() . '/')) {
            $path = rawurldecode(substr($url->get_path(), strlen($base->get_path())));
        } else {
            return '';
        }
        if (!is_string($path)) {
            return '';
        }
        $args = explode('/', ltrim($path, '/'));
        if (count($args) < 4 || !ctype_digit($args[0]) || $args[1] !== 'block_exaport') {
            return '';
        }
        $contextid = (int)array_shift($args);
        array_shift($args); // Component has already been checked.
        $filearea = array_shift($args);
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            return '';
        }

        // Use the real viewer and the normal file permissions, including revocations.
        $file = block_exaport_get_authorized_file($context, $filearea, $args);
        if (!$file || $file->is_directory() || !$file->is_valid_image()) {
            return '';
        }
        return 'data:' . $file->get_mimetype() . ';base64,' . base64_encode($file->get_content());
    } catch (moodle_exception $e) {
        // A missing or newly private image should not prevent exporting the collection.
        return '';
    }
}
