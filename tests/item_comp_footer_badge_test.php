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

    public static function view_editor_badge_provider(): array {
        return ['competences enabled' => [true], 'competences disabled' => [false]];
    }

    /**
     * @dataProvider view_editor_badge_provider
     */
    public function test_view_editor_reuses_card_competence_badge(bool $enabled): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $CFG->block_exaport_enable_interaction_competences = (int)$enabled;
        if ($enabled && !block_exaport_check_competence_interaction()) {
            $this->markTestSkipped('Exacomp is required for the enabled competence case.');
        }

        $owner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($owner);
        $itemid = $DB->insert_record('block_exaportitem', (object)[
            'userid' => $owner->id, 'courseid' => $course->id, 'name' => 'Badge in editor',
            'type' => 'note', 'intro' => '<p>Parent description</p>', 'url' => '', 'attachment' => '',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($enabled) {
            $topicid = $DB->insert_record('block_exacomptopics', (object)['title' => 'Editor topic']);
            $descriptorid = $DB->insert_record('block_exacompdescriptors', (object)['title' => 'Editor competence']);
            $DB->insert_record('block_exacompdescrtopic_mm', (object)[
                'descrid' => $descriptorid, 'topicid' => $topicid,
            ]);
            foreach ([BLOCK_EXACOMP_TYPE_DESCRIPTOR => $descriptorid, BLOCK_EXACOMP_TYPE_TOPIC => $topicid] as $type => $compid) {
                $DB->insert_record('block_exacompcompactiv_mm', (object)[
                    'compid' => $compid, 'activityid' => $itemid, 'comptype' => $type, 'eportfolioitem' => 1,
                ]);
            }
        }
        $viewid = $DB->insert_record('block_exaportview', (object)[
            'userid' => $owner->id, 'creatorid' => $owner->id, 'name' => 'Editor badge view',
            'timemodified' => time(),
        ]);
        $viewblockid = $DB->insert_record('block_exaportviewblock', (object)[
            'viewid' => $viewid, 'positionx' => 1, 'positiony' => 1, 'type' => 'item',
            'itemid' => $itemid, 'width' => 320, 'height' => 240,
        ]);

        $blocks = block_exaport_get_view_blocks((object)['id' => $viewid, 'userid' => $owner->id]);
        $projection = $blocks[$viewblockid]->item;
        $sourceitem = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame(block_exaport_get_item_comp_footer_badge($sourceitem), $projection->compbadge);
        $this->assertStringContainsString('Parent description', $projection->intro);
        $this->assertObjectNotHasProperty('userid', $projection);
        if ($enabled) {
            $this->assertStringContainsString('fa-lightbulb', $projection->compbadge);
            $this->assertStringContainsString('aria-label="' . s(get_string('competences', 'block_exaport')) . '"',
                $projection->compbadge);
            $this->assertStringContainsString('Editor competence', $projection->compbadge);
            $this->assertStringContainsString('Editor topic', $projection->compbadge);
            $this->assertStringContainsString('>2</span>', $projection->compbadge);
        } else {
            $this->assertSame('', $projection->compbadge);
        }
    }
}
