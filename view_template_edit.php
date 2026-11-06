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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Edit the starting layout and content of a course view template.
 *
 * @package block_exaport
 * @copyright 2026 GTN - Global Training Network GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/inc.php');

$courseid = required_param('courseid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$type = optional_param('type', 'layout', PARAM_ALPHA);
if (!in_array($type, ['layout', 'content'], true)) {
    throw new invalid_parameter_exception('Unknown template editor tab.');
}
block_exaport_require_login($courseid);
require_capability('block/exaport:distributecategories', context_course::instance($courseid));
if (empty($CFG->block_exaport_enable_category_distribution)) {
    throw new moodle_exception('areaisdisabled', 'block_exaport');
}
$template = $DB->get_record('block_exaport_view_templ', ['id' => $id, 'courseid' => $courseid], '*', MUST_EXIST);
$url = new moodle_url('/blocks/exaport/view_template_edit.php', ['courseid' => $courseid, 'id' => $id, 'type' => $type]);
$returnurl = new moodle_url('/blocks/exaport/category_distribution.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->requires->css('/blocks/exaport/css/views_mod.css');

if (data_submitted()) {
    require_sesskey();
    if (optional_param('cancel', false, PARAM_BOOL)) {
        redirect($returnurl);
    }
    if ($type === 'content') {
        $ajax = optional_param('ajax', false, PARAM_BOOL);
        try {
            $blocks = json_decode(required_param('blocks', PARAM_RAW), true);
            if (!is_array($blocks)) {
                throw new invalid_parameter_exception('Invalid template block list.');
            }
            $blocks = \block_exaport\view_template::normalise_blocks($blocks, (int)($template->layout ?? 2));
            if ($ajax) {
                // Applying a block updates the working copy; only Save template writes to the database.
                header('Content-Type: application/json');
                echo json_encode(['blocks' => json_encode(\block_exaport\view_template::editor_blocks($blocks))]);
                exit;
            }
            \block_exaport\view_template::save_blocks($id, $blocks);
        } catch (moodle_exception $exception) {
            if (!$ajax) {
                throw $exception;
            }
            header('Content-Type: application/json');
            echo json_encode(['error' => $exception->getMessage()]);
            exit;
        }
    } else {
        \block_exaport\view_template::update_layout($id, required_param('layout', PARAM_INT));
    }
    redirect($returnurl, get_string('changessaved'), null, 'success');
}

if ($type === 'content') {
    block_exaport_add_iconpack(true);
    $PAGE->requires->css('/blocks/exaport/css/blocks.css');
    $PAGE->requires->css('/blocks/exaport/css/preloadinator.css');
    $strings = [];
    foreach (
        ['view_specialitem_headline', 'view_specialitem_headline_defaulttext', 'view_specialitem_text',
            'view_specialitem_text_defaulttext', 'configureblock_headline', 'configureblock_text', 'delete'] as $key
    ) {
        $strings[$key] = get_string($key, 'block_exaport');
    }
    $PAGE->requires->js_call_amd('block_exaport/views', 'initialise', [context_course::instance($courseid)->id,
        ['templateid' => $id, 'courseid' => $courseid, 'strings' => $strings]]);
}
block_exaport_print_header('category_distribution');
echo $OUTPUT->heading(s($template->name), 3);
echo $OUTPUT->render(new tabtree([
    new tabobject('layout', new moodle_url($url, ['type' => 'layout']), get_string('viewlayout', 'block_exaport')),
    new tabobject('content', new moodle_url($url, ['type' => 'content']), get_string('viewcontent', 'block_exaport')),
], $type));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'id' => 'view_edit_form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
if ($type === 'layout') {
    echo $PAGE->get_renderer('block_exaport')->view_layout_selector((int)($template->layout ?? 2));
} else {
    echo html_writer::tag('p', get_string('view_template_content_instructions', 'block_exaport'));
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'blocks', 'value' => json_encode(
        \block_exaport\view_template::editor_blocks(\block_exaport\view_template::get_blocks($id))
    )]);
    echo html_writer::start_div('', ['id' => 'blocktype-list']);
    echo html_writer::start_tag('ul');
    echo $PAGE->get_renderer('block_exaport')->view_content_text_adders();
    echo html_writer::end_tag('ul') . html_writer::end_div();
    echo $PAGE->get_renderer('block_exaport')->view_content_canvas((int)($template->layout ?? 2));
}
echo html_writer::start_div('text-end pt-3');
echo html_writer::tag(
    'button',
    get_string('save_view_template', 'block_exaport'),
    ['type' => 'submit', 'class' => 'btn btn-primary']
);
echo ' ' . html_writer::tag(
    'button',
    get_string('cancel'),
    ['type' => 'submit', 'name' => 'cancel', 'value' => 1, 'class' => 'btn btn-secondary']
);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
