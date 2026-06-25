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

// Legacy entry point: redirect to view_items.php which now handles external category access.
require_once(__DIR__ . '/inc.php');

$rawaccess = optional_param('access', '', PARAM_TEXT);
$access = clean_param($rawaccess, PARAM_TEXT);

redirect(new moodle_url('/blocks/exaport/view_items.php', ['access' => $access]));
