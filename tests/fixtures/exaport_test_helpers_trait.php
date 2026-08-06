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
 * Shared test helper trait for block_exaport unit tests.
 *
 * Provides `create_category()` and `create_view()` factory methods used
 * across multiple test classes. Centralising them here removes duplication
 * and makes schema changes easier to maintain.
 *
 * @package    block_exaport
 * @copyright  2026 gtn gmbh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_exaport\tests;

/**
 * Trait with shared category/view factory helpers for block_exaport PHPUnit tests.
 */
trait exaport_test_helpers_trait {

    /**
     * Insert a category owned by the given user and return its id.
     *
     * @param \stdClass $user         Category owner.
     * @param string    $name         Display name (default 'Cat').
     * @param int       $pid          Parent category id (0 = root).
     * @param int       $internshare  1 = shared internally (block_exaportcatshar), 0 = not.
     * @param int       $shareall     1 = shared with everyone, 0 = not.
     * @param int       $externaccess 1 = externally accessible, 0 = not.
     * @param string    $hash         External access hash (auto-generated when empty).
     * @return int Category id.
     */
    protected function create_category(\stdClass $user, string $name = 'Cat', int $pid = 0,
                                       int $internshare = 0, int $shareall = 0,
                                       int $externaccess = 0, string $hash = ''): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportcate', (object)[
            'userid'             => $user->id,
            'pid'                => $pid,
            'name'               => $name,
            'timemodified'       => time(),
            'courseid'           => 0,
            'description'        => '',
            'subjid'             => 0,
            'topicid'            => 0,
            'source'             => 0,
            'sourceid'           => 0,
            'isoez'              => 0,
            'sortorder'          => 0,
            'internshare'        => $internshare,
            'shareall'           => $shareall,
            'structure_shareall' => 0,
            'structure_share'    => 0,
            'iconmerge'          => 0,
            'creatorid'          => $user->id,
            'externaccess'       => $externaccess,
            'hash'               => $hash ?: substr(md5(uniqid()), 0, 8),
        ]);
    }

    /**
     * Insert a view owned by the given user and return its id.
     *
     * @param \stdClass $user         View owner.
     * @param int       $shareall     1 = shared with all users, 0 = not.
     * @param int       $externaccess 1 = externally accessible, 0 = not.
     * @return int View id.
     */
    protected function create_view(\stdClass $user, int $shareall = 0, int $externaccess = 0): int {
        global $DB;
        return (int)$DB->insert_record('block_exaportview', (object)[
            'userid'        => $user->id,
            'name'          => 'Test view',
            'intro'         => '',
            'timemodified'  => time(),
            'externaccess'  => $externaccess,
            'externcomment' => 0,
            'shareall'      => $shareall,
            'layout'        => 0,
            'hash'          => substr(md5(uniqid()), 0, 8),
        ]);
    }
}
