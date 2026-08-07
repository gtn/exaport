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

require_once(__DIR__ . '/inc.php');

use function block_exaport\common\print_error;
use block_exaport\share_overview;
use block_exaport\output\my_shares_page;

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);

$context = context_system::instance();
require_capability('block/exaport:use', $context);

if (empty($CFG->block_exaport_enable_my_shares)) {
    print_error('areaisdisabled', 'block_exaport');
}

$PAGE->set_url('/blocks/exaport/my_shares.php', ['courseid' => $courseid]);

block_exaport_print_header("my_shares");

echo "<div class='block_eportfolio_center'>\n";

$rows = share_overview::get_my_shares($USER->id);
$page = new my_shares_page($rows, $courseid);
echo $PAGE->get_renderer('block_exaport')->render($page);

echo "</div>";
echo block_exaport_wrapperdivend();
echo block_exaport_print_footer();
