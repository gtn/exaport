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

global $DB, $OUTPUT, $CFG;
require_once(__DIR__ . '/inc.php');
require_once("{$CFG->dirroot}/blocks/exaport/lib/lib.php");

$output = "";
$courseid = optional_param("courseid", 0, PARAM_INT);

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);
require_capability('block/exaport:importfrommoodle', $context);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}
$url = '/blocks/exaport/import_moodle.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
block_exaport_print_header("importexport", "exportimportmoodleimport");

$modassign = block_exaport_assignmentversion();
$assignments = block_exaport_get_assignments_for_import($modassign);

$table = new html_table();
$table->head = array(get_string("modulename", $modassign->title), get_string("time"), get_string("file"),
    get_string("course", "block_exaport"), get_string("action"));
$table->align = array("LEFT", "LEFT", "LEFT", "LEFT", "RIGHT");
$table->size = array("20%", "20%", "25%", "20%", "15%");
$table->width = "85%";
$table->data = array();

if ($assignments) {
    foreach ($assignments as $assignment) {
        if (!$cm = get_coursemodule_from_instance($modassign->title, $assignment->aid)) {
            print_error('invalidcoursemodule');
        }
        $course = $DB->get_record('course', array("id" => $courseid));
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, $modassign->component, $modassign->filearea, $assignment->submissionid,
            "filename", false);

        foreach ($files as $file) {

            $icon = $OUTPUT->pix_icon(file_file_icon($file), '');

            $filename = $file->get_filename();
            $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out();

            $button = '<a href="' . $CFG->wwwroot . '/blocks/exaport/import_moodle_add_file.php?courseid=' . $courseid .
                '&amp;submissionid=' . $assignment->submissionid . '&amp;fileid=' . $file->get_pathnamehash() . '">' .
                get_string("add_this_file", "block_exaport") . '</a>';

            $table->data[] = array($assignment->name, userdate($assignment->timemodified), $icon .
                ' <a href="' . s($url) . '" >' . $filename . '</a><br />', $assignment->coursename, $button);

        }

    }
    $output .= html_writer::table($table);
    echo $output;
} else {
    echo "<p>" . get_string("nomoodleimportyet", "block_exaport") . "</p>";
}
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
