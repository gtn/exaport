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

use block_exaport\category_template;
use block_exaport\category_distributor;
use block_exaport\view_template;
use block_exaport\view_distributor;
use function block_exaport\common\print_error;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_TEXT);

block_exaport_require_login($courseid);

$context = context_course::instance($courseid);
require_capability('block/exaport:distributecategories', $context);

if (empty($CFG->block_exaport_enable_category_distribution)) {
    print_error('areaisdisabled', 'block_exaport');
}

$url = new moodle_url('/blocks/exaport/category_distribution.php', array('courseid' => $courseid));
$PAGE->set_url($url);

// Handle actions.
$message = '';
$messagetype = 'success';

if ($action === 'load_template' && confirm_sesskey()) {
    $template_name = required_param('template_name', PARAM_TEXT);
    if (category_template::load_starter_template($courseid, $template_name)) {
        $message = get_string('starter_template_loaded', 'block_exaport');
    } else {
        $message = get_string('distribution_error', 'block_exaport', 'Template not found');
        $messagetype = 'error';
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'distribute_now' && confirm_sesskey()) {
    $stats = category_distributor::distribute_to_course($courseid);
    if (isset($stats['error'])) {
        $message = get_string('no_template_to_distribute', 'block_exaport');
        $messagetype = 'error';
    } else {
        $summary = get_string('distribution_complete', 'block_exaport') . '<br>';
        $summary .= get_string('students_processed', 'block_exaport', $stats['students']) . '<br>';
        $summary .= get_string('categories_created', 'block_exaport', $stats['created']) . '<br>';
        $summary .= get_string('categories_skipped', 'block_exaport', $stats['skipped']);
        $message = $summary;
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'toggle_auto_distribute' && confirm_sesskey()) {
    $auto_distribute = optional_param('auto_distribute', 0,  PARAM_INT); // when the checkbox is unchecked, it is not sent as a param ==> default to 0
    category_distributor::update_settings($courseid, $auto_distribute);
    $message = get_string('changessaved');
    redirect($url, $message, null, 'success');
}

if ($action === 'add_category' && confirm_sesskey()) {
    $name = required_param('name', PARAM_TEXT);
    $pid = optional_param('pid', 0, PARAM_INT);
    $share_to_teachers = optional_param('share_to_teachers', 0, PARAM_INT);

    // Verify parent belongs to this course (if not root).
    if ($pid !== 0) {
        category_template::verify_category($pid, $courseid);
    }

    $name = trim($name);
    if (!empty($name) && strlen($name) <= 255) {
        category_template::add_category($courseid, $name, $pid, $share_to_teachers);
        $message = get_string('category_added', 'block_exaport');
    } else {
        $message = get_string('category_name_required', 'block_exaport');
        $messagetype = 'error';
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'rename_category' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);
    $name = required_param('name', PARAM_TEXT);

    // Verify category belongs to this course.
    category_template::verify_category($id, $courseid);

    $name = trim($name);
    if (!empty($name) && strlen($name) <= 255) {
        category_template::rename_category($id, $name);
        $message = get_string('category_renamed', 'block_exaport');
    } else {
        $message = get_string('category_name_required', 'block_exaport');
        $messagetype = 'error';
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'move_category' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);
    $newpid = required_param('newpid', PARAM_INT);

    // Verify category belongs to this course.
    category_template::verify_category($id, $courseid);

    // Verify new parent belongs to this course (if not root).
    if ($newpid !== 0) {
        category_template::verify_category($newpid, $courseid);
    }

    category_template::move_category($id, $newpid);
    $message = get_string('category_moved', 'block_exaport');
    redirect($url, $message, null, 'success');
}

if ($action === 'remove_category' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);

    // Verify category belongs to this course.
    category_template::verify_category($id, $courseid);

    category_template::remove_category($id);
    $message = get_string('category_removed', 'block_exaport');
    redirect($url, $message, null, 'success');
}

if ($action === 'toggle_share_to_teachers' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);
    $share_to_teachers = required_param('share_to_teachers', PARAM_INT);

    // Verify category belongs to this course.
    $category = category_template::verify_category($id, $courseid);

    // Update share_to_teachers flag.
    $DB->update_record('block_exaport_course_templ', (object)array(
        'id' => $id,
        'share_to_teachers' => $share_to_teachers,
        'timemodified' => time(),
    ));

    $message = get_string('changessaved');
    redirect($url, $message, null, 'success');
}

// View template actions.
if ($action === 'load_view_template' && confirm_sesskey()) {
    $template_name = required_param('template_name', PARAM_TEXT);
    if (view_template::load_starter_template($courseid, $template_name)) {
        $message = get_string('starter_template_loaded', 'block_exaport');
    } else {
        $message = get_string('distribution_error', 'block_exaport', 'Template not found');
        $messagetype = 'error';
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'add_view' && confirm_sesskey()) {
    $name = required_param('name', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $share_to_teachers = optional_param('share_to_teachers', 0, PARAM_INT);

    $name = trim($name);
    if (!empty($name) && strlen($name) <= 255) {
        view_template::add_view($courseid, $name, $description, $share_to_teachers);
        $message = get_string('view_added', 'block_exaport');
    } else {
        $message = get_string('view_name_required', 'block_exaport');
        $messagetype = 'error';
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'rename_view' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);
    $name = required_param('name', PARAM_TEXT);

    // Verify view belongs to this course.
    view_template::verify_view($id, $courseid);

    $name = trim($name);
    if (!empty($name) && strlen($name) <= 255) {
        view_template::rename_view($id, $name);
        $message = get_string('view_renamed', 'block_exaport');
    } else {
        $message = get_string('view_name_required', 'block_exaport');
        $messagetype = 'error';
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'remove_view' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);

    // Verify view belongs to this course.
    view_template::verify_view($id, $courseid);

    view_template::remove_view($id);
    $message = get_string('view_removed', 'block_exaport');
    redirect($url, $message, null, 'success');
}

if ($action === 'toggle_view_share' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);
    $share_to_teachers = required_param('share_to_teachers', PARAM_INT);

    // Verify view belongs to this course.
    view_template::verify_view($id, $courseid);

    view_template::toggle_share_to_teachers($id, $share_to_teachers);
    $message = get_string('changessaved');
    redirect($url, $message, null, 'success');
}

if ($action === 'distribute_views_now' && confirm_sesskey()) {
    $stats = view_distributor::distribute_to_course($courseid);
    if (isset($stats['error'])) {
        $message = get_string('no_views_to_distribute', 'block_exaport');
        $messagetype = 'error';
    } else {
        $summary = get_string('distribution_complete', 'block_exaport') . '<br>';
        $summary .= get_string('students_processed', 'block_exaport', $stats['students']) . '<br>';
        $summary .= get_string('views_created', 'block_exaport', $stats['created']) . '<br>';
        $summary .= get_string('views_skipped', 'block_exaport', $stats['skipped']);
        $message = $summary;
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'toggle_auto_distribute_views' && confirm_sesskey()) {
    $auto_distribute_views = optional_param('auto_distribute_views', 0, PARAM_INT);
    $settings = view_distributor::get_settings($courseid);
    view_distributor::update_settings($courseid, $auto_distribute_views);
    $message = get_string('changessaved');
    redirect($url, $message, null, 'success');
}

// Get current data.
$templates = category_template::get_starter_templates();
$course_template = category_template::get_course_template($courseid);
$settings = category_distributor::get_settings($courseid);

// Get view templates.
$view_templates = view_template::get_starter_templates();
$course_view_template = view_template::get_course_template($courseid);
$view_settings = view_distributor::get_settings($courseid);

// Get all template nodes for move operations.
$all_template_nodes = $DB->get_records('block_exaport_course_templ', array('courseid' => $courseid), 'sortorder ASC');

// Prepare nodes array for JavaScript.
$js_nodes = array();
foreach ($all_template_nodes as $node) {
    $js_nodes[] = array(
        'id' => $node->id,
        'name' => $node->name,
    );
}

// Initialize AMD module with configuration.
$PAGE->requires->js_call_amd('block_exaport/category_distribution', 'init', array(
    array(
        'url' => $url->out(false),
        'sesskey' => sesskey(),
        'strings' => array(
            'categoryNameRequired' => get_string('category_name_required', 'block_exaport'),
            'selectParent' => get_string('move_category_select_parent', 'block_exaport'),
            'moveToRoot' => get_string('move_to_root', 'block_exaport'),
            'enterParentId' => get_string('enter_parent_id', 'block_exaport'),
            'addSubcategory' => get_string('add_subcategory', 'block_exaport'),
            'renameCategory' => get_string('rename_category', 'block_exaport'),
            'moveCategory' => get_string('move_category', 'block_exaport'),
            'addView' => get_string('add_view', 'block_exaport'),
            'renameView' => get_string('rename_view', 'block_exaport'),
            'viewNameRequired' => get_string('view_name_required', 'block_exaport'),
            'save' => get_string('save', 'core'),
            'confirmDistributeCategoriesTitle' => get_string('confirm_distribute_categories_title', 'block_exaport'),
            'confirmDistributeCategoriesBody' => get_string('confirm_distribute_categories_body', 'block_exaport'),
            'confirmDistributeViewsTitle' => get_string('confirm_distribute_views_title', 'block_exaport'),
            'confirmDistributeViewsBody' => get_string('confirm_distribute_views_body', 'block_exaport'),
            'distribute' => get_string('distribute', 'block_exaport'),
        ),
        'nodes' => $js_nodes,
    )
));

block_exaport_print_header("category_distribution");

echo html_writer::tag('p', get_string('category_distribution_description', 'block_exaport'));

// Section 1: Load Starter Template.
echo $OUTPUT->heading(get_string('starter_template_select', 'block_exaport'), 3);

if (!empty($templates)) {
    echo '<form method="post" action="' . $url->out() . '" id="load-template-form">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="load_template">';
    echo '<div class="form-group">';
    echo '<select name="template_name" class="form-control" style="display: inline-block; width: auto;">';
    foreach ($templates as $template) {
        echo '<option value="' . s($template['name']) . '">' . s($template['name']) . '</option>';
    }
    echo '</select> ';
    echo '<button type="submit" class="btn btn-secondary" onclick="return confirm(' .
        json_encode(get_string('starter_template_load_confirm', 'block_exaport')) . ');">' .
        get_string('starter_template_load', 'block_exaport') . '</button>';
    echo '</div>';
    echo '</form>';
} else {
    echo '<p>' . get_string('invalid_template_json', 'block_exaport') . '</p>';
}

// Section 2: Current Template.
echo $OUTPUT->heading(get_string('current_template', 'block_exaport'), 3);

if (empty($course_template)) {
    echo '<p>' . get_string('template_empty', 'block_exaport') . '</p>';
    echo '<form method="post" action="' . $url->out() . '">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="add_category">';
    echo '<input type="hidden" name="pid" value="0">';
    echo '<div class="form-inline">';
    echo '<input type="text" name="name" class="form-control" placeholder="' .
        get_string('category_name_required', 'block_exaport') . '" required> ';
    echo '<button type="submit" class="btn btn-primary">' .
        get_string('add_root_category', 'block_exaport') . '</button>';
    echo '</div>';
    echo '</form>';
} else {
    // Display tree.
    echo '<div class="exaport-template-tree">';
    block_exaport_render_template_tree($course_template, $url, $all_template_nodes);
    
    // Add new root category button (similar to views).
    echo '<div style="margin-top: 15px;">';
    echo '<button type="button" class="btn btn-sm btn-outline-primary" data-action="add-category">' .
        get_string('add_category', 'block_exaport') . '</button>';
    echo '</div>';
    echo '</div>';
}

// Section 3: Distribution Controls.
echo $OUTPUT->heading(get_string('distribute_categories', 'block_exaport'), 3);

echo '<div style="margin-bottom: 20px;">';
echo '<button type="button" class="btn btn-primary" data-action="distribute-categories"' .
    (empty($course_template) ? ' disabled' : '') . '>' .
    get_string('distribute_categories_now', 'block_exaport') . '</button>';
echo '</div>';

echo '<form method="post" action="' . $url->out() . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="toggle_auto_distribute">';
echo '<div class="form-check">';
$checked = $settings->auto_distribute ? 'checked' : '';
echo '<input type="checkbox" name="auto_distribute" value="1" class="form-check-input" id="auto-dist" ' .
    $checked . ' onchange="this.form.submit();">';
echo '<label class="form-check-label" for="auto-dist">' .
    get_string('auto_distribute_on_enrolment', 'block_exaport') . '</label>';
echo '</div>';
echo '</form>';

// Separator.
echo '<hr style="margin: 40px 0;">';

// VIEW DISTRIBUTION SECTION.
echo $OUTPUT->heading(get_string('view_distribution', 'block_exaport'), 2);
echo html_writer::tag('p', get_string('view_distribution_description', 'block_exaport'));

// Section 4: Load Starter View Template.
echo $OUTPUT->heading(get_string('starter_view_template_select', 'block_exaport'), 3);

if (!empty($view_templates)) {
    echo '<form method="post" action="' . $url->out() . '" id="load-view-template-form">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="load_view_template">';
    echo '<div class="form-group">';
    echo '<select name="template_name" class="form-control" style="display: inline-block; width: auto;">';
    foreach ($view_templates as $template) {
        echo '<option value="' . s($template['name']) . '">' . s($template['name']) . '</option>';
    }
    echo '</select> ';
    echo '<button type="submit" class="btn btn-secondary" onclick="return confirm(' .
        json_encode(get_string('starter_template_load_confirm', 'block_exaport')) . ');">' .
        get_string('starter_template_load', 'block_exaport') . '</button>';
    echo '</div>';
    echo '</form>';
} else {
    echo '<p>' . get_string('invalid_template_json', 'block_exaport') . '</p>';
}

// Section 5: Current View Template.
echo $OUTPUT->heading(get_string('current_view_template', 'block_exaport'), 3);

if (empty($course_view_template)) {
    echo '<p>' . get_string('view_template_empty', 'block_exaport') . '</p>';
    echo '<form method="post" action="' . $url->out() . '">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="add_view">';
    echo '<div class="form-inline">';
    echo '<input type="text" name="name" class="form-control" placeholder="' .
        get_string('view_name_required', 'block_exaport') . '" required> ';
    echo '<button type="submit" class="btn btn-primary">' .
        get_string('add_view', 'block_exaport') . '</button>';
    echo '</div>';
    echo '</form>';
} else {
    // Display view list.
    echo '<div class="exaport-view-template-list">';
    block_exaport_render_view_template_list($course_view_template, $url);
    echo '</div>';
}

// Section 6: View Distribution Controls.
echo $OUTPUT->heading(get_string('distribute_views', 'block_exaport'), 3);

echo '<div style="margin-bottom: 20px;">';
echo '<button type="button" class="btn btn-primary" data-action="distribute-views"' .
    (empty($course_view_template) ? ' disabled' : '') . '>' .
    get_string('distribute_views_now', 'block_exaport') . '</button>';
echo '</div>';

echo '<form method="post" action="' . $url->out() . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="toggle_auto_distribute_views">';
echo '<div class="form-check">';
$checked_views = $view_settings->auto_distribute_views ? 'checked' : '';
echo '<input type="checkbox" name="auto_distribute_views" value="1" class="form-check-input" id="auto-dist-views" ' .
    $checked_views . ' onchange="this.form.submit();">';
echo '<label class="form-check-label" for="auto-dist-views">' .
    get_string('auto_distribute_views_on_enrolment', 'block_exaport') . '</label>';
echo '</div>';
echo '</form>';

echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();

/**
 * Render template tree recursively
 *
 * @param array $tree Tree structure
 * @param moodle_url $url Base URL
 * @param array $all_nodes All template nodes for move operation
 * @param int $level Nesting level
 */
function block_exaport_render_template_tree($tree, $url, $all_nodes, $level = 0) {
    echo '<ul class="list-unstyled" style="margin-left: ' . ($level * 20) . 'px;">';
    foreach ($tree as $node) {
        echo '<li style="margin-bottom: 10px;">';
        echo '<div class="d-flex align-items-center justify-content-between">';
        echo '<strong>' . s($node['name']) . '</strong>';

        // Actions.
        echo '<div class="btn-group btn-group-sm" role="group">';

        // Add subcategory.
        echo '<button type="button" class="btn btn-sm btn-outline-primary" ' .
            'data-action="add-subcategory" data-pid="' . $node['id'] . '">' .
            get_string('add_subcategory', 'block_exaport') . '</button>';

        // Rename.
        echo '<button type="button" class="btn btn-sm btn-outline-secondary" ' .
            'data-action="rename-category" data-id="' . $node['id'] . '" data-name="' . s($node['name']) . '">' .
            get_string('rename_category', 'block_exaport') . '</button>';

        // Move.
        echo '<button type="button" class="btn btn-sm btn-outline-info" ' .
            'data-action="move-category" data-id="' . $node['id'] . '">' .
            get_string('move_category', 'block_exaport') . '</button>';

        // Remove.
        $removeurl = new moodle_url($url, array('action' => 'remove_category', 'id' => $node['id'], 'sesskey' => sesskey()));
        echo '<a href="' . $removeurl->out() . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(' .
            json_encode(get_string('remove_from_template_confirm', 'block_exaport')) . ');">' .
            get_string('remove_from_template', 'block_exaport') . '</a>';

        // Share to teachers toggle button.
        $is_shared = isset($node['share_to_teachers']) && $node['share_to_teachers'];
        $share_class = $is_shared ? 'btn-warning' : 'btn-outline-warning';
        echo '<button type="button" class="btn btn-sm ' . $share_class . '" ' .
            'data-action="toggle-share" data-id="' . $node['id'] . '" data-shared="' . ($is_shared ? '1' : '0') . '" ' .
            'title="' . s(get_string('share_to_teachers_help', 'block_exaport')) . '">' .
            get_string('share_to_teachers', 'block_exaport') . '</button>';

        echo '</div>';

        echo '</div>';

        // Render children.
        if (!empty($node['children'])) {
            block_exaport_render_template_tree($node['children'], $url, $all_nodes, $level + 1);
        }
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * Render view template list
 *
 * @param array $views View list
 * @param moodle_url $url Base URL
 */
function block_exaport_render_view_template_list($views, $url) {
    echo '<ul class="list-group">';
    foreach ($views as $view) {
        echo '<li class="list-group-item">';
        echo '<div class="d-flex align-items-center justify-content-between">';
        echo '<div><strong>' . s($view->name) . '</strong>';
        if (!empty($view->description)) {
            echo '<br><small class="text-muted">' . s($view->description) . '</small>';
        }
        echo '</div>';

        // Actions.
        echo '<div class="btn-group btn-group-sm" role="group">';

        // Rename.
        echo '<button type="button" class="btn btn-sm btn-outline-secondary" ' .
            'data-action="rename-view" data-id="' . $view->id . '" data-name="' . s($view->name) . '">' .
            get_string('rename_view', 'block_exaport') . '</button>';

        // Remove.
        $removeurl = new moodle_url($url, array('action' => 'remove_view', 'id' => $view->id, 'sesskey' => sesskey()));
        echo '<a href="' . $removeurl->out() . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(' .
            json_encode(get_string('remove_view_confirm', 'block_exaport')) . ');">' .
            get_string('remove_view', 'block_exaport') . '</a>';

        // Share to teachers toggle button.
        $is_shared = isset($view->share_to_teachers) && $view->share_to_teachers;
        $share_class = $is_shared ? 'btn-warning' : 'btn-outline-warning';
        echo '<button type="button" class="btn btn-sm ' . $share_class . '" ' .
            'data-action="toggle-view-share" data-id="' . $view->id . '" data-shared="' . ($is_shared ? '1' : '0') . '" ' .
            'title="' . s(get_string('share_to_teachers_help', 'block_exaport')) . '">' .
            get_string('share_to_teachers', 'block_exaport') . '</button>';

        echo '</div>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';

    // Add new view button.
    echo '<div style="margin-top: 15px;">';
    echo '<button type="button" class="btn btn-sm btn-outline-primary" data-action="add-view">' .
        get_string('add_view', 'block_exaport') . '</button>';
    echo '</div>';
}
?>

<style>
.exaport-template-tree {
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.exaport-template-tree ul {
    list-style: none;
}
.exaport-template-tree li {
    padding: 5px 0;
}
.exaport-view-template-list {
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>
