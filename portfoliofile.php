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

// Syntax:
// Files in the portfolio:
// portfoliofile.php/files/$userid/$portfolioid/filename.ext
// Exported SCORM-File (user has to be logged in)
// portfoliofile.php/temp/export/$userid/filename.ext

if (!empty($_GET['token']) || !empty($_GET['wstoken'])) {
	// automatisches einloggen beim öffnen mit token (vom webservice) verhindern
	define('NO_MOODLE_COOKIES', true);
}

require_once __DIR__.'/inc.php';
require_once $CFG->dirroot.'/webservice/lib.php';

if (empty($CFG->filelifetime)) {
	$lifetime = 86400;	 // Seconds for files to remain in caches
} else {
	$lifetime = $CFG->filelifetime;
}

// disable moodle specific debug messages
//disable_debugging();

$relativepath = get_file_argument('portfoliofile.php'); // the check of the parameter to PARAM_PATH is executed inside get_file_argument
$access = optional_param('access', 0, PARAM_TEXT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$userhash = optional_param('hv', 0, PARAM_ALPHANUM);
// old elove token - moodle sometimes uses wstoken, sometimes token
$token = optional_param('token', null, PARAM_ALPHANUM);
// new token
$wstoken = optional_param('wstoken', $token, PARAM_ALPHANUM);
//block_exaport_epop_checkhash
$epopaccess=false;

//authenticate the user
if ($token) {
        // keep code for eLove - needed for teacher access to submitted files

        $webservicelib = new webservice();
        $authenticationinfo = $webservicelib->authenticate_user($token);
        $accessPath = explode('/', $access);

        if(strpos($accessPath[2],'-'))
                $accessPath[2] = (explode('-', $accessPath[2])[0]);

        $item = block_exaport_get_elove_item($itemid, $accessPath[2], $authenticationinfo);
        if(!$item)
                print_error("viewnotfound", "block_exaport");

        if ($file = block_exaport_get_item_file($item)) {
                send_stored_file($file);
        } else {
                not_found();
        }
        exit;
}

$authenticationinfo = null;
if ($wstoken) {
	$webservicelib = new webservice();
	$authenticationinfo = $webservicelib->authenticate_user($wstoken);
} elseif ($userhash){
	$user=block_exaport_epop_checkhash($userhash);
	if ($user == false) {
		require_login();
} else {
		$epopaccess = true;
	}
} else {
	require_login();
}


if ($itemid) {
	// file storage logic

	if ($epopaccess){
		$item = block_exaport_get_item_epop($itemid, $user);
	} elseif ($access) {
		$item = block_exaport_get_item($itemid, $access, false);
	} elseif (($userid = optional_param('userid', 0, PARAM_INT)) && $authenticationinfo) {
		$item = block_exaport_get_elove_item($itemid, $userid, $authenticationinfo);
	}

	if (!$item) {
		print_error('Item not found');
	}

	if ($commentid = optional_param('commentid', 0, PARAM_INT)) {
		$comment = $DB->get_record("block_exaportitemcomm", ['itemid' => $item->id, 'id' => $commentid]);
		if (!$comment) {
			not_found();
		}
		$file = block_exaport_get_item_comment_file($comment->id);
	} else {
		$file = block_exaport_get_item_file($item);
	}

	if ($file) {
		send_stored_file($file, 1);
	} else {
		not_found();
	}
} else {
	// old logic? still used?
	
	if (!$relativepath) {
		error('No valid arguments supplied or incorrect server configuration');
	} else {
		if ($relativepath{0} != '/') {
		error('No valid arguments supplied, path does not start with slash!');
	}
	}

	// relative path must start with '/', because of backup/restore!!!

	// extract relative path components
	$args = explode('/', trim($relativepath, '/'));

	if( $args[0] != 'exaport') {
		error('No valid arguments supplied');
	}

	if($args[1] == 'temp') {
		if($args[2] == 'export') {
			$args[3] = $access_user_id = clean_param($args[3], PARAM_INT);
			if($access_user_id == $USER->id) {
				// check ok, allowed to access the file
			} else {
				error('No valid arguments supplied');
			}
		} else {
			error('No valid arguments supplied');
		}
	} elseif ($args[1] == 'files') { // in this case the user tries to access a file of a portfolio entry.
		// portfoliofile.php/files/$userid/$portfolioid/filename.ext
		if (isset($args[2]) && isset($args[3])) {
			$args[2] = $access_user_id = clean_param($args[2], PARAM_INT);
			$args[3] = $access_portfolio_id = clean_param($args[3], PARAM_INT);

			if($access_user_id == $USER->id) { // check if this user has a portfolio with id $access_portfolio_id;
				if ($DB->count_records('block_exaportitem', array('userid'=>$USER->id, 'id'=>$access_portfolio_id)) == 1) {
					// check ok, allowed to access the file
				} else {
					error('No valid arguments supplied');
				}
			} else {
				error('No valid arguments supplied');
			}
		} else {
			error('No valid arguments supplied');
		}
	} else {
		error('No valid arguments supplied');
	}

	$filepath = $CFG->dataroot . '/' . implode('/', $args);
}
	
if (!file_exists($filepath)) {
	if(isset($course)) {
		not_found($course->id);
	} else {
		not_found();
	}
}
send_file($filepath, basename($filepath), $lifetime, $CFG->filteruploadedfiles, false, true);

function not_found($courseid = 0) {
	global $CFG;
	header('HTTP/1.0 404 not found');
	if($courseid > 0) {
		error(get_string('filenotfound', 'error'), $CFG->wwwroot.'/course/view.php?id='.$courseid); //this is not displayed on IIS??
	} else {
		error(get_string('filenotfound', 'error')); //this is not displayed on IIS??
	}
}
