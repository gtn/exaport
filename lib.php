<?php

require_once dirname(__FILE__).'/inc.php';
require_once dirname(__FILE__).'/lib/sharelib.php';

// called from pluginfile.php
// to serve the file of a plugin
// urlformat:
// http://localhost/moodle20/pluginfile.php/17/block_exaport/item_content/portfolio/id/2/itemid/3/pic_145.jpg
// 17/block_exaport/item_content/portfolio/id/2/itemid/3/pic_145.jpg
// user context id (moodle standard)
//    moudle name (moodle standard)
//                  file column name (moodle standard)
//                               access string according to exaport
//                                              itemid (string)
//                                                     itemid
//                                                       file name
function block_exaport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

	// always require login, at least guest
	require_login();

	$filename = array_pop($args);
	$id = array_pop($args);
	if (array_pop($args) != 'itemid') print_error('wrong params');
	
	// other params together are the access string
	$access = join('/', $args);
	
	// item exists?
	$item = block_exaport_get_item($id, $access);
	if (!$item) print_error('Item not found');

	// get file
	$fs = get_file_storage();
	$file = $fs->get_file(get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', $filearea, $item->id, '/', $filename);

	// serve file
	if ($file) {
		send_stored_file($file);
	} else {
		return false;
	}
}

