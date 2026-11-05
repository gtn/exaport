<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/** Existing view layout IDs, grouped by column count, with their thumbnail names. */
final class view_layout {
    public const GROUPS = [
        1 => [1 => '100'],
        2 => [2 => '50-50', 3 => '67-33', 4 => '33-67'],
        3 => [5 => '33-33-33', 6 => '25-50-25', 7 => '15-70-15'],
        4 => [8 => '25-25-25-25', 9 => '20-30-30-20'],
        5 => [10 => '20-20-20-20-20'],
    ];

    /** Return the number of columns in a supported layout. */
    public static function columns(int $layout): int {
        foreach (self::GROUPS as $columns => $layouts) {
            if (isset($layouts[$layout])) {
                return $columns;
            }
        }
        throw new \invalid_parameter_exception('Unknown view layout.');
    }
}
