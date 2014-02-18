<?php
// This file is NOT a part of Moodle - http://moodle.org/
//
// This client for Moodle 2 is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//

$token = 'e315bd823ce52bec96f44b4b5845168a';
$domainname = 'http://gtn02.gtn-solutions.com/moodle25';
$functionname = 'block_exaport_get_childcategories';

$params = new stdClass();
$params->pid = 38;


/// REST CALL BLOCK_EXACOMP_GET_SUBJECTS





$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);

echo "



";


