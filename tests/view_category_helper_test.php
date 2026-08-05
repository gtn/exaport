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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for view_category_helper::sync_view_categories().
 *
 * @package    block_exaport
 * @copyright  2024 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_category_helper_test extends \advanced_testcase {

    /** @var \stdClass The primary test user (view owner). */
    private \stdClass $owner;

    /** @var \stdClass A second user who owns categories that should be rejected. */
    private \stdClass $other;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->owner = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Insert a view owned by the given user and return its id.
     */
    private function create_view(\stdClass $user): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportview', (object)[
            'userid'       => $user->id,
            'name'         => 'Test view',
            'intro'        => '',
            'timemodified' => time(),
            'externaccess' => 0,
            'externcomment'=> 0,
            'shareall'     => 0,
            'layout'       => 0,
        ]);
    }

    /**
     * Insert a category owned by the given user and return its id.
     */
    private function create_category(\stdClass $user, string $name = 'Cat'): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportcate', (object)[
            'userid'           => $user->id,
            'pid'              => 0,
            'name'             => $name,
            'timemodified'     => time(),
            'courseid'         => 0,
            'description'      => '',
            'subjid'           => 0,
            'topicid'          => 0,
            'source'           => 0,
            'sourceid'         => 0,
            'isoez'            => 0,
            'sortorder'        => 0,
            'internshare'      => 0,
            'shareall'         => 0,
            'structure_shareall' => 0,
            'structure_share'  => 0,
            'iconmerge'        => 0,
            'creatorid'        => $user->id,
        ]);
    }

    /**
     * Return the cateid values currently stored for the given view.
     *
     * @return int[]
     */
    private function get_assigned_cateids(int $viewid): array {
        global $DB;
        return array_map('intval',
            $DB->get_fieldset_select('block_exaportviewcate', 'cateid', 'viewid = ?', [$viewid])
        );
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * Assigning own categories persists exactly those rows.
     */
    public function test_own_categories_are_persisted(): void {
        $viewid = $this->create_view($this->owner);
        $cat1   = $this->create_category($this->owner, 'A');
        $cat2   = $this->create_category($this->owner, 'B');

        view_category_helper::sync_view_categories($viewid, [$cat1, $cat2]);

        $assigned = $this->get_assigned_cateids($viewid);
        sort($assigned);
        $this->assertSame([$cat1, $cat2], $assigned);
    }

    /**
     * A category id owned by a different user is dropped silently.
     */
    public function test_foreign_category_is_dropped(): void {
        $viewid      = $this->create_view($this->owner);
        $foreigncate = $this->create_category($this->other, 'Foreign');

        view_category_helper::sync_view_categories($viewid, [$foreigncate]);

        global $DB;
        $this->assertFalse(
            $DB->record_exists('block_exaportviewcate', ['viewid' => $viewid, 'cateid' => $foreigncate]),
            'A foreign category id must not appear in block_exaportviewcate.'
        );
    }

    /**
     * A mixed list (some own, some foreign) keeps only the owned ones.
     */
    public function test_mixed_list_keeps_only_owned(): void {
        $viewid  = $this->create_view($this->owner);
        $owncate = $this->create_category($this->owner, 'Own');
        $foreign = $this->create_category($this->other,  'Foreign');

        view_category_helper::sync_view_categories($viewid, [$owncate, $foreign]);

        $assigned = $this->get_assigned_cateids($viewid);
        $this->assertContains($owncate, $assigned);
        $this->assertNotContains($foreign, $assigned);
        $this->assertCount(1, $assigned);
    }

    /**
     * Re-syncing with a different set replaces the previous assignments.
     */
    public function test_resync_replaces_previous_assignments(): void {
        $viewid = $this->create_view($this->owner);
        $cat1   = $this->create_category($this->owner, 'First');
        $cat2   = $this->create_category($this->owner, 'Second');

        view_category_helper::sync_view_categories($viewid, [$cat1]);
        $this->assertSame([$cat1], $this->get_assigned_cateids($viewid));

        view_category_helper::sync_view_categories($viewid, [$cat2]);
        $assigned = $this->get_assigned_cateids($viewid);
        $this->assertNotContains($cat1, $assigned);
        $this->assertContains($cat2, $assigned);
    }

    /**
     * Syncing with an empty array removes all assignments.
     */
    public function test_empty_array_removes_all_assignments(): void {
        $viewid = $this->create_view($this->owner);
        $cat    = $this->create_category($this->owner, 'Cat');

        view_category_helper::sync_view_categories($viewid, [$cat]);
        $this->assertCount(1, $this->get_assigned_cateids($viewid));

        view_category_helper::sync_view_categories($viewid, []);
        $this->assertEmpty($this->get_assigned_cateids($viewid));
    }

    /**
     * Duplicate ids in the input produce a single row.
     */
    public function test_duplicate_ids_produce_single_row(): void {
        $viewid = $this->create_view($this->owner);
        $cat    = $this->create_category($this->owner, 'Cat');

        view_category_helper::sync_view_categories($viewid, [$cat, $cat, $cat]);

        $assigned = $this->get_assigned_cateids($viewid);
        $this->assertCount(1, $assigned);
        $this->assertContains($cat, $assigned);
    }

    /**
     * Calling sync with a non-existent view id does nothing (no exception).
     */
    public function test_nonexistent_view_does_nothing(): void {
        $cat = $this->create_category($this->owner, 'Cat');

        // Should return without throwing or inserting rows.
        view_category_helper::sync_view_categories(999999, [$cat]);

        global $DB;
        $this->assertFalse($DB->record_exists('block_exaportviewcate', ['viewid' => 999999]));
    }
}
