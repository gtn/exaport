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
 * Checks that regeneration preserves the English and German language catalogues.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class language_catalogue_test extends \advanced_testcase {

    /**
     * Every generated translation must have the same value in its source catalogue.
     */
    public function test_generated_translations_are_preserved_in_total_catalogue(): void {
        global $CFG;

        $langdir = $CFG->dirroot . '/blocks/exaport/lang';
        $total = require($langdir . '/total.php');
        foreach (['de', 'en'] as $index => $language) {
            $strings = (static function(string $path): array {
                $string = [];
                include($path);
                return $string;
            })($langdir . '/' . $language . '/block_exaport.php');

            foreach ($strings as $key => $value) {
                $this->assertArrayHasKey($key, $total, "Missing total.php source for {$language}: {$key}");
                $this->assertArrayHasKey($index, $total[$key], "Missing total.php translation for {$language}: {$key}");
                $this->assertSame($value, $total[$key][$index], "Different total.php translation for {$language}: {$key}");
            }
        }
    }
}
