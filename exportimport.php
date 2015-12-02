<?php 
/***************************************************************
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
***************************************************************/

require_once __DIR__.'/inc.php';

$courseid = optional_param('courseid', 0, PARAM_INT);

block_exaport_require_login($courseid);

$context = context_system::instance();
$url = '/blocks/exabis_competences/exportimport.php';
$PAGE->set_url($url);

global $DB;
$conditions = array("id" => $courseid);
if (! $course = $DB->get_record("course", $conditions) ) {
	error("That's an invalid course id");
}

block_exaport_print_header("exportimport");
			 
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
