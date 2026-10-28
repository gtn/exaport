<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Regression tests for the explicitly nullable signatures (PHP 8.4 deprecation fix).
 *
 * Pins the behaviour for omitted and null arguments, so the type declarations
 * can change without altering what callers get back.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');

final class nullable_compat_test extends \advanced_testcase {

    public function test_url_create_without_params_has_no_query(): void {
        $url = \block_exaport\common\url::create('/blocks/exaport/view.php');
        $this->assertStringNotContainsString('?', $url->out(false));
    }

    public function test_url_params_null_returns_current_params(): void {
        $url = \block_exaport\common\url::create('/x.php', ['a' => 1]);
        $this->assertEquals(['a' => 1], $url->params(null));
    }

    public function test_url_copy_null_keeps_params(): void {
        $url = \block_exaport\common\url::create('/x.php', ['a' => 1]);
        $this->assertEquals(['a' => 1], $url->copy(null)->params());
    }

    public function test_url_copy_null_value_removes_param(): void {
        $url = \block_exaport\common\url::create('/x.php', ['a' => 1, 'b' => 2]);
        $this->assertEquals(['a' => 1], $url->copy(['b' => null])->params());
    }

    public function test_parse_sort_without_default_falls_back_to_first_column(): void {
        $this->assertEquals(['name', 'asc'], block_exaport_parse_sort('unknown-desc', ['name', 'date']));
    }

    public function test_parse_sort_uses_given_default(): void {
        $this->assertEquals(['date', 'desc'], block_exaport_parse_sort('unknown', ['name'], ['date', 'desc']));
    }

    public function test_parse_sort_valid_column_keeps_order(): void {
        $this->assertEquals(['name', 'desc'], block_exaport_parse_sort('name-desc', ['name', 'date']));
    }

    public function test_minixml_from_array_preserves_scalar_and_nested_values(): void {
        require_once(__DIR__ . '/../lib/minixml.inc.php');
        $deprecations = [];
        set_error_handler(static function($severity, $message) use (&$deprecations) {
            $deprecations[] = $message;
            return true;
        }, E_DEPRECATED);
        try {
            $document = new \MiniXMLDoc();
            $data = ['portfolio' => ['title' => 'My portfolio', 'item' => ['First', 'Second']]];
            $document->fromArray($data, ['attributes' => []]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);

        $this->assertXmlStringEqualsXmlString(
            '<portfolio><title>My portfolio</title><item>First</item><item>Second</item></portfolio>',
            $document->toString());
    }
}
