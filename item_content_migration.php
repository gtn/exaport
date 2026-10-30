<?php
// This file is part of Exabis Eportfolio (extension for Moodle).

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use block_exaport\local\migration_issues;

require_admin();
admin_externalpage_setup('block_exaport_content_recovery');
$url = new moodle_url('/blocks/exaport/item_content_migration.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('contentrecovery', 'block_exaport'));
$PAGE->set_heading(get_string('contentrecovery', 'block_exaport'));

$page = max(0, optional_param('page', 0, PARAM_INT));
$counts = migration_issues::counts();
$issues = $DB->get_records(migration_issues::TABLE, [
    'migrationversion' => migration_issues::VERSION,
], 'id ASC', '*', $page * 100, 100);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('contentrecovery', 'block_exaport'));
echo html_writer::tag('p', get_string('contentrecoveryhelp', 'block_exaport'));
echo html_writer::tag('p', get_string('contentrecoverysummary', 'block_exaport', (object)$counts));
$table = new html_table();
$table->head = array_map(static function($key) {
    return get_string($key, 'block_exaport');
}, ['migrationitemid', 'migrationownerid', 'migrationfileid', 'migrationdestinationid', 'migrationcontenthash',
    'migrationreason', 'migrationfirstseen', 'migrationlastseen', 'migrationattempts', 'migrationtimecopied']);
foreach ($issues as $issue) {
    $table->data[] = [(int)$issue->itemid, (int)$issue->ownerid, (int)$issue->fileid,
        $issue->destinationid ? (int)$issue->destinationid : '-', s($issue->contenthash ?? '-'), s($issue->reason),
        userdate($issue->firstseen), userdate($issue->lastseen), (int)$issue->attempts,
        $issue->timecopied ? userdate($issue->timecopied) : '-'];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($counts['issues_recorded'], $page, 100, $url);
echo $OUTPUT->footer();
