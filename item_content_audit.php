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
$result = null;
if ($data = $form->get_data()) {
    // moodleform validates its POST sesskey before returning submitted data.
    $itemid = empty($data->itemid) ? null : (int)$data->itemid;
    $result = (new item_content_audit())->run($itemid, (int)$data->samplelimit);

    if (!empty($data->downloadjson)) {
        $plugin = get_config('block_exaport');
        $json = json_encode(item_content_audit_output::with_metadata($result, (string)$plugin->version),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
$form->display();

if ($result !== null) {
    echo item_content_audit_output::html($result, !empty($data->verbose));
}

echo $OUTPUT->footer();
