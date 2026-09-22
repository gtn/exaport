<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Static checks for strings used by the shared sharing interface.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sharing_language_catalogue_test extends \advanced_testcase {

    /**
     * Every plugin string referenced by the sharing template and AMD module must exist and stay aligned.
     */
    public function test_sharing_interface_strings_exist_in_all_catalogues(): void {
        global $CFG;

        $plugindir = $CFG->dirroot . '/blocks/exaport';
        $keys = $this->get_sharing_interface_keys($plugindir);
        $english = $this->load_language_file($plugindir . '/lang/en/block_exaport.php');
        $german = $this->load_language_file($plugindir . '/lang/de/block_exaport.php');
        $total = require($plugindir . '/lang/total.php');

        $this->assertNotEmpty($keys, 'No sharing-interface language keys were discovered.');
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $english, "Missing English sharing string: {$key}");
            $this->assertArrayHasKey($key, $german, "Missing German sharing string: {$key}");
            $this->assertArrayHasKey($key, $total, "Missing total.php sharing string: {$key}");
            $this->assertSame($german[$key], $total[$key][0], "German total.php value differs for: {$key}");
            $this->assertSame($english[$key], $total[$key][1], "English total.php value differs for: {$key}");
        }
    }

    /**
     * Extract block_exaport string keys from the sharing template and AMD source.
     *
     * @param string $plugindir Absolute plugin directory.
     * @return string[]
     */
    private function get_sharing_interface_keys(string $plugindir): array {
        $template = file_get_contents($plugindir . '/templates/sharing_form.mustache');
        preg_match_all('/\{\{#str\}\}\s*([a-zA-Z0-9_:.-]+)\s*,\s*block_exaport\s*\{\{\/str\}\}/',
            $template, $templatematches);

        $javascript = file_get_contents($plugindir . '/amd/src/sharing_form.js');
        preg_match_all("/\{key:\s*'([^']+)',\s*component:\s*'block_exaport'\}/", $javascript, $jsmatches);

        $keys = array_values(array_unique(array_merge($templatematches[1], $jsmatches[1])));
        sort($keys);
        return $keys;
    }

    /**
     * Load a standard Moodle language file into an isolated local string array.
     *
     * @param string $path Absolute language-file path.
     * @return array
     */
    private function load_language_file(string $path): array {
        $string = [];
        include($path);
        return $string;
    }
}
