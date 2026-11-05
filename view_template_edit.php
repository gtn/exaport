<?php
// This file is part of Exabis Eportfolio (extension for Moodle).
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

require_once(__DIR__ . '/inc.php');

$courseid = required_param('courseid', PARAM_INT);
$id = required_param('id', PARAM_INT);
block_exaport_require_login($courseid);
require_capability('block/exaport:distributecategories', context_course::instance($courseid));
if (empty($CFG->block_exaport_enable_category_distribution)) {
    throw new moodle_exception('areaisdisabled', 'block_exaport');
}
$template = $DB->get_record('block_exaport_view_templ', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
$url = new moodle_url('/blocks/exaport/view_template_edit.php', ['courseid' => $courseid, 'id' => $id]);
$returnurl = new moodle_url('/blocks/exaport/category_distribution.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->requires->css('/blocks/exaport/css/views_mod.css');

if (data_submitted()) {
    require_sesskey();
    if (optional_param('cancel', false, PARAM_BOOL)) {
        redirect($returnurl);
    }
    \block_exaport\view_template::update_layout($id, required_param('layout', PARAM_INT));
    redirect($returnurl, get_string('changessaved'), null, 'success');
}

block_exaport_print_header('category_distribution');
echo $OUTPUT->heading(s($template->name), 3);
echo $OUTPUT->render(new tabtree([new tabobject('layout', $url, get_string('viewlayout', 'block_exaport'))], 'layout'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo $PAGE->get_renderer('block_exaport')->view_layout_selector((int)($template->layout ?? 2));
echo html_writer::start_div('text-end pt-3');
echo html_writer::tag('button', get_string('save_view_template', 'block_exaport'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo ' ' . html_writer::tag('button', get_string('cancel'),
    ['type' => 'submit', 'name' => 'cancel', 'value' => 1, 'class' => 'btn btn-secondary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
