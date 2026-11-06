<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Existing view layout IDs, grouped by column count, with their thumbnail names.
 *
 * @package block_exaport
 * @copyright 2026 GTN - Global Training Network GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_layout {
    /**
     * Layout IDs and thumbnail names, grouped by column count.
     *
     * @var array
     */
    public const GROUPS = [
        1 => [1 => '100'],
        2 => [2 => '50-50', 3 => '67-33', 4 => '33-67'],
        3 => [5 => '33-33-33', 6 => '25-50-25', 7 => '15-70-15'],
        4 => [8 => '25-25-25-25', 9 => '20-30-30-20'],
        5 => [10 => '20-20-20-20-20'],
    ];

    /**
     * Return the number of columns in a supported layout.
     *
     * @param int $layout Layout ID
     * @return int Number of columns
     */
    public static function columns(int $layout): int {
        foreach (self::GROUPS as $columns => $layouts) {
            if (isset($layouts[$layout])) {
                return $columns;
            }
        }
        throw new \invalid_parameter_exception('Unknown view layout.');
    }
}
