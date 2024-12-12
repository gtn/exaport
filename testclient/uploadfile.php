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

// GLOBAL SETTINGS - CHANGE THEM !
$token = 'c20e48d5114cb56363084d24c4d15da4';
$domainname = 'http://localhost:8888/moodle26/';

// UPLOAD PARAMETERS.
// Note: check "Maximum uploaded file size" in your Moodle "Site Policies".
$imagepath = './surf.jpg'; // CHANGE THIS !
$filepath = '/'; // Put the file to the root of your private file area. //OPTIONAL.

require_once('./curl.php');
// UPLOAD IMAGE - Moodle 2.1 and later.
$params = array('file_box' => "@" . $imagepath, 'filepath' => $filepath, 'token' => $token);
$ch = curl_init();
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
curl_setopt($ch, CURLOPT_URL, $domainname . '/webservice/upload.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
$response = curl_exec($ch);

/* =========================================================================

// DOWNLOAD PARAMETERS
// Note: The service associated to the user token must allow "file download" !
// in the administration, edit the service to check the setting (click "advanced" button on the edit page).

// Normally you retrieve the file download url from calling the web service core_course_get_contents()
// However to be quick to demonstrate the download call,
// you are going to retrieve the file download url manually:
// 1- In Moodle, create a forum with an attachement
// 2- look at the attachement link url, and copy everything after http://YOURMOODLE/pluginfile.php
// into the above variable
$relativepath = '/20/mod_forum/attachment/1/S8%20-%20Week%205%20-%20Thursday.pdf'; //CHANGE THIS !

// CHANGE THIS ! This is where you will store the file.
// Don't forget to allow 'write permission' on the folder for your web server.
$path = '/computerroot/.../sample-ws-clients/PHP-HTTP-filehandling/S8 - Week 5 - Thursday.pdf';

// DOWNLOAD IMAGE - Moodle 2.2 and later
$url  = $domainname . '/webservice/pluginfile.php' . $relativepath;
// NOTE: normally you should get this download url from your previous call of core_course_get_contents()
$tokenurl = $url . '?token=' . $token; //NOTE: in your client/app don't forget to attach the token to your download url
$fp = fopen($path, 'w');
$ch = curl_init($tokenurl);
curl_setopt($ch, CURLOPT_FILE, $fp);
$data = curl_exec($ch);
curl_close($ch);
fclose($fp);
*/
