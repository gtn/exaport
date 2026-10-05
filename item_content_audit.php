<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use block_exaport\form\item_content_audit as item_content_audit_form;
use block_exaport\local\item_content_audit;
use block_exaport\local\item_content_audit_output;

require_admin();
admin_externalpage_setup('block_exaport_item_content_audit');
$context = context_system::instance();

$url = new moodle_url('/blocks/exaport/item_content_audit.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('audititemcontent', 'block_exaport'));
$PAGE->set_heading(get_string('audititemcontent', 'block_exaport'));

$form = new item_content_audit_form($url);
$migrationreports = $DB->get_records('block_exaportmigration', null, 'migrationversion ASC');
$result = null;
if ($data = $form->get_data()) {
    // moodleform validates its POST sesskey before returning submitted data.
    $itemid = empty($data->itemid) ? null : (int)$data->itemid;
    $result = (new item_content_audit())->run($itemid, (int)$data->samplelimit);

    if (!empty($data->downloadjson)) {
        $plugin = get_config('block_exaport');
        $output = item_content_audit_output::with_metadata($result, (string)$plugin->version);
        foreach ($migrationreports as $migrationreport) {
            $output['migrationreports'][] = [
                'migrationversion' => (int)$migrationreport->migrationversion,
                'formatversion' => (int)$migrationreport->formatversion,
                'timestarted' => (int)$migrationreport->timestarted,
                'timecompleted' => (int)$migrationreport->timecompleted,
                'summary' => json_decode($migrationreport->summaryjson, true),
            ];
            // Preserve the original key for audit JSON consumers while also exposing all reports.
            if ((int)$migrationreport->migrationversion === 2026092900) {
                $output['migrationreport'] = end($output['migrationreports']);
            }
        }
        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new coding_exception('Unable to encode the item-content audit result');
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="exaport-item-content-audit.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('audititemcontent', 'block_exaport'));
echo $OUTPUT->notification(get_string('audititemcontentreadonlynotice', 'block_exaport'), 'info', false);
echo html_writer::tag('p', get_string('audititemcontentperformancewarning', 'block_exaport'));
echo html_writer::tag('h3', get_string('audititemcontenthistoricalreport', 'block_exaport'));
if (!$migrationreports) {
    echo item_content_audit_output::migration_report_html(null);
}
foreach ($migrationreports as $migrationreport) {
    echo item_content_audit_output::migration_report_html($migrationreport);
}
echo html_writer::tag('h3', get_string('audititemcontentcurrentaudit', 'block_exaport'));
$form->display();

if ($result !== null) {
    echo item_content_audit_output::html($result, !empty($data->verbose));
}

echo $OUTPUT->footer();
