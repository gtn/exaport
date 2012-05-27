<?php 
/***************************************************************
*  Copyright notice
*
*  (c) 2006 exabis internet solutions <info@exabis.at>
*  All rights reserved
*
*  You can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This module is based on the Collaborative Moodle Modules from
*  NCSA Education Division (http://www.ncsa.uiuc.edu)
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

// Syntax:
// Files in the portfolio:
// portfoliofile.php/files/$userid/$portfolioid/filename.ext
// Exported SCORM-File (user has to be logged in)
// portfoliofile.php/temp/export/$userid/filename.ext

require_once dirname(__FILE__).'/inc.php';
require_once dirname(__FILE__).'/lib/sharelib.php';

if (empty($CFG->filelifetime)) {
	$lifetime = 86400;     // Seconds for files to remain in caches
} else {
	$lifetime = $CFG->filelifetime;
}

// disable moodle specific debug messages
//disable_debugging();

$relativepath = get_file_argument('portfoliofile.php'); // the check of the parameter to PARAM_PATH is executed inside get_file_argument
$access = optional_param('access', 0, PARAM_TEXT);
$itemid = optional_param('att', 0, PARAM_INT);
$id = optional_param('itemid', 0, PARAM_INT);
require_login();


if ($access && $itemid && $id) {
        $item = block_exaport_get_item($id, $access);
	if (!$item || ($item->type != 'file') || !$item->attachment) {
            print_error('No valid arguments supplied');
	}
        //Berechtigung checken

	$fs = get_file_storage();
        $context = get_context_instance(CONTEXT_SYSTEM);

        $hash = get_hash($itemid);
         $file = $fs->get_file_by_hash($hash);

        // Read contents
        if ($file) {
                send_stored_file($file);
        } else {
                not_found();
        }

} else {
	if (!$relativepath) {
		error('No valid arguments supplied or incorrect server configuration');
	} else if ($relativepath{0} != '/') {
		error('No valid arguments supplied, path does not start with slash!');
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
			}
			else {
				error('No valid arguments supplied');
			}
		}
		else {
			error('No valid arguments supplied');
		}
	}
	elseif ($args[1] == 'files') { // in this case the user tries to access a file of a portfolio entry.
		// portfoliofile.php/files/$userid/$portfolioid/filename.ext
		if (isset($args[2]) && isset($args[3])) {
			$args[2] = $access_user_id = clean_param($args[2], PARAM_INT);
			$args[3] = $access_portfolio_id = clean_param($args[3], PARAM_INT);

			if($access_user_id == $USER->id) { // check if this user has a portfolio with id $access_portfolio_id;
				if(count_records('block_exaportitem', 'userid', $USER->id, 'id', $access_portfolio_id) == 1) {
					// check ok, allowed to access the file
				}
				else {
					error('No valid arguments supplied');
				}
			}
			else {
				error('No valid arguments supplied');
			}
		}
		else {
			error('No valid arguments supplied');
		}
	}
	else {
		error('No valid arguments supplied');
	}

	$filepath = $CFG->dataroot . '/' . implode('/', $args);
}
	
if (!file_exists($filepath)) {
	if(isset($course)) {
		not_found($course->id);
	}
	else {
		not_found();
	}
}
send_file($filepath, basename($filepath), $lifetime, $CFG->filteruploadedfiles, false, true);
/*
$fs = get_file_storage();
$context = get_context_instance(CONTEXT_SYSTEM);
// Prepare file record object

//$file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'], 
//         $fileinfo['filepath'], $fileinfo['filename']);

$hash = get_hash($itemid);
 $file = $fs->get_file_by_hash($hash);
 
// Read contents
if ($file) {
	send_stored_file($file);
} else {
	not_found();
}
*/
// ========================================
// finally send the file
// ========================================
//session_write_close(); // unlock session during fileserving

// portfoliofile serves user submitted data, so $forcedownload is set to 1


function get_hash($itemid) {
	global $DB;

    if ($file_record = $DB->get_record_sql("select min(id), pathnamehash from {files} where itemid={$itemid} AND filename!='.' GROUP BY id, pathnamehash")) {
        return $file_record->pathnamehash;
    } else {
        return false;
    }
}
function not_found($courseid = 0) {
	global $CFG;
	header('HTTP/1.0 404 not found');
	if($courseid > 0) {
		error(get_string('filenotfound', 'error'), $CFG->wwwroot.'/course/view.php?id='.$courseid); //this is not displayed on IIS??
	}
	else {
		error(get_string('filenotfound', 'error')); //this is not displayed on IIS??
	}
}
