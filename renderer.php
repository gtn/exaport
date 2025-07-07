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

use block_exaport\globals as g;

class block_exaport_renderer extends plugin_renderer_base {
    /**
     * in moodle33 pix_url was renamed to image_url
     */
    public function image_url($imagename, $component = 'moodle') {
        if (method_exists(get_parent_class($this), 'image_url')) {
            // return call_user_func_array(['parent', 'image_url'], func_get_args());
            return parent::image_url($imagename, $component);
        } else {
            // return call_user_func_array(['parent', 'pix_url'], func_get_args());
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

