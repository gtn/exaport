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

defined('MOODLE_INTERNAL') || die;
require_once(__DIR__ . '/inc.php');

class block_exaport_renderer extends plugin_renderer_base {

    /**
     * Render a category card tile (Bootstrap/folder-mode layout).
     *
     * @param \block_exaport\output\category_card $card
     * @return string HTML
     */
    public function render_category_card(\block_exaport\output\category_card $card): string {
        return $this->render_from_template(
            'block_exaport/view_items_category_card',
            $card->export_for_template($this)
        );
    }

    /**
     * Render an artefact card in folder-navigation mode (Bootstrap layout).
     *
     * @param \block_exaport\output\artefact_card_folder $card
     * @return string HTML
     */
    public function render_artefact_card_folder(\block_exaport\output\artefact_card_folder $card): string {
        return $this->render_from_template(
            'block_exaport/view_items_artefact_card_folder',
            $card->export_for_template($this)
        );
    }

    /**
     * Render an artefact card in flat/grid mode (Bootstrap layout).
     *
     * @param \block_exaport\output\artefact_card_flat $card
     * @return string HTML
     */
    public function render_artefact_card_flat(\block_exaport\output\artefact_card_flat $card): string {
        return $this->render_from_template(
            'block_exaport/view_items_artefact_card_flat',
            $card->export_for_template($this)
        );
    }

    /**
     * in moodle33 pix_url was renamed to image_url
     */
    public function image_url($imagename, $component = 'moodle') {
        if (method_exists(get_parent_class($this), 'image_url')) {
            // return call_user_func_array(['parent::class', 'image_url'], func_get_args());
            return parent::image_url($imagename, $component);
        } else {
            // return call_user_func_array(['parent::class', 'pix_url'], func_get_args());
            return parent::pix_url($imagename, $component);
        }
    }


    public function get_theme_dir() {
        return $this->get_theme_config()->dir;
    }

    public function get_theme_config() {
        return $this->page->theme;
    }
}

