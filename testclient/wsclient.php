<?php
// This file is NOT a part of Moodle - http://moodle.org/
//
// This client for Moodle 2 is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//

//$token = '6f8233ff407d082557f51006bd494489'; //flo
$token = '41ec1df3194f653a072a68ac9241b537'; //michy
$domainname = 'http://localhost:1337/moodle26/'; //michy
//$domainname = 'hhtp://localhost:8888/moodle26/'; //flo
/*$functionname = 'block_exaport_get_items';

$params = new stdClass();
$params->level = 1;

$restformat="";
header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
echo "



";
*/
/*$functionname = 'block_exaport_get_item';
$params = new stdClass();
$params->itemid = 2;

$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
*/
/*$functionname = 'block_exaport_get_views';
$params = new stdClass();
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat);
print_r($resp);
*/
/*$functionname = 'block_exaport_get_view';
$params = new stdClass();
$params->id = 2;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
*/
/*$functionname = 'block_exaport_add_view';
$params = new stdClass();
$params->name = "wstestview";
$params->description = "das ist eine view, die über ein Webservice angelegt wurde.";
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
*/
/*$functionname = 'block_exaport_update_view';
$params = new stdClass();
$params->id = 5;
$params->name = "wstestview";
$params->description = "das ist eine mit einer ganz neuen beschreibung aber gleichem namen";
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);

$functionname = 'block_exaport_update_view';
$params = new stdClass();
$params->id = 2;
$params->name = "geänderter name";
$params->description = "beides wurde hier über werbservice geändert";
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
*/
$functionname = 'block_exaport_delete_view';
$params = new stdClass();
$params->id = 5;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);



/*
echo "



";

$functionname = 'block_exaport_add_item';
$params = new stdClass();
$params->title = "ws testnotiz";
$params->categoryid = 0;
$params->url = "";
$params->intro = "";
$params->url = "";
$params->type = "note";
$params->filename = "";

$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);


echo "



";
// BEFORE ADDING FILE ITEM THE UPLOADFILE.PHP Script needs to be called!!

$functionname = 'block_exaport_add_item';
$params = new stdClass();
$params->title = "ws testfile";
$params->categoryid = 0;
$params->url = "";
$params->intro = "";
$params->url = "";
$params->type = "file";
$params->filename = "surf.jpg";

$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);

*/
/*
$functionname = 'block_exaport_get_item';
$params = new stdClass();
$params->itemid = 7;

$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);*/
