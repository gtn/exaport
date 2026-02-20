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
require_once(__DIR__ . '/lib/category_distribution.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login($courseid);

$context = context_course::instance($courseid);
require_capability('block/exaport:distributecategories', $context);

$url = new moodle_url('/blocks/exaport/category_distribution.php', array('courseid' => $courseid));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('category_distribution', 'block_exaport'));
$PAGE->set_heading(get_string('category_distribution_title', 'block_exaport'));

// Handle actions.
$message = '';
$messagetype = 'success';

if ($action === 'load_template' && confirm_sesskey()) {
    $template_name = required_param('template_name', PARAM_TEXT);
    if (block_exaport_load_starter_template($courseid, $template_name)) {
        $message = get_string('starter_template_loaded', 'block_exaport');
    } else {
        $message = get_string('distribution_error', 'block_exaport', 'Template not found');
        $messagetype = 'error';
    }
    redirect($url, $message, null, $messagetype);
}

if ($action === 'distribute_now' && confirm_sesskey()) {
    $stats = block_exaport_distribute_to_course($courseid);
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
    $auto_distribute = required_param('auto_distribute', PARAM_INT);
    block_exaport_update_distribution_settings($courseid, $auto_distribute);
    $message = get_string('changessaved');
    redirect($url, $message, null, 'success');
}

if ($action === 'add_category' && confirm_sesskey()) {
    $name = required_param('name', PARAM_TEXT);
    $pid = optional_param('pid', 0, PARAM_INT);
    
    // Verify parent belongs to this course (if not root).
    if ($pid != 0) {
        $parent = $DB->get_record('block_exaport_course_templ', array('id' => $pid, 'courseid' => $courseid));
        if (!$parent) {
            print_error('Invalid parent category');
        }
    }
    
    $name = trim($name);
    if (!empty($name) && strlen($name) <= 255) {
        block_exaport_add_template_category($courseid, $name, $pid);
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
    $category = $DB->get_record('block_exaport_course_templ', array('id' => $id, 'courseid' => $courseid));
    if (!$category) {
        print_error('Invalid category');
    }
    
    $name = trim($name);
    if (!empty($name) && strlen($name) <= 255) {
        block_exaport_rename_template_category($id, $name);
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
    $category = $DB->get_record('block_exaport_course_templ', array('id' => $id, 'courseid' => $courseid));
    if (!$category) {
        print_error('Invalid category');
    }
    
    // Verify new parent belongs to this course (if not root).
    if ($newpid != 0) {
        $parent = $DB->get_record('block_exaport_course_templ', array('id' => $newpid, 'courseid' => $courseid));
        if (!$parent) {
            print_error('Invalid parent category');
        }
    }
    
    block_exaport_move_template_category($id, $newpid);
    $message = get_string('category_moved', 'block_exaport');
    redirect($url, $message, null, 'success');
}

if ($action === 'remove_category' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);
    
    // Verify category belongs to this course.
    $category = $DB->get_record('block_exaport_course_templ', array('id' => $id, 'courseid' => $courseid));
    if (!$category) {
        print_error('Invalid category');
    }
    
    block_exaport_remove_template_category($id);
    $message = get_string('category_removed', 'block_exaport');
    redirect($url, $message, null, 'success');
}

// Get current data.
$templates = block_exaport_get_starter_templates();
$course_template = block_exaport_get_course_template($courseid);
$settings = block_exaport_get_distribution_settings($courseid);

// Get all template nodes for move operations.
$all_template_nodes = $DB->get_records('block_exaport_course_templ', array('courseid' => $courseid), 'sortorder ASC');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('category_distribution_title', 'block_exaport'));
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
    echo '<button type="submit" class="btn btn-secondary" onclick="return confirm(\'' .
        get_string('starter_template_load_confirm', 'block_exaport') . '\');">' .
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
    echo '</div>';
}

// Section 3: Distribution Controls.
echo $OUTPUT->heading(get_string('distribute_now', 'block_exaport'), 3);

echo '<form method="post" action="' . $url->out() . '" style="margin-bottom: 20px;">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="distribute_now">';
echo '<button type="submit" class="btn btn-primary"' .
    (empty($course_template) ? ' disabled' : '') . '>' .
    get_string('distribute_now', 'block_exaport') . '</button>';
echo '</form>';

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
        echo '<div class="d-flex align-items-center">';
        echo '<strong>' . s($node['name']) . '</strong> ';

        // Actions.
        echo '<div class="btn-group btn-group-sm ml-2" role="group">';

        // Add subcategory.
        echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="addSubcategory(' . $node['id'] . ')">' .
            get_string('add_subcategory', 'block_exaport') . '</button>';

        // Rename.
        echo '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="renameCategory(' . $node['id'] . ', ' .
            json_encode($node['name']) . ')">' . get_string('rename_category', 'block_exaport') . '</button>';

        // Move.
        echo '<button type="button" class="btn btn-sm btn-outline-info" onclick="moveCategory(' . $node['id'] . ')">' .
            get_string('move_category', 'block_exaport') . '</button>';

        // Remove.
        $removeurl = new moodle_url($url, array('action' => 'remove_category', 'id' => $node['id'], 'sesskey' => sesskey()));
        echo '<a href="' . $removeurl->out() . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'' .
            get_string('remove_from_template_confirm', 'block_exaport') . '\');">' .
            get_string('remove_from_template', 'block_exaport') . '</a>';

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
?>

<script>
function addSubcategory(pid) {
    var name = prompt(<?php echo json_encode(get_string('category_name_required', 'block_exaport')); ?>);
    if (name) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = <?php echo json_encode($url->out()); ?>;

        var fields = {
            'sesskey': <?php echo json_encode(sesskey()); ?>,
            'action': 'add_category',
            'pid': pid,
            'name': name
        };

        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
}

function renameCategory(id, oldname) {
    var name = prompt(<?php echo json_encode(get_string('category_name_required', 'block_exaport')); ?>, oldname);
    if (name && name !== oldname) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = <?php echo json_encode($url->out()); ?>;

        var fields = {
            'sesskey': <?php echo json_encode(sesskey()); ?>,
            'action': 'rename_category',
            'id': id,
            'name': name
        };

        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
}

function moveCategory(id) {
    // Create a simple dialog using prompt as fallback
    // In a real implementation, this should use Moodle's modal dialog
    var message = <?php echo json_encode(get_string('move_category_select_parent', 'block_exaport') . "\n\n"); ?>;
    message += <?php echo json_encode(get_string('move_to_root', 'block_exaport') . ': 0' . "\n"); ?>;
    <?php
    foreach ($all_template_nodes as $node) {
        if ($node->id != $id) { // Can't move to itself.
            echo "message += " . json_encode($node->name . ': ' . $node->id . "\n") . ";\n";
        }
    }
    ?>
    message += <?php echo json_encode("\n" . get_string('enter_parent_id', 'block_exaport') . ':'); ?>;

    var newpid = prompt(message, '0');
    if (newpid !== null) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = <?php echo json_encode($url->out()); ?>;

        var fields = {
            'sesskey': <?php echo json_encode(sesskey()); ?>,
            'action': 'move_category',
            'id': id,
            'newpid': newpid || '0'
        };

        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

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
</style>
