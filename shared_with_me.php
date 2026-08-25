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
use block_exaport\output\shared_with_me_page;

$courseid = required_param('courseid', PARAM_INT);
$sort     = optional_param('sort', 'name-asc', PARAM_TEXT);

require_login($courseid);

$context = context_system::instance();
require_capability('block/exaport:use', $context);

if (empty($CFG->block_exaport_enable_shared_with_me)) {
    print_error('areaisdisabled', 'block_exaport');
}

$PAGE->set_url('/blocks/exaport/shared_with_me.php', ['courseid' => $courseid]);

block_exaport_print_header("shared_with_me");

block_exaport_require_filter_js();

echo "<div class='block_eportfolio_center'>\n";

$rows = share_overview::get_shared_with_me($USER->id);
$searchcontrols = block_exaport_render_search_and_sort_controls($sort);
$page = new shared_with_me_page($rows, $courseid, $searchcontrols);
echo $PAGE->get_renderer('block_exaport')->render($page);

echo "</div>";
echo block_exaport_wrapperdivend();
echo block_exaport_print_footer();
