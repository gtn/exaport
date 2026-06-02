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
 * Output class for the artefact card in folder-navigation mode (Bootstrap layout).
 *
 * Renders block_exaport/artefact_card_folder.
 */
class artefact_card_folder extends artefact_card {

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $item         = $this->item;
        $iconTypeProps = block_exaport_item_icon_type_options($item->type);
        $typelabel    = get_string($item->type, 'block_exaport');

        $introtext = '';
        if (!empty($item->intro)) {
            $intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php',
                context_user::instance($item->userid)->id,
                'block_exaport', 'item_content',
                'portfolio/id/' . $item->userid . '/itemid/' . $item->id);
            $introtext = shorten_text(trim(strip_tags($intro)), 140, true);
        }

        $base         = $this->base_export_data();
        $commentcount = $base['commentcount'];
        $commentlabel = $commentcount . ' ' . block_exaport_get_string($commentcount === 1 ? 'comment' : 'comments');

        return $base + [
            'typeicon'     => '<i class="icon fa fa-' . s($iconTypeProps['iconName']) . ' fa-fw me-1"'
                              . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                              . ' data-bs-title="' . s($typelabel) . '"></i>',
            'ellipsisicon' => block_exaport_fontawesome_icon('ellipsis-vertical', 'solid', 1),
            'viewlabel'    => block_exaport_get_string('view'),
            'viewicon'     => block_exaport_fontawesome_icon('eye', 'regular', 1),
            'canedit'      => $base['isownitem'],
            'editlabel'    => block_exaport_get_string('edit'),
            'candelete'    => $base['isownitem'] && block_exaport_item_is_editable($item->id),
            'deletelabel'  => block_exaport_get_string('delete'),
            'introtext'    => $introtext,
            'compbadge'    => block_exaport_get_item_comp_footer_badge($item),
            'commentlabel' => $commentlabel,
        ];
    }

    /**
     * Return the mustache template name.
     *
     * @return string
     */
    public function get_template_name(): string {
        return 'block_exaport/artefact_card_folder';
    }
}
