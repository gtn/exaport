<?php
// This file is part of Moodle - http://moodle.org/.
// Licensed under the GNU GPL v3 or later.

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/lib/item_content_helpers.php');

// Each public entry point fixes the type in server-side code; requests cannot choose a different editor.
if (!isset($contenttype) || !in_array($contenttype, block_exaport_item_content_types(), true)) {
    throw new coding_exception('Unsupported Exaport item content block type');
}

$courseid = required_param('courseid', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);
$blockid = optional_param('blockid', 0, PARAM_INT);
$operation = optional_param('operation', 'save', PARAM_ALPHA);

require_login($courseid);
require_capability('block/exaport:use', context_system::instance());
// Keep the standalone pages' explicit course check, including courseid=0 requests.
$DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);
$item = block_exaport_get_editable_content_item($itemid, $courseid);
$block = $blockid ? block_exaport_get_item_content_block($item, $blockid) : null;
if ($block && $block->type !== $contenttype) {
    throw new invalid_parameter_exception('Content block type does not match the form');
}
if ($operation === 'delete' && !$block) {
    throw new invalid_parameter_exception('A content block is required for deletion');
}
$PAGE->set_url('/blocks/exaport/item_content_' . $contenttype . '.php', [
    'courseid' => $courseid, 'itemid' => $itemid, 'blockid' => $blockid, 'operation' => $operation,
]);
$returnurl = block_exaport_content_return_url($courseid, $itemid);
// The same dynamic form provides the accessible standalone fallback and modal behaviour.
$form = new \block_exaport\form\item_content(null, ['contenttype' => $contenttype, 'standalone' => true]);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($form->get_data()) {
    $form->process_dynamic_submission();
    if ($operation !== 'delete') {
        redirect($returnurl, get_string('contentblockadded', 'block_exaport'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($returnurl);
}
$form->set_data_for_dynamic_submission();
block_exaport_print_header('bookmarks' . block_exaport_get_plural_item_type('all'), 'edit');
echo $OUTPUT->heading(get_string('editcontentblock', 'block_exaport'));
$form->display();
echo block_exaport_wrapperdivend();
echo $OUTPUT->footer();
