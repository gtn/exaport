<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
// Moodle is free software under the GNU General Public License, version 3 or later.

require_once(__DIR__ . '/inc.php');

use block_exaport\view_structure;
use block_exaport\view_template;
use block_exaport\view_template_editor;

$courseid = required_param('courseid', PARAM_INT);
$id = required_param('id', PARAM_INT);
require_login($courseid);
require_capability('block/exaport:distributecategories', context_course::instance($courseid));
if (empty($CFG->block_exaport_enable_category_distribution) || empty($CFG->block_exaport_enable_views)) {
    throw new moodle_exception('areaisdisabled', 'block_exaport');
}
$template = $DB->get_record('block_exaport_view_templ', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
$url = new moodle_url('/blocks/exaport/view_template_edit.php', ['courseid' => $courseid, 'id' => $id]);
$returnurl = new moodle_url('/blocks/exaport/category_distribution.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context(context_course::instance($courseid));
$definition = view_structure::definition($template->structure ?? '');
$error = '';
if ($data = data_submitted()) {
    require_sesskey();
    try {
        // Reject stale browser tabs instead of overwriting another editor's changes.
        if ((int)required_param('revision', PARAM_INT) !== (int)$template->revision) {
            throw new moodle_exception('template_changed', 'block_exaport');
        }
        $definition = view_template_editor::update($definition, (array)$data,
            required_param('operation', PARAM_RAW_TRIMMED), (array)($data->addtypes ?? []));
        view_template::save_structure($id, $courseid, $definition, (int)$template->revision);
        $operation = required_param('operation', PARAM_RAW_TRIMMED);
        redirect($operation === 'save:' ? $returnurl : $url, get_string('changessaved'));
    } catch (invalid_parameter_exception | moodle_exception $e) {
        $error = $e->getMessage();
    }
}
block_exaport_print_header('category_distribution');
echo $OUTPUT->heading(get_string('template_edit_structure', 'block_exaport') . ': ' . s($template->name));
echo html_writer::div(get_string('template_editor_help', 'block_exaport'), 'alert alert-info');
if ($error) {
    echo $OUTPUT->notification($error, 'error');
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'exaport-template-editor']);
foreach (['sesskey' => sesskey(), 'revision' => $template->revision] as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
echo view_template_editor::render($definition);
echo ' ' . html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
