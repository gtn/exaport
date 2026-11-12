<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use block_exaport\form\item_content_audit as item_content_audit_form;
use block_exaport\local\item_content_audit;
use block_exaport\local\item_content_audit_output;
use block_exaport\local\migration_issues;

require_admin();
admin_externalpage_setup('block_exaport_item_content_audit');
$context = context_system::instance();

$url = new moodle_url('/blocks/exaport/item_content_audit.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('audititemcontent', 'block_exaport'));
$PAGE->set_heading(get_string('audititemcontent', 'block_exaport'));

$form = new item_content_audit_form($url);
// Look up the content-migration report by its savepoint identifier.
$migrationreport = $DB->get_record('block_exaportmigration', ['migrationversion' => 2026101902]);
$result = null;
if ($data = $form->get_data()) {
    // moodleform validates its POST sesskey before returning submitted data.
    $itemid = empty($data->itemid) ? null : (int)$data->itemid;
    $result = (new item_content_audit())->run($itemid, (int)$data->samplelimit);

    if (!empty($data->downloadjson)) {
        $plugin = get_config('block_exaport');
        $output = item_content_audit_output::with_metadata($result, (string)$plugin->version);
        if ($migrationreport) {
            $output['migrationreport'] = [
                'migrationversion' => (int)$migrationreport->migrationversion,
                'formatversion' => (int)$migrationreport->formatversion,
                'timestarted' => (int)$migrationreport->timestarted,
                'timecompleted' => (int)$migrationreport->timecompleted,
                'summary' => json_decode($migrationreport->summaryjson, true),
            ];
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
if (\block_exaport\local\item_content_migration::is_pending()) {
    echo $OUTPUT->notification(get_string('itemcontentmigrationpendingadmin', 'block_exaport'), 'warning', false);
    echo html_writer::link(new moodle_url('/blocks/exaport/item_content_migration_status.php'),
        get_string('itemcontentmigrationtask', 'block_exaport'), ['class' => 'btn btn-primary mb-3']);
}

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
$issuecounts = migration_issues::counts();
if ($issuecounts['files_affected']) {
    echo $OUTPUT->notification(get_string('migrationissuesrecorded', 'block_exaport', $issuecounts['files_affected']),
        'warning', false);
}
echo html_writer::tag('p', get_string('migrationissuesintro', 'block_exaport'), ['class' => 'card-text']);
echo html_writer::link(new moodle_url('/blocks/exaport/item_content_migration.php'),
    get_string('migrationissues', 'block_exaport'), ['class' => 'btn btn-primary']);
echo html_writer::end_div();
echo html_writer::end_div();
if ($migrationreport) {
    echo html_writer::tag('h3', get_string('audititemcontenthistoricalreport', 'block_exaport'));
    echo item_content_audit_output::migration_report_html($migrationreport);
}
echo html_writer::tag('h3', get_string('audititemcontentcurrentaudit', 'block_exaport'));
echo html_writer::tag('p', get_string('audititemcontentscope', 'block_exaport'));
echo html_writer::tag('p', get_string('audititemcontentperformancewarning', 'block_exaport'));
$form->display();

if ($result !== null) {
    echo item_content_audit_output::html($result, !empty($data->verbose));
}

echo $OUTPUT->footer();
