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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();
\block_exaport\vendor::load();

class wordpress_lib {
    /**
     * checks if the WordPress SSO is fully configured
     * @return bool
     */
    static function is_sso_configured() {
        return get_config('block_exaport', 'wp_sso_enabled') && static::get_sso_passphrase() && static::get_sso_url();
    }

    static function get_sso_url() {
        return get_config('block_exaport', 'wp_sso_url')
            // FIXED GTN server
            ?: 'https://lab3.gtn-solutions.com/wp/';
    }

    static function get_sso_passphrase() {
        return get_config('block_exaport', 'wp_sso_passphrase');
    }
}
