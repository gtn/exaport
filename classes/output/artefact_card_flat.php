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

use renderer_base;

/**
 * Output class for the artefact card in flat/grid mode (Bootstrap layout).
 *
 * Renders block_exaport/view_items_artefact_card_flat.
 */
class artefact_card_flat extends artefact_card {

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $DB, $USER;

        $item            = $this->item;
        $courseid        = $this->courseid;
        $currentcategory = $this->currentcategory;

        $iconTypeProps = block_exaport_item_icon_type_options($item->type);
        $copytoself    = ($currentcategory->id == -1);
        $isownitem     = ($item->userid == $USER->id);
        $ownername     = '';
        if (!$isownitem) {
            $itemuser  = $DB->get_record('user', ['id' => $item->userid]);
            $ownername = $itemuser ? fullname($itemuser) : '';
        }

        return $this->base_export_data() + [
            'typeicon'          => block_exaport_fontawesome_icon($iconTypeProps['iconName'], $iconTypeProps['iconStyle'], 1, ['artefact_icon']),
            'typestring'        => get_string($item->type, 'block_exaport'),
            'copytoself'        => $copytoself,
            'copytoselfurl'     => $CFG->wwwroot . '/blocks/exaport/item.php?courseid=' . $courseid
                                   . '&id=' . $item->id . '&sesskey=' . sesskey() . '&action=copytoself',
            'copytoselftooltip' => get_string('make_it_yours', 'block_exaport'),
            'commenticon'       => block_exaport_fontawesome_icon('comment', 'regular', 1, [], [], [], '', [], [], [], []),
            'projecticon'       => block_exaport_get_item_project_icon($item),
            'compicon'          => block_exaport_get_item_comp_icon($item),
            'typemineorshared'  => in_array($this->type, ['mine', 'shared']),
            'ownername'         => $ownername,
            'usericon'          => block_exaport_fontawesome_icon('circle-user', 'solid', 1),
            'thumburl'          => $CFG->wwwroot . '/blocks/exaport/item_thumb.php?item_id=' . $item->id,
            'categorybadges'    => block_exaport_render_item_category_badges($item),
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
