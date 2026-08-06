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
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolved sharing detail for a shareable entity (view or category).
 */
class share_info {
    /** @var string[] Fullnames of internally shared users. */
    public array $users = [];
    /** @var string[] Group/cohort names it is internally shared with. */
    public array $groups = [];
    /** @var bool Shared with everyone (internal shareall). */
    public bool $all = false;
    /** @var bool Shared externally via a public URL. */
    public bool $external = false;

    /**
     * Whether the entity is shared in any way.
     *
     * @return bool
     */
    public function is_shared(): bool {
        return (bool)($this->users || $this->groups || $this->all || $this->external);
    }
}
