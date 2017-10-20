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

$item_id = optional_param('item_id', -1, PARAM_INT);
$access = optional_param('access', '', PARAM_TEXT);

ini_set("display_errors", 0);

//require_login(0, true);
$item = null;

// thumbnails for BackEnd (editing the view part)
if ($access == '') {
	echo $access;
	if ($sharable = block_exaport_can_user_access_shared_item($USER->id, $item_id)) {
		// Get thumbnails if item was shared for current user
		$item = $DB->get_record('block_exaportitem', array('id'=>$item_id));
	} else {
		$item = $DB->get_record('block_exaportitem', array('id'=>$item_id, 'userid' => $USER->id));
	}
} else {
	// Checking access to item by access to view
	if (!$view = block_exaport_get_view_from_access($access)) {
		die("view not found");
	}	
	$view_id = $view->id;
	$view_ownerid = $view->userid;
	$item = $DB->get_record('block_exaportitem', array('id'=>$item_id));
	$sharable = block_exaport_can_user_access_shared_item($view_ownerid, $item_id);
	if ($view_ownerid != $item->userid && !$sharable) {
		throw new moodle_exception('item not found');
	}
}
if (empty($item)) throw new moodle_exception('item not found');
//exit;

// Custom Icon file 
if ($iconfile = block_exaport_get_file($item, 'item_iconfile')) {
	send_stored_file($iconfile);
	exit;
}

switch ($item->type) {
	case "file": 
		// thumbnail of file
		$file = block_exaport_get_item_file($item);
		// serve file
		if ($file && $file->is_valid_image()) {
			send_stored_file($file, 1);
			exit;
		}

		$output = block_exaport_get_renderer();
		// needed for pix_url
		$PAGE->set_context(context_system::instance());
		$icon = $output->image_url(file_file_icon($file, 90));

		header('Location: '.$icon);
		break;

	case "link":
		$url = $item->url;
		if (strpos($url,'http')===false)
			$url = 'http://'.$url;

		$str = file_get_contents($url);

		if ($str && preg_match('/<img\s.*src=[\'"]([^\'"]+)[\'"]/im', $str, $matches)) {
			$first_img = $matches[1];
			if (strpos($first_img,'http')===false) {
				if ($first_img[0] == '/') {
					// google.com		+ /imgage.png
					// google.com/sub	+ /imgage.png
					$first_img = preg_replace('!([^:/])/.*$!m', '$1', $url).$first_img;
				} else {
					// google.com		+ imgage.png
					$first_img = $url."/".$first_img;
				}
			}
			
			$headers = get_headers($first_img, 1);
			$type = $headers["Content-Type"];
			
			$imgstr=@file_get_contents($first_img);
			//echo strlen($imgstr);
			if (strlen($imgstr)<50){
				header('Location: pix/link_tile.png');
				break;
			}
			header("Content-type: ".$type);
			echo $imgstr;
			
			exit;
		}

		header('Location: pix/link_tile.png');
		break;

	case "note":
		header('Location: pix/note_tile.png');
		break;
	default:
		die('wrong type');
}
