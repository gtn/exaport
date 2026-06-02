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
 * Extends artefact_card_folder and adds category badge chips shown only in flat mode.
 * Rendered via block_exaport/artefact_card_folder template (see renderer.php).
 */
class artefact_card_flat extends artefact_card_folder {

    /**
     * Export the data required by the mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return parent::export_for_template($output) + [
            'categorybadges' => block_exaport_render_item_category_badges($this->item),
        ];
    }
}
