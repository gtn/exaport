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
 * Output class for the category card tile (Bootstrap/folder-mode layout).
 *
 * Renders block_exaport/category_card.
 */
class category_card implements renderable, templatable {

    /** @var \stdClass $category */
    protected $category;

    /** @var int $courseid */
    protected $courseid;

    /** @var string $type */
    protected $type;

    /** @var \stdClass|null $parentcategory */
    protected $parentcategory;

    /**
     * Constructor.
     *
     * @param \stdClass      $category       The category record.
     * @param int            $courseid       The course id.
     * @param string         $type           Access type, e.g. 'mine' or 'shared'.
     * @param \stdClass      $currentcategory The currently active category (unused in context build but kept for API parity).
     * @param \stdClass|null $parentcategory  When non-null, this tile links up to the parent category.
     */
    public function __construct(\stdClass $category, int $courseid, string $type, \stdClass $currentcategory,
                                ?\stdClass $parentcategory = null) {
        $this->category = $category;
        $this->courseid = $courseid;
        $this->type = $type;
        $this->parentcategory = $parentcategory;
    }

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG;

        $isparenttile = (bool)$this->parentcategory;
        $tiletargetid = $isparenttile ? (int)$this->parentcategory->id : (int)$this->category->id;
        $tilename     = $isparenttile ? $this->parentcategory->name : $this->category->name;
        $tileurl      = $isparenttile ? (string)$this->parentcategory->url : (string)$this->category->url;
        $outerclasses = $isparenttile ? 'col mb-4 exaport-folder-category' : 'col col-card-folder mb-4 exaport-folder-category';
        $tilefixedclass = $isparenttile ? 'excomdos_tile_fixed ' : '';

        return [
            'outerclasses'   => $outerclasses,
            'tilenamelower'  => strtolower($tilename),
            'isparenttile'   => $isparenttile,
            'tiletargetid'   => $tiletargetid,
            'tilefixedclass' => $tilefixedclass,
            'tileurl'        => $tileurl,
            'tilename'       => $tilename,
            'typemine'       => ($this->type == 'mine'),
            'editurl'        => $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $this->courseid
                                . '&id=' . $this->category->id . '&action=edit',
            'deleteurl'      => $CFG->wwwroot . '/blocks/exaport/category.php?courseid=' . $this->courseid
                                . '&id=' . $this->category->id . '&action=delete',
            'ellipsisicon'   => block_exaport_fontawesome_icon('ellipsis-vertical', 'solid', 1),
            'viewicon'       => block_exaport_fontawesome_icon('eye', 'regular', 1),
            'editicon'       => block_exaport_fontawesome_icon('pen-to-square', 'regular', 1),
            'deleteicon'     => block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon']),
            'viewlabel'      => block_exaport_get_string('view'),
            'editlabel'      => block_exaport_get_string('edit'),
            'deletelabel'    => block_exaport_get_string('delete'),
            'folderupicon'   => block_exaport_fontawesome_icon('folder-open', 'regular', 1, ['icon', 'fa-fw', 'me-1'], [],
                                    ['data-bs-toggle' => 'tooltip', 'data-bs-placement' => 'top',
                                     'data-bs-title'  => block_exaport_get_string('category_up')], 'up'),
            'categorylabel'  => block_exaport_get_string('category'),
        ];
    }

    /**
     * Return the mustache template name.
     *
     * @return string
     */
    public function get_template_name(): string {
        return 'block_exaport/category_card';
    }
}
