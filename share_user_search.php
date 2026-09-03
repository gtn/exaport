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

// Search any moodle user and share a collection, category or item directly with them.
// The whole logic lives in lib/sharelib.php so that it is not triplicated per entity type,
// see block_exaport_sharing_user_search_page().
//
// TODO: item.php does not offer this search box yet - items have no "internal access" flag and
// their share form flow differs, so wiring up the item UI was deferred. This script itself
// already supports entitytype=item.

require_once(__DIR__ . '/inc.php');

$entitytype = optional_param('entitytype', 'view', PARAM_ALPHA);
if (!in_array($entitytype, ['view', 'category', 'item'])) {
    throw new \block_exaport\moodle_exception('error');
}

block_exaport_sharing_user_search_page($entitytype);
