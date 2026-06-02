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
 * Abstract base class for artefact card output objects (Bootstrap layout).
 *
 * Holds the shared constructor, properties, and data that is common to
 * both the flat/grid card and the folder-navigation card.
 */
abstract class artefact_card implements renderable, templatable {

    /** @var \stdClass $item */
    protected $item;

    /** @var int $courseid */
    protected $courseid;

    /** @var string $type */
    protected $type;

    /** @var int $categoryid */
    protected $categoryid;

    /** @var \stdClass $currentcategory */
    protected $currentcategory;

    /**
     * Constructor.
     *
     * @param \stdClass $item            The artefact/item record.
     * @param int       $courseid        The course id.
     * @param string    $type            Access type, e.g. 'mine' or 'shared'.
     * @param int       $categoryid      The current category id (used for delete URL).
     * @param \stdClass $currentcategory The currently active category.
     */
    public function __construct(\stdClass $item, int $courseid, string $type, int $categoryid,
                                \stdClass $currentcategory) {
        $this->item            = $item;
        $this->courseid        = $courseid;
        $this->type            = $type;
        $this->categoryid      = $categoryid;
        $this->currentcategory = $currentcategory;
    }

    /**
     * Return the data fields shared by all artefact card variants.
     *
     * @return array
     */
    protected function base_export_data(): array {
        global $CFG, $USER;

        $item       = $this->item;
        $courseid   = $this->courseid;
        $type       = $this->type;
        $categoryid = $this->categoryid;

        $url = $CFG->wwwroot . '/blocks/exaport/shared_item.php?courseid=' . $courseid
               . '&access=portfolio/id/' . $item->userid . '&itemid=' . $item->id;

        // Build category IDs for client-side filtering.
        $itemcatids = [];
        if (!empty($item->flatcategories) && is_array($item->flatcategories)) {
            foreach ($item->flatcategories as $cat) {
                $itemcatids[] = (int)$cat->id;
            }
        }

        $cattype      = ($type == 'shared') ? '&cattype=shared' : '';
        $isownitem    = ($item->userid == $USER->id);
        $commentcount = (int)($item->comments ?? 0);

        return [
            'itemnamelower' => strtolower($item->name),
            'catids'        => implode(',', $itemcatids),
            'timemodified'  => (int)$item->timemodified,
            'itemid'        => (int)$item->id,
            'url'           => $url,
            'itemname'      => $item->name,
            'isownitem'     => $isownitem,
            'editurl'       => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                               . '&id=' . $item->id . '&action=edit' . $cattype,
            'editicon'      => block_exaport_fontawesome_icon('pen-to-square', 'regular', 1),
            'deleteurl'     => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                               . '&id=' . $item->id . '&action=delete&categoryid=' . $categoryid . $cattype,
            'deleteicon'    => block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon']),
            'hascomments'   => $commentcount > 0,
            'commentcount'  => $commentcount,
            'dateformatted' => date('d.m.Y H:i', $item->timemodified),
        ];
    }
}
