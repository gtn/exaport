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
 * Output class for the artefact card in flat/grid mode (Bootstrap layout).
 *
 * Renders block_exaport/view_items_artefact_card_flat.
 */
class artefact_card_flat implements renderable, templatable {

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
     * @param \stdClass $currentcategory The currently active category (id == -1 means "copy to self" mode).
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
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $USER, $DB;

        $item            = $this->item;
        $courseid        = $this->courseid;
        $type            = $this->type;
        $categoryid      = $this->categoryid;
        $currentcategory = $this->currentcategory;

        $iconTypeProps = block_exaport_item_icon_type_options($item->type);
        $url = $CFG->wwwroot . '/blocks/exaport/shared_item.php?courseid=' . $courseid
               . '&access=portfolio/id/' . $item->userid . '&itemid=' . $item->id;

        // Build category IDs for client-side filtering.
        $itemCatIds = [];
        if (!empty($item->flatcategories) && is_array($item->flatcategories)) {
            foreach ($item->flatcategories as $cat) {
                $itemCatIds[] = (int)$cat->id;
            }
        }

        $copytoself = ($currentcategory->id == -1);
        $isownitem  = ($item->userid == $USER->id);
        $ownername  = '';
        if (!$isownitem) {
            $itemuser  = $DB->get_record('user', ['id' => $item->userid]);
            $ownername = $itemuser ? fullname($itemuser) : '';
        }

        return [
            'itemnamelower'     => strtolower($item->name),
            'catids'            => implode(',', $itemCatIds),
            'timemodified'      => (int)$item->timemodified,
            'itemid'            => (int)$item->id,
            'url'               => $url,
            'itemname'          => $item->name,
            'typeicon'          => block_exaport_fontawesome_icon($iconTypeProps['iconName'], $iconTypeProps['iconStyle'], 1, ['artefact_icon']),
            'typestring'        => get_string($item->type, 'block_exaport'),
            'copytoself'        => $copytoself,
            'copytoselfurl'     => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                                   . '&id=' . $item->id . '&sesskey=' . sesskey() . '&action=copytoself',
            'copytoselftooltip' => get_string('make_it_yours', 'block_exaport'),
            'hascomments'       => ($item->comments > 0),
            'commentcount'      => (int)$item->comments,
            'commenticon'       => block_exaport_fontawesome_icon('comment', 'regular', 1, [], [], [], '', [], [], [], []),
            'projecticon'       => block_exaport_get_item_project_icon($item),
            'compicon'          => block_exaport_get_item_comp_icon($item),
            'typemineorshared'  => in_array($type, ['mine', 'shared']),
            'isownitem'         => $isownitem,
            'editurl'           => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                                   . '&id=' . $item->id . '&action=edit' . (($type == 'shared') ? '&cattype=shared' : ''),
            'editicon'          => block_exaport_fontawesome_icon('pen-to-square', 'regular', 1),
            'deleteurl'         => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                                   . '&id=' . $item->id . '&action=delete&categoryid=' . $categoryid
                                   . (($type == 'shared') ? '&cattype=shared' : ''),
            'trashicon'         => block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon']),
            'ownername'         => $ownername,
            'usericon'          => block_exaport_fontawesome_icon('circle-user', 'solid', 1),
            'thumburl'          => $CFG->wwwroot . '/blocks/exaport/item_thumb.php?item_id=' . $item->id,
            'categorybadges'    => block_exaport_render_item_category_badges($item),
            'dateformatted'     => date('d.m.Y H:i', $item->timemodified),
        ];
    }

    /**
     * Return the mustache template name.
     *
     * @return string
     */
    public function get_template_name(): string {
        return 'block_exaport/view_items_artefact_card_flat';
    }
}
