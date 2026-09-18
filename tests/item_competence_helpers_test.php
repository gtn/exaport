<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib/item_competence_helpers.php');

/**
 * Tests for item competence persistence helpers.
 *
 * @covers ::block_exaport_normalize_competenceids
 */
final class item_competence_helpers_test extends \advanced_testcase {

    public function test_normalize_competenceids_keeps_unique_positive_ids(): void {
        $this->assertSame([4, 8], block_exaport_normalize_competenceids([4, '8', 4, 0, -2]));
    }
}
