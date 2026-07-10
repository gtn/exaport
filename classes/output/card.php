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

namespace block_exaport\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;

/**
 * Abstract base class for card output objects (Bootstrap layout).
 *
 * Holds the shared constructor and properties common to all card variants,
 * plus the base_icons() helper that returns the shared icon/label fields.
 */
abstract class card implements renderable, templatable {

    /** @var int $courseid */
    protected $courseid;

    /** @var string $type */
    protected $type;

    /**
     * Constructor.
     *
     * @param int    $courseid The course id.
     * @param string $type     Access type, e.g. 'mine' or 'shared'.
     */
    public function __construct(int $courseid, string $type) {
        $this->courseid = $courseid;
        $this->type     = $type;
    }

    /**
     * Return the icon/label fields shared by all card variants.
     * Note: viewicon/viewlabel are intentionally omitted for category cards
     * because the tile itself is already a link; item_card still uses them.
     *
     * @return array
     */
    protected function base_icons(): array {
        return [
            'ellipsisicon' => block_exaport_fontawesome_icon('ellipsis-vertical', 'solid', 1),
            'editicon'     => block_exaport_fontawesome_icon('pen-to-square', 'regular', 1),
            'editlabel'    => block_exaport_get_string('edit'),
            'deleteicon'   => block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon']),
            'deletelabel'  => block_exaport_get_string('delete'),
        ];
    }
}
