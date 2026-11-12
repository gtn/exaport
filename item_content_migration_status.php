<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('block_exaport_item_content_migration_status');
require_capability('moodle/site:config', context_system::instance());

if (optional_param('retry', 0, PARAM_BOOL)) {
    require_sesskey();
    \block_exaport\local\item_content_migration::retry();
    redirect(new moodle_url('/blocks/exaport/item_content_migration_status.php'));
}

$pending = \block_exaport\local\item_content_migration::is_pending();
$state = \block_exaport\local\item_content_migration::state();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('itemcontentmigrationtask', 'block_exaport'));
echo $OUTPUT->notification(get_string($pending ? 'itemcontentmigrationpendingadmin' :
    'itemcontentmigrationready', 'block_exaport'), $pending ? 'warning' : 'success', false);
if (!empty($state['started'])) {
    $table = new html_table();
    $table->head = [get_string('itemcontentmigrationprogress', 'block_exaport'), get_string('audititemcontentvalue', 'block_exaport')];
    $table->data = [
        [get_string('itemcontentmigrationphase', 'block_exaport'), s($state['phase'])],
        [get_string('itemcontentmigrationprocessed', 'block_exaport'),
            (int)$state['operations_in_background_job']['items_processed']],
        [get_string('itemcontentmigrationlastitem', 'block_exaport'), (int)$state['lastitemid']],
        [get_string('lastmodified'), userdate($state['updated'] ?? $state['started'])],
    ];
    echo html_writer::table($table);
}
if (!empty($state['error'])) {
    echo $OUTPUT->notification(s($state['error']), 'error', false);
}
echo html_writer::tag('p', html_writer::link(new moodle_url('/admin/tool/task/adhoctasks.php',
    ['classname' => '\\block_exaport\\task\\migrate_item_content']),
    get_string('itemcontentmigrationtasks', 'block_exaport')));
if ($pending) {
    echo $OUTPUT->single_button(new moodle_url('/blocks/exaport/item_content_migration_status.php',
        ['retry' => 1, 'sesskey' => sesskey()]), get_string('itemcontentmigrationretry', 'block_exaport'), 'post');
}
echo html_writer::tag('p', html_writer::link(new moodle_url('/blocks/exaport/item_content_audit.php'),
    get_string('audititemcontent', 'block_exaport')));
echo $OUTPUT->footer();
