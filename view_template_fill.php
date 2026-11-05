<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
// Moodle is free software under the GNU General Public License, version 3 or later.

require_once(__DIR__ . '/inc.php');

use block_exaport\view_structure;
use block_exaport\form\view_structure as response_form;

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$id = required_param('id', PARAM_INT);
require_login($courseid);
require_capability('block/exaport:use', context_system::instance());
if (empty($CFG->block_exaport_enable_views)) {
    throw new moodle_exception('areaisdisabled', 'block_exaport');
}
$view = $DB->get_record('block_exaportview', ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST);
$url = new moodle_url('/blocks/exaport/view_template_fill.php', ['courseid' => $courseid, 'id' => $id]);
$previewurl = new moodle_url('/blocks/exaport/shared_view.php', ['courseid' => $courseid, 'access' => 'id/' . $USER->id . '-' . $id]);
$PAGE->set_url($url);
$PAGE->set_context(context_course::instance($courseid));
$blocks = view_structure::blocks($id);
$form = new response_form($url, ['view' => $view, 'blocks' => $blocks]);
$form->prepare($view, $blocks);
if ($form->is_cancelled()) {
    redirect($previewurl);
}
if ($data = $form->get_data()) {
    require_sesskey();
    $factory = \core\lock\lock_config::get_lock_factory('block_exaport_view_response');
    $lock = $factory->get_lock((string)$view->id, 10);
    if (!$lock) {
        throw new moodle_exception('locktimeout');
    }
    try {
        if (!hash_equals(response_form::token(view_structure::blocks($id)), $data->structuretoken)) {
            throw new moodle_exception('template_changed', 'block_exaport');
        }
        $transaction = $DB->start_delegated_transaction();
        response_form::save_responses($view, $blocks, $data);
        $operation = optional_param('structureoperation', '', PARAM_RAW_TRIMMED);
        if ($operation !== '') {
            [$action, $key] = array_pad(explode(':', $operation, 2), 2, '');
            if ($action === 'repeat') {
                view_structure::repeat($id, $key);
            } else if ($action === 'remove') {
                view_structure::remove($id, $key);
            } else {
                throw new invalid_parameter_exception('Invalid structure operation');
            }
        }
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    redirect(!empty($data->previewbutton) ? $previewurl : $url, get_string('changessaved'));
}
block_exaport_print_header('views', 'content');
echo $OUTPUT->heading(s($view->name));
echo html_writer::div(get_string('template_fill_help', 'block_exaport'), 'alert alert-info');
echo html_writer::div(html_writer::link($previewurl, get_string('preview')) . ' · ' . html_writer::link(
    new moodle_url('/blocks/exaport/views_mod.php', ['id' => $id, 'courseid' => $courseid, 'action' => 'edit', 'extras' => 1]),
    get_string('template_extra_content', 'block_exaport')), 'mb-4');
$form->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
