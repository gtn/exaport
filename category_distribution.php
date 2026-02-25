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

$courseid = optional_param('courseid', 0, PARAM_INT);

block_exaport_require_login($courseid);

$context = context_system::instance();

// Check capability - require teacher or admin.
require_capability('block/exaport:use', $context);

// Set page properties similar to importexport.php.
$url = '/blocks/exaport/category_distribution.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

global $DB;
$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    throw new moodle_exception('invalidcourseid');
}

block_exaport_print_header("category_distribution");

echo "<br />";

echo "<div class='block_eportfolio_center'>";

echo "<h2>" . get_string('category_distribution', 'block_exaport') . "</h2>";

// Display information about category distribution templates.
echo html_writer::tag('p', get_string('category_distribution_description', 'block_exaport'));

// Get templates for this course.
$course_templates = $DB->get_records('block_exaport_course_templ', ['courseid' => $courseid]);
$view_templates = $DB->get_records('block_exaport_view_templ', ['courseid' => $courseid]);
$distribution_settings = $DB->get_records('block_exaport_templ_dist', ['courseid' => $courseid]);

if ($course_templates || $view_templates) {
    echo html_writer::start_tag('h3');
    echo get_string('templates_for_course', 'block_exaport');
    echo html_writer::end_tag('h3');

    if ($course_templates) {
        echo html_writer::tag('h4', get_string('category_templates', 'block_exaport'));
        echo html_writer::start_tag('ul');
        foreach ($course_templates as $template) {
            echo html_writer::tag('li', s($template->name));
        }
        echo html_writer::end_tag('ul');
    }

    if ($view_templates) {
        echo html_writer::tag('h4', get_string('view_templates', 'block_exaport'));
        echo html_writer::start_tag('ul');
        foreach ($view_templates as $template) {
            echo html_writer::tag('li', s($template->name));
        }
        echo html_writer::end_tag('ul');
    }
} else {
    echo html_writer::tag('p', get_string('no_templates_found', 'block_exaport'));
}

echo "</div>";

echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
