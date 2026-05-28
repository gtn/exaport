<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

require_once(__DIR__ . '/inc.php');

// Syntax:
// Files in the portfolio:
// portfoliofile.php/files/$userid/$portfolioid/filename.ext
// Exported SCORM-File (user has to be logged in)
// portfoliofile.php/temp/export/$userid/filename.ext.
$token = optional_param('token', null, PARAM_RAW);
$wstoken = optional_param('token', null, PARAM_RAW);
if (!$token || !$wstoken) {
    // Automatisches einloggen beim öffnen mit token (vom webservice) verhindern.
    @define('NO_MOODLE_COOKIES', true);
}

require_once($CFG->dirroot . '/webservice/lib.php');

if (empty($CFG->filelifetime)) {
    $lifetime = 86400;     // Seconds for files to remain in caches.
} else {
    $lifetime = $CFG->filelifetime;
}

// needed, because diggr-plus is loading files via xhr
header("Access-Control-Allow-Origin: *");

// The check of the parameter to PARAM_PATH is executed inside get_file_argument.
$relativepath = get_file_argument('portfoliofile.php');
$access = optional_param('access', 0, PARAM_TEXT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$inst = optional_param('inst', 0, PARAM_ALPHANUM);
$userhash = optional_param('hv', 0, PARAM_ALPHANUM);
// Old elove token - moodle sometimes uses wstoken, sometimes token.
$token = optional_param('token', null, PARAM_ALPHANUM);
// New token.
$wstoken = optional_param('wstoken', $token, PARAM_ALPHANUM);
$download = optional_param('download', true, PARAM_BOOL);

// Block_exaport_epop_checkhash.
$epopaccess = false;

// Authenticate the user.
if ($token) {
    // Keep code for eLove - needed for teacher access to submitted files.
    $webservicelib = new webservice();
    $authenticationinfo = $webservicelib->authenticate_user($token);
    $accesspath = explode('/', $access);

    if (strpos($accesspath[2], '-')) {
        $accesspath[2] = (explode('-', $accesspath[2])[0]);
    }

    $item = block_exaport_get_item_for_webservice($itemid, $accesspath[2], $authenticationinfo['user']->id);
    if (!$item) {
        print_error("viewnotfound", "block_exaport");
    }


    if ($file = block_exaport_get_item_single_file($item)) {
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
} else if ($userhash) {
    $user = block_exaport_epop_checkhash($userhash);
    if ($user == false) {
        require_login();
    } else {
        $epopaccess = true;
    }
} else {
    require_login();
}

if ($itemid) {
    // File storage logic.
    if ($epopaccess) {
        $item = block_exaport_get_item_epop($itemid, $user);
    } else if ($access) {
        $item = block_exaport_get_item($itemid, $access, false);
    } else if (($userid = optional_param('userid', 0, PARAM_INT)) && $authenticationinfo) {
        $item = block_exaport_get_item_for_webservice($itemid, $userid, $authenticationinfo['user']->id);
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
        $files = block_exaport_get_item_files_array($item);

        if ($inst && !empty($files[$inst])) {
            $file = $files[$inst];
        } else {
            // fallback: always get first file
            $file = reset($files);
        }
    }

    if ($file) {
        // TODO: check this code
        $mimetype = $file->get_mimetype();
        if (strpos($mimetype, 'html') !== false || strpos($mimetype, 'text') === true) { // clean HTML ; TODO: 'text' - do we need?
            $tempfilecontent = $file->get_content();
            $tempfilecontent = clean_text($tempfilecontent, FORMAT_HTML, ['newlines' => false]);
            readstring_accel($tempfilecontent, $mimetype, false);
            die;
        }


        $as_pdf = optional_param('as_pdf', false, PARAM_BOOL);
        if ($as_pdf && class_exists('\block_exacomp\api')) {
            \block_exacomp\api::send_stored_file_as_pdf($file, $download);
        }

        send_stored_file($file, null, 0, $download);
    } else {
        not_found();
    }
} else {
    // Old logic? still used?
    if (!$relativepath) {
        error('No valid arguments supplied or incorrect server configuration');
    } else {
        if ($relativepath[0] != '/') {
            error('No valid arguments supplied, path does not start with slash!');
        }
    }
    // Relative path must start with '/', because of backup/restore!!!

    // Extract relative path components.
    $args = explode('/', trim($relativepath, '/'));

    if ($args[0] != 'exaport') {
        error('No valid arguments supplied');
    }
    if ($args[1] == 'temp') {
        if ($args[2] == 'export') {
            $args[3] = $accessuserid = clean_param($args[3], PARAM_INT);
            if ($accessuserid == $USER->id) {
                $tempvar = 1; // For code checker;
                // Check ok, allowed to access the file.
            } else {
                error('No valid arguments supplied');
            }
        } else {
            error('No valid arguments supplied');
        }
    } else if ($args[1] == 'files') {
        // In this case the user tries to access a file of a portfolio entry.
        // portfoliofile.php/files/$userid/$portfolioid/filename.ext.
        if (isset($args[2]) && isset($args[3])) {
            $args[2] = $accessuserid = clean_param($args[2], PARAM_INT);
            $args[3] = $accessportfolioid = clean_param($args[3], PARAM_INT);

            if ($accessuserid == $USER->id) { // Check if this user has a portfolio with id $access_portfolio_id;.
                if ($DB->count_records('block_exaportitem', array('userid' => $USER->id, 'id' => $accessportfolioid)) == 1) {
                    $tempvar = 1; // For code checker;
                    // Check ok, allowed to access the file.
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
    if (isset($course)) {
        not_found($course->id);
    } else {
        not_found();
    }
}
send_file($filepath, basename($filepath), $lifetime, $CFG->filteruploadedfiles, false, true);

function not_found($courseid = 0) {
    global $CFG;
    header('HTTP/1.0 404 not found');
    // if ($courseid > 0) {
    //error(get_string('filenotfound', 'error'), $CFG->wwwroot.'/course/view.php?id='.$courseid); // This is not displayed on IIS?
    // } else {
    // error(get_string('filenotfound', 'error')); // This is not displayed on IIS??
    //}
    print_error("filenotfound", "block_exaport");
}
