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

use context_user;
use renderable;
use renderer_base;
use templatable;

/**
 * Output class for the artefact card in folder-navigation mode (Bootstrap layout).
 *
 * Renders block_exaport/view_items_artefact_card_folder.
 */
class artefact_card_folder implements renderable, templatable {

    /** @var \stdClass $item */
    protected $item;

    /** @var int $courseid */
    protected $courseid;

    /** @var string $type */
    protected $type;

    /** @var int $categoryid */
    protected $categoryid;

    /**
     * Constructor.
     *
     * @param \stdClass $item        The artefact/item record.
     * @param int       $courseid    The course id.
     * @param string    $type        Access type, e.g. 'mine' or 'shared'.
     * @param int       $categoryid  The current category id (used for delete URL).
     * @param \stdClass $currentcategory The currently active category (kept for API parity).
     */
    public function __construct(\stdClass $item, int $courseid, string $type, int $categoryid,
                                \stdClass $currentcategory) {
        $this->item       = $item;
        $this->courseid   = $courseid;
        $this->type       = $type;
        $this->categoryid = $categoryid;
    }

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $USER;

        $item           = $this->item;
        $courseid       = $this->courseid;
        $type           = $this->type;
        $categoryid     = $this->categoryid;

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

        $typelabel = get_string($item->type, 'block_exaport');
        $introtext = '';
        if (!empty($item->intro)) {
            $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php',
                context_user::instance($item->userid)->id,
                'block_exaport', 'item_content',
                'portfolio/id/' . $item->userid . '/itemid/' . $item->id);
            $introtext = shorten_text(trim(strip_tags($intro)), 140, true);
        }

        $cattype      = ($type == 'shared') ? '&cattype=shared' : '';
        $isownitem    = ($item->userid == $USER->id);
        $commentcount = (int)($item->comments ?? 0);
        $commentlabel = $commentcount . ' ' . block_exaport_get_string($commentcount === 1 ? 'comment' : 'comments');

        return [
            'url'           => $url,
            'itemnamelower' => strtolower($item->name),
            'timemodified'  => (int)$item->timemodified,
            'catids'        => implode(',', $itemCatIds),
            'itemid'        => (int)$item->id,
            'typeicon'      => '<i class="icon fa fa-' . s($iconTypeProps['iconName']) . ' fa-fw me-1"'
                               . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                               . ' data-bs-title="' . s($typelabel) . '"></i>',
            'itemname'      => $item->name,
            'ellipsisicon'  => block_exaport_fontawesome_icon('ellipsis-vertical', 'solid', 1),
            'viewlabel'     => block_exaport_get_string('view'),
            'viewicon'      => block_exaport_fontawesome_icon('eye', 'regular', 1),
            'canedit'       => $isownitem,
            'editurl'       => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                               . '&id=' . $item->id . '&action=edit' . $cattype,
            'editicon'      => block_exaport_fontawesome_icon('pen-to-square', 'regular', 1),
            'editlabel'     => block_exaport_get_string('edit'),
            'candelete'     => $isownitem && block_exaport_item_is_editable($item->id),
            'deleteurl'     => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                               . '&id=' . $item->id . '&action=delete&categoryid=' . $categoryid . $cattype,
            'deleteicon'    => block_exaport_fontawesome_icon('trash-can', 'regular', 1, [], [], [], '', [], [], [], ['exaport-remove-icon']),
            'deletelabel'   => block_exaport_get_string('delete'),
            'introtext'     => $introtext,
            'dateformatted' => date('d.m.Y H:i', $item->timemodified),
            'compbadge'     => block_exaport_get_item_comp_footer_badge($item),
            'hascomments'   => $commentcount > 0,
            'commentcount'  => $commentcount,
            'commentlabel'  => $commentlabel,
        ];
    }

    /**
     * Return the mustache template name.
     *
     * @return string
     */
    public function get_template_name(): string {
        return 'block_exaport/view_items_artefact_card_folder';
    }
}
