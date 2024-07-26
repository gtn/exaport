<?php
global $PAGE, $USER, $OUTPUT;
require_once(__DIR__ . '/inc.php');
require_once(__DIR__ . '/lib/minixml.inc.php');
require_once ("common_functions.php");
require_once("export_scorm.php");
global $DB, $CFG;
$courseid = optional_param("courseid", 0, PARAM_INT);
$url = '/blocks/exaport/export_xapi.php';
$PAGE->set_url($url, ['courseid' => $courseid]);
global $zip, $existingfilesarray;
$zip = block_exacomp_ZipArchive::create_temp_file();
$existingfilesarray = array();

$courseid = optional_param("courseid", 0, PARAM_INT);
$confirm = optional_param("confirm", 0, PARAM_INT);
$viewid = optional_param("viewid", 0, PARAM_INT);
$identifier = 1000000; // Item identifier.
$ridentifier = 1000000; // Ressource identifier.

$context = context_system::instance();

require_login($courseid);
require_capability('block/exaport:use', $context);
require_capability('block/exaport:export', $context);
creatFrontEnd();
?>


