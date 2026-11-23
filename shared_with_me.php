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
use block_exaport\output\my_students_page;
use block_exaport\output\shared_with_me_page;

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);

$context = context_system::instance();
require_capability('block/exaport:use', $context);

if (empty($CFG->block_exaport_enable_shared_with_me)) {
    print_error('areaisdisabled', 'block_exaport');
}

$showstudents = optional_param('mode', '', PARAM_ALPHA) === 'students';
$canviewstudents = block_exaport_user_can_see_artifacts_of_students();
if ($showstudents && !$canviewstudents) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('show_mystudents', 'block_exaport'));
}

$sharesurl = new moodle_url('/blocks/exaport/shared_with_me.php', ['courseid' => $courseid]);
$studentsurl = new moodle_url($sharesurl, ['mode' => 'students']);
$PAGE->set_url($showstudents ? $studentsurl : $sharesurl);

if ($showstudents) {
    $page = new my_students_page(block_exaport_get_students_for_teacher(), $courseid);
} else {
    $copycategoryid = null;
    if (optional_param('action', '', PARAM_ALPHA) === 'copy') {
        if (empty($CFG->block_exaport_copy_category_to_my)) {
            print_error('areaisdisabled', 'block_exaport');
        }
        if (!data_submitted()) {
            throw new moodle_exception('invalidrequest');
        }
        require_sesskey();
        $copycategoryid = required_param('categoryid', PARAM_INT);
    }

    $rows = share_overview::get_shared_with_me($USER->id);
    if ($copycategoryid !== null) {
        // Copy only a Category shared with the current user, as shown in this overview.
        foreach ($rows as $row) {
            if ($row->entity_type === 'category' && (int)$row->id === $copycategoryid) {
                $copy = \block_exaport\copy_category_to_myself($copycategoryid);
                redirect(new moodle_url('/blocks/exaport/view_items.php', ['courseid' => $courseid, 'categoryid' => $copy->id]));
            }
        }
        throw new moodle_exception('invalidcategoryid');
    }

    if ($rows) {
        block_exaport_require_filter_js();
    }
    $searchcontrols = block_exaport_render_share_filter_controls(null, get_string('share_filter_search_titles', 'block_exaport'));
    $page = new shared_with_me_page($rows, $courseid, $searchcontrols);
}

block_exaport_print_header("shared_with_me");

echo "<div class='block_eportfolio_center'>\n";

if ($canviewstudents) {
    $switchurl = $showstudents ? $sharesurl : $studentsurl;
    $switchlabel = get_string($showstudents ? 'shared_with_me' : 'show_mystudents', 'block_exaport');
    echo html_writer::div(html_writer::link($switchurl, $switchlabel), 'd-flex mb-2');
}
echo $PAGE->get_renderer('block_exaport')->render($page);

echo "</div>";
echo block_exaport_wrapperdivend();
echo block_exaport_print_footer();
