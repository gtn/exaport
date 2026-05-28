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

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param("action", "", PARAM_ALPHA);
$itemid = optional_param('itemid', 0, PARAM_INT);
$sharecourseid = optional_param('sharecourseid', '', PARAM_INT); // Array of integer.

$backtype = optional_param('backtype', 'all', PARAM_ALPHA);
$backtype = block_exaport_check_item_type($backtype, true);

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);
require_capability('block/exaport:shareintern', $context);

global $DB;
$conditions = array("id" => $courseid);

if (!$course = $DB->get_record("course", $conditions)) {
    print_error("invalidcourseid", "block_exaport");
}

if (!block_exaport_feature_enabled('copy_to_course')) {
    print_error("Copy to course not enabled");
}

global $DB;
$params = array($USER->id, $itemid, 'file');
// Get the bookmark if it is mine.
$item = $DB->get_record_sql("select *" .
    " from {block_exaportitem i}" .
    " where i.userid = ? and i.id=? AND i.type=?");

if (!$item) {
    print_error("bookmarknotfound", "block_exaport", 'view.php?courseid=' . $courseid);
}

$courses = exaport_get_shareable_courses();

if ($sharecourseid) {
    if (!isset($courses[$sharecourseid])) {
        print_error('not allowed to share to this course');
    }

    $dir = make_upload_directory($sharecourseid . '/students');

    $filenamenew = clean_filename($USER->lastname . '_' . $USER->firstname) . '_' . $item->attachment;
    die('todo: block_exaport_file_area_name');
    copy($CFG->dataroot . "/" . block_exaport_file_area_name($item) . "/" . $item->attachment,
        $dir . '/' . $filenamenew);

    block_exaport_print_header("bookmarks" . block_exaport_get_plural_item_type($backtype), "share");

    echo '<div style="text-align: center; font-weight: bold; padding: 20px;">';
    echo get_string('filecopiedtocourse',
        'block_exaport',
        (object)array('coursename' => $courses[$sharecourseid]['fullname'], 'filename' => $filenamenew));
    echo '</div>';

    redirect($CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $courseid . "&type=" . $backtype);
    echo block_exaport_wrapperdivend();
    print_footer();
    exit;
}

block_exaport_print_header("bookmarks" . block_exaport_get_plural_item_type($backtype), "share");

echo "<div class='block_eportfolio_center'>";
echo '<div style="padding: 20px; font-weight: bold;">' . get_string("copy") . ' ' . $item->name . '</div>';

if (!$courses) {
    echo 'error, no courses';
} else {
    foreach ($courses as $course) {
        echo "<a href=\"{$CFG->wwwroot}/blocks/exaport/copy_item_to_course.php?'.
            'courseid={$courseid}&amp;itemid={$itemid}&amp;backtype={$backtype}&amp;sharecourseid={$course['id']}\">" .
            $course['fullname'] . '</a><br />';
    }
}

echo "<br /><a href=\"{$CFG->wwwroot}/blocks/exaport/view_items.php?'.
          'courseid={$courseid}&amp;backtype={$backtype}\">" . get_string("back", "block_exaport") . "</a><br /><br />";
echo '</div';
echo block_exaport_wrapperdivend();
print_footer();
