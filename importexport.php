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
require_once(__DIR__ . '/lib/resumelib.php');
require_once(__DIR__ . '/blockmediafunc.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

block_exaport_require_login($courseid);

$context = context_system::instance();

// WordPress integration
$wpIntegration = null;
$wpAction = optional_param('wpAction', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_RAW);
if (block_exaport_wpsso_configured()
    || ($wpAction && $action == 'requestPassphrase') // not configured, but the request to get passphrase
    || ($wpAction && $action == 'removePassphrase') // remove the passphrase
    || ($wpAction && $action == 'testPassphrase') // test the passphrase
) {
    // needed to render templates
    $PAGE->set_context($context);

    require_once(__DIR__ . '/lib/classes/wplib.php');
    $wpIntegration = new \wpIntegration($courseid, block_exaport_get_wpsso_passphrase());
    // Check on Ajax request.
    if ($wpAction) {
        $ajaxParameters = optional_param_array('parameters', [], PARAM_RAW);
        $wpIntegration->handleWpAjaxRequest($action, $ajaxParameters);
        exit;
    }
}

// Regular page
$url = '/blocks/exaport/importexport.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

block_exaport_add_iconpack();

global $DB;
$conditions = array("id" => $courseid);
if (!$course = $DB->get_record("course", $conditions)) {
    error("That's an invalid course id");
}

block_exaport_print_header("importexport");

echo "<br />";

echo "<div class='block_eportfolio_center'>";

$OUTPUT->box(text_to_html(get_string("explainexport", "block_exaport")));

if (has_capability('block/exaport:export', $context)) {
    echo "<p >"
        . block_exaport_fontawesome_icon('file-export', 'solid', 1, [], [], [], '', [], [], [], ['exaport-export-import-scorm-icon'])
        //            ."<img src=\"{$CFG->wwwroot}/blocks/exaport/pix/export.png\" height=\"16\" width=\"16\" alt='".get_string("export", "block_exaport")."' />"
        . " <a title=\"" . get_string("export", "block_exaport") .
        "\" href=\"{$CFG->wwwroot}/blocks/exaport/export_scorm.php?courseid=" . $courseid . "\">" .
        get_string("export", "block_exaport") . "" . "</a></p>";
}

if (has_capability('block/exaport:import', $context)) {
    echo "<p >"
        . block_exaport_fontawesome_icon('file-import', 'solid', 1, [], [], [], '', [], [], [], ['exaport-export-import-scorm-icon'])
        //        ."<img src=\"{$CFG->wwwroot}/blocks/exaport/pix/import.png\" height=\"16\" width=\"16\" alt='".get_string("import", "block_exaport")."' />"
        . " <a title=\"" . get_string("import", "block_exaport") .
        "\" href=\"{$CFG->wwwroot}/blocks/exaport/import_file.php?courseid=" . $courseid . "\">" .
        get_string("import", "block_exaport") . "</a></p>";

}

if (has_capability('block/exaport:importfrommoodle', $context)) {
    $modassign = block_exaport_assignmentversion();
    $assignments = block_exaport_get_assignments_for_import($modassign);
    if ($assignments) {
        echo "<p >"
            . block_exaport_fontawesome_icon('file-import', 'solid', 1, [], [], [], '', [], [], [], ['exaport-export-import-scorm-icon'])
            //            ."<img src=\"{$CFG->wwwroot}/blocks/exaport/pix/import.png\" height=\"16\" width=\"16\" alt='" . get_string("moodleimport", "block_exaport") . "' />"
            . " <a title=\"" . get_string("moodleimport", "block_exaport") .
            "\" href=\"{$CFG->wwwroot}/blocks/exaport/import_moodle.php?courseid=" . $courseid . "\">" .
            get_string("moodleimport", "block_exaport") . "</a></p>";
    }
}

echo "</div>";

if (block_exaport_wpsso_configured()) {
    $PAGE->requires->jquery();
    $PAGE->requires->js_call_amd('block_exaport/wpexport', 'init');
    echo '<hr>';
    echo $wpIntegration->exportFormView();

}

echo block_exaport_wrapperdivend();
echo $OUTPUT->footer($course);
