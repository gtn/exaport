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
    global $USER, $CFG, $DB;

	// always require login, at least guest
	require_login();
	
	if ($filearea == 'item_file') {
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
		$file = $fs->get_file(context_user::instance($item->userid)->id, 'block_exaport', $filearea, $item->id, '/', $filename);

		// serve file
		if ($file) {
			send_stored_file($file);
		} else {
			return false;
		}
	} elseif ($filearea == 'item_content') {
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
		$file = $fs->get_file(context_user::instance($item->userid)->id, 'block_exaport', $filearea, $item->id, '/', $filename);

		// serve file
		if ($file) {
			send_stored_file($file);
		} else {
			return false;
		}
	} elseif ($filearea == 'view_content') {
		$filename = array_pop($args);

		// other params together are the access string
		$access = join('/', $args);

		if (!$view = block_exaport_get_view_from_access($access)) {
			print_error("viewnotfound", "block_exaport");
		}

		// get file
		$fs = get_file_storage();
		$file = $fs->get_file(context_user::instance($view->userid)->id, 'block_exaport', $filearea, $view->id, '/', $filename);

		// serve file
		if ($file) {
			send_stored_file($file);
		} else {
			return false;
		}
	} elseif ($filearea == 'personal_information_view') {
		$filename = array_pop($args);
		
		// other params together are the access string
		$access = join('/', $args);

		if (!$view = block_exaport_get_view_from_access($access)) {
			print_error("viewnotfound", "block_exaport");
		}
		
		// view has personal information?
		$sql = "SELECT b.* FROM {block_exaportviewblock} b".
				" WHERE b.viewid=? AND".
				" b.type='personal_information'";
		if (!$DB->record_exists_sql($sql, array($view->id)))
			return false;
								 
		// get file
		$fs = get_file_storage();
		$file = $fs->get_file(context_user::instance($view->userid)->id, 'block_exaport', 'personal_information', $view->userid, '/', $filename);

		// serve file
		if ($file) {
			send_stored_file($file);
		} else {
			return false;
		}
	} elseif ($filearea == 'personal_information_self') {
		$filename = join('/', $args);
		
		// get file
		$fs = get_file_storage();
		$file = $fs->get_file(context_user::instance($USER->id)->id, 'block_exaport', 'personal_information', $USER->id, '/', $filename);

		// serve file
		if ($file) {
			send_stored_file($file);
		} else {
			return false;
		}
	} else {
		die('wrong file area');
	}
}

