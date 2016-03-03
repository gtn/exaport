<?php

require_once dirname(__FILE__) . '/inc.php';

require_login(0, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = '/blocks/exaport/admin.php';
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$backurl = $CFG->wwwroot.'/admin/settings.php?section=blocksettingexaport';
$action = required_param('action', PARAM_TEXT);

if ($action == 'remove_shareall') {
	if (optional_param('confirm', 0, PARAM_INT)) {
		confirm_sesskey();
		
		$sql = "UPDATE {block_exaportview} SET shareall=0";
		$DB->execute($sql);

		redirect($backurl);
		exit;
	}

	block_exaport_print_header("bookmarks");
	
	echo '<br />';
	echo $OUTPUT->confirm(block_exaport_get_string("delete_all_shareall"),
		new moodle_url('admin.php', array('action'=>$action, 'confirm'=>1, 'sesskey'=>sesskey())),
		$backurl);
	echo block_exaport_wrapperdivend();
	$OUTPUT->footer();

	exit;
}

die('error');


// http://localhost/moodle24