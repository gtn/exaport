<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/exaport/lib/lib.php');
require_once($CFG->dirroot . '/blocks/exaport/locallib.php');

/**
 * Tests the item competence footer badge helper.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_comp_footer_badge_test extends \advanced_testcase {

    public function test_category_badges_helper_is_available_to_card_renderers(): void {
        $item = (object)[
            'flatcategories' => [
                (object)['name' => 'Parent / Child'],
            ],
        ];

        $html = block_exaport_render_item_category_badges($item);

        $this->assertStringContainsString('eportfolio-categories', $html);
        $this->assertStringContainsString('data-bs-title="Parent / Child"', $html);
        $this->assertStringContainsString('>Child</span>', $html);
        $this->assertSame('', block_exaport_render_item_category_badges((object)[]));
    }

    public function test_badge_is_available_and_empty_when_competence_interaction_is_disabled(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $CFG->block_exaport_enable_interaction_competences = 0;

        $this->assertTrue(function_exists('block_exaport_get_item_comp_footer_badge'));
        $this->assertSame('', block_exaport_get_item_comp_footer_badge((object)['id' => 1]));
    }
}
