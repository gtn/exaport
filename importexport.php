<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require_once __DIR__.'/inc.php';

$courseid = optional_param('courseid', 0, PARAM_INT);

block_exaport_require_login($courseid);

$context = context_system::instance();
$url = '/blocks/exaport/importexport.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

global $DB;
$conditions = array("id" => $courseid);
if (! $course = $DB->get_record("course", $conditions) ) {
	error("That's an invalid course id");
}

block_exaport_print_header("importexport");
			 
echo "<br />";

echo "<div class='block_eportfolio_center'>";

$OUTPUT->box( text_to_html(get_string("explainexport","block_exaport")));


if (has_capability('block/exaport:export', $context)) {
	echo "<p ><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/export.png\" height=\"16\" width=\"16\" alt='".get_string("export", "block_exaport")."' /> <a title=\"" . get_string("export","block_exaport") . "\" href=\"{$CFG->wwwroot}/blocks/exaport/export_scorm.php?courseid=".$courseid."\">".get_string("export","block_exaport")."</a></p>";
}

if (has_capability('block/exaport:export', $context)) {
	echo "<p ><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/import.png\" height=\"16\" width=\"16\" alt='".get_string("import", "block_exaport")."' /> <a title=\"" . get_string("import","block_exaport") . "\" href=\"{$CFG->wwwroot}/blocks/exaport/import_file.php?courseid=".$courseid."\">".get_string("import","block_exaport")."</a></p>";
}

if (has_capability('block/exaport:importfrommoodle', $context)) {	
	echo "<p ><img src=\"{$CFG->wwwroot}/blocks/exaport/pix/import.png\" height=\"16\" width=\"16\" alt='".get_string("moodleimport", "block_exaport")."' /> <a title=\"" . get_string("moodleimport","block_exaport") . "\" href=\"{$CFG->wwwroot}/blocks/exaport/import_moodle.php?courseid=".$courseid."\">".get_string("moodleimport","block_exaport")."</a></p>";
}

echo "</div>";
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
