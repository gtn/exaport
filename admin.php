<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require_once __DIR__.'/inc.php';

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

	block_exaport_print_header("myportfolio");
	
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
