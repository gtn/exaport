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
use renderer_base;

/**
 * Output class for the item card (Bootstrap layout).
 *
 * Used for both folder-navigation mode and flat/grid mode.
 * Pass $showcategories = true to render category badge chips in the card.
 * Renders block_exaport/item_card.
 */
class item_card extends card {

    /** @var \stdClass $item */
    protected $item;

    /** @var int $categoryid */
    protected $categoryid;

    /** @var \stdClass $currentcategory */
    protected $currentcategory;

    /** @var bool $showcategories */
    protected bool $showcategories;

    /**
     * Constructor.
     *
     * @param \stdClass $item            The artefact/item record.
     * @param int       $courseid        The course id.
     * @param string    $type            Access type, e.g. 'mine' or 'shared'.
     * @param int       $categoryid      The current category id (used for delete URL).
     * @param \stdClass $currentcategory The currently active category.
     * @param bool      $showcategories  Whether to show category badge chips.
     */
    public function __construct(\stdClass $item, int $courseid, string $type, int $categoryid,
                                \stdClass $currentcategory, bool $showcategories = false) {
        parent::__construct($courseid, $type);
        $this->item            = $item;
        $this->categoryid      = $categoryid;
        $this->currentcategory = $currentcategory;
        $this->showcategories  = $showcategories;
    }

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
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

        $iconTypeProps = block_exaport_item_icon_type_options($item->type);
        $typelabel     = get_string($item->type, 'block_exaport');

        $introtext = '';
        if (!empty($item->intro)) {
            $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php',
                context_user::instance($item->userid)->id,
                'block_exaport', 'item_content',
                'portfolio/id/' . $item->userid . '/itemid/' . $item->id);
            $introtext = shorten_text(trim(strip_tags($intro)), 140, true);
        }

        $commentlabel = $commentcount . ' ' . block_exaport_get_string($commentcount === 1 ? 'comment' : 'comments');

        return $this->base_icons() + [
            'itemnamelower' => strtolower($item->name),
            'catids'        => implode(',', $itemcatids),
            'timemodified'  => (int)$item->timemodified,
            'itemid'        => (int)$item->id,
            'url'           => $url,
            'itemname'      => $item->name,
            'isownitem'     => $isownitem,
            'editurl'       => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                               . '&id=' . $item->id . '&action=edit' . $cattype,
            'deleteurl'     => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                               . '&id=' . $item->id . '&action=delete&categoryid=' . $categoryid . $cattype,
            'hascomments'   => $commentcount > 0,
            'commentcount'  => $commentcount,
            'dateformatted' => date('d.m.Y H:i', $item->timemodified),
            'typeicon'      => '<i class="icon fa fa-' . s($iconTypeProps['iconName']) . ' fa-fw me-1"'
                               . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                               . ' data-bs-title="' . s($typelabel) . '"></i>',
            'canedit'       => $isownitem,
            'candelete'     => $isownitem && block_exaport_item_is_editable($item->id),
            'introtext'     => $introtext,
            'compbadge'     => block_exaport_get_item_comp_footer_badge($item),
            'commentlabel'  => $commentlabel,
        ] + ($this->showcategories ? [
            'categorybadges' => block_exaport_render_item_category_badges($this->item),
        ] : []);
    }
}
