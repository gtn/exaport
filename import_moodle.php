<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2006 exabis internet solutions <info@exabis.at>
 *  All rights reserved
 *
 *  You can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This module is based on the Collaborative Moodle Modules from
 *  NCSA Education Division (http://www.ncsa.uiuc.edu)
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */
global $DB, $OUTPUT,$CFG;
require_once dirname(__FILE__) . '/inc.php';
require_once("{$CFG->dirroot}/blocks/exaport/lib/lib.php");


$output="";
$courseid = optional_param("courseid", 0, PARAM_INT);

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);
require_capability('block/exaport:importfrommoodle', $context);

$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}
$url = '/blocks/exabis_competences/import_moodle.php';
$PAGE->set_url($url);
block_exaport_print_header("exportimportmoodleimport");

$modassign=block_exaport_assignmentversion();
if ($modassign->new==1){
	$assignments = $DB->get_records_sql("SELECT s.id, a.id AS aid, s.assignment, s.timemodified, a.name, a.course, c.fullname AS coursename
								FROM {assignsubmission_file} sf
								INNER JOIN {assign_submission} s ON sf.submission=s.id
								INNER JOIN {assign} a ON s.assignment=a.id
								LEFT JOIN {course} c on a.course = c.id
								WHERE s.userid=?", array($USER->id));
}else{
	$assignments = $DB->get_records_sql("SELECT s.id, a.id AS aid, s.assignment, s.timemodified, a.name, a.course, a.assignmenttype, c.fullname AS coursename
								FROM {assignment_submissions} s
								JOIN {assignment} a ON s.assignment=a.id
								LEFT JOIN {course} c on a.course = c.id
								WHERE s.userid=", array($USER->id));
}
$table = new html_table();
$table->head = array(get_string("modulename", $modassign->title), get_string("time"), get_string("file"), get_string("course", "block_exaport"), get_string("action"));
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
        //$context = get_context_instance(CONTEXT_MODULE, $cm->id);
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, $modassign->component, $modassign->filearea, $assignment->id);
//
//
//        foreach ($files as $f) {
//            // $f is an instance of stored_file
//            $filename= $f->get_filename();
//            $link = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_assignment/submission/'.$assignment->id.'/'.$filename);
//
//            echo $link;
//        }

        unset($icons);
        $icons = '';
        foreach ($files as $file) {

            $icon =  new pix_icon(file_mimetype_icon($file->get_mimetype()),'');

            $filename = $file->get_filename();
            if($filename == ".")
                continue;
            
            $link = file_encode_url($CFG->wwwroot . '/pluginfile.php', '/' . $context->id . '/mod_assignment/submission/' . $assignment->id . '/' . $filename);

            $icons .= '<a href="' . $CFG->wwwroot . '/blocks/exaport/import_moodle_add_file.php?courseid=' . $courseid . '&amp;submissionid=' . $assignment->id . '&amp;filename=' . $filename . '&amp;sesskey=' . sesskey() . '&activityid='.$cm->id.'&assignmentid='.$assignment->aid.'">' .
                    get_string("add_this_file", "block_exaport") . '</a>';

            $table->data[] = array($assignment->name, userdate($assignment->timemodified), '<img src="' . $CFG->wwwroot . '/pix/' . $icon->pix . '.png" class="icon" alt="' . $icon->pix . '" />' .
                '<a href="' . $link . '" >' . $filename . '</a><br />', $assignment->coursename, $icons);
        
        }
        
        
    }
    $output .= html_writer::table($table);
    echo $output;
} else {
    echo "<p>" . get_string("nomoodleimportyet", "block_exaport") . "</p>";
}
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
