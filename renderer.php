<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

defined('MOODLE_INTERNAL') || die;
require_once __DIR__.'/inc.php';

use block_exaport\globals as g;

class block_exaport_renderer extends plugin_renderer_base {
	/**
	 * in moodle33 pix_url was renamed to image_url
	 */
	public function image_url($imagename, $component = 'moodle') {
		if (method_exists(get_parent_class($this), 'image_url')) {
			return call_user_func_array(['parent', 'image_url'], func_get_args());
		} else {
			return call_user_func_array(['parent', 'pix_url'], func_get_args());
		}
	}
}
