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

class item_helper {

    /**
     * Build a \block_exaport\share_info object for an item.
     *
     * Resolves user fullnames and group/cohort names from the item's sharing
     * records. This uses the same tables as the existing sharing UI.
     *
     * @param \stdClass $item Item record (must have ->id, ->shareall, ->externaccess).
     * @return \block_exaport\share_info
     */
    public static function build_share_info(\stdClass $item): \block_exaport\share_info {
        return \block_exaport\share_info::resolve('item', $item);
    }
}
