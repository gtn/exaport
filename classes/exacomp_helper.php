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

require_once(__DIR__ . '/../lib/lib.php');

class exacomp_helper {
    /**
     * Has to be called for all descriptor ids, which come from the request, else any descriptor could be used by changing the id.
     * The check itself is done by exacomp.
     */
    public static function require_descriptor_access(array $descriptorids, int $itemid = 0): void {
        // exacomp is optional and older versions of it don't have the check method
        if (block_exaport_check_competence_interaction() && method_exists(\block_exacomp\api::class, 'require_descriptor_access_for_exaport')) {
            \block_exacomp\api::require_descriptor_access_for_exaport($descriptorids, $itemid);
        }
    }
}
