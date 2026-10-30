<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/db/upgradelib.php');

use block_exaport\local\migration_issues;

require_admin();
admin_externalpage_setup('block_exaport_content_recovery');
$url = new moodle_url('/blocks/exaport/item_content_migration.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('contentrecovery', 'block_exaport'));
$PAGE->set_heading(get_string('contentrecovery', 'block_exaport'));

if (data_submitted() && optional_param('retry', 0, PARAM_BOOL)) {
    require_sesskey();
    $itemid = required_param('itemid', PARAM_INT);
    migration_issues::require_independent_commits();
    // A posted ID cannot turn this recovery action into a general legacy-data writer.
    if (migration_issues::pending($itemid)) {
        $item = $DB->get_record('block_exaportitem', ['id' => $itemid], '*', MUST_EXIST);
        core_php_time_limit::raise(1800);
        block_exaport_migrate_legacy_item_content($item);
    }
    redirect($url, get_string('contentrecoveryretried', 'block_exaport'), null,
        \core\output\notification::NOTIFY_INFO);
}

$page = max(0, optional_param('page', 0, PARAM_INT));
$counts = migration_issues::counts();
$params = ['migrationversion' => migration_issues::VERSION];
$issues = $DB->get_records_sql('SELECT m.*, i.id AS existingitemid
    FROM {block_exaportmigissue} m LEFT JOIN {block_exaportitem} i ON i.id = m.itemid
    WHERE m.migrationversion = :migrationversion ORDER BY m.id ASC', $params, $page * 100, 100);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('contentrecovery', 'block_exaport'));
echo html_writer::tag('p', get_string('contentrecoveryhelp', 'block_exaport'));
echo html_writer::tag('p', get_string('contentrecoverysummary', 'block_exaport', (object)$counts));
$table = new html_table();
$table->head = array_map(static function($key) {
    return get_string($key, 'block_exaport');
}, ['migrationitemid', 'migrationownerid', 'migrationfileid', 'migrationreason', 'migrationfirstseen',
    'migrationlastseen', 'migrationattempts', 'migrationstatus', 'migrationresolved', 'migrationretry']);
foreach ($issues as $issue) {
    $retry = '';
    if (!$issue->timeresolved && $issue->existingitemid) {
        $retry = $OUTPUT->single_button(new moodle_url($url, [
            'retry' => 1, 'itemid' => $issue->itemid,
        ]), get_string('migrationretry', 'block_exaport'), 'post');
    }
    $table->data[] = [(int)$issue->itemid, (int)$issue->ownerid, (int)$issue->fileid,
        s($issue->reason), userdate($issue->firstseen), userdate($issue->lastseen), (int)$issue->attempts,
        get_string($issue->timeresolved ? 'migrationresolved' : 'migrationunresolved', 'block_exaport'),
        $issue->timeresolved ? userdate($issue->timeresolved) : '', $retry];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($counts['issues_recorded'], $page, 100, $url);
echo $OUTPUT->footer();
