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

// $token = '6f8233ff407d082557f51006bd494489'; // Flo.
$token = '41ec1df3194f653a072a68ac9241b537'; // Michy.
$domainname = 'http://localhost/moodle271/';

require_once('./curl.php');
$curl = new curl;

$restformat = "";

$serverurl = 'http://localhost/moodle271/login/token.php?username=schueler&password=Schueler123!&service=exaportservices';
$resp = $curl->get($serverurl);
$resp = json_decode($resp)->token;
$token = $resp;

$functionname = 'block_exaport_get_items';

$params = new stdClass();
$params->level = 0;

header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token . '&wsfunction=' . $functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
echo "



";

$functionname = 'block_exaport_get_competencies_by_item';
$params = new stdClass();
$params->itemid = 2;
$restformat = "";
$serverurl = $domainname . '/webservice/rest/server.php' . '?wstoken=' . $token . '&wsfunction=' . $functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
/*
$functionname = 'block_exaport_add_item';

$params = new stdClass();
$params->title = "ws testnote";
$params->categoryid = 0;
$params->url = "";
$params->intro = "testnote";
$params->url = "";
$params->type = "note";
$params->filename = "";

header('Content-Type: text/plain');
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
echo "



";*/

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
/*$functionname = 'block_exaport_delete_view';
$params = new stdClass();
$params->id = 5;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);*/
/*$functionname = 'block_exaport_get_all_items';

$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat);
print_r($resp);
/*$functionname = 'block_exaport_add_view_item';
$params = new stdClass();
$params->viewid = 3;
$params->itemid = 2;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
*//*$functionname = 'block_exaport_delete_view_item';
$params = new stdClass();
$params->viewid = 3;
$params->itemid = 2;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);*/
/*$functionname = 'block_exaport_view_grant_external_access';
$params = new stdClass();
$params->id = 3;
$params->val = 1;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);*/
/*$functionname = 'block_exaport_view_grant_internal_access_all';
$params = new stdClass();
$params->id = 3;
$params->val = 0;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);*/
/*
$functionname = 'block_exaport_view_grant_internal_access';
$params = new stdClass();
$params->viewid = 3;
$params->userid = 15;
$params->val = 1;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);

/*$functionname = 'block_exaport_view_get_available_users';
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat);
print_r($resp);
*//*
echo "



";
*/
/*
$functionname = 'block_exaport_update_item';
$params = new stdClass();
$params->id = 2;
$params->title = "ws testlinkänderung";
$params->categoryid = 0;
$params->url = "www.iwas.com";
$params->intro = "das ist das intro";
$params->type = "link";
$params->filename = "";

$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);


echo "



";
*/
/*$functionname = 'block_exaport_list_competencies';
$params = new stdClass();
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat);
print_r($resp);
*/
/*
$functionname = 'block_exaport_set_item_competence';
$params = new stdClass();
$params->itemid = 5;
$params->descriptorid = 1018;
$params->val = 1;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
/*$functionname = 'block_exaport_delete_item';
$params = new stdClass();
$params->id = 6;
$restformat="";
$serverurl = $domainname . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$functionname;
require_once('./curl.php');
$curl = new curl;
$resp = $curl->post($serverurl . $restformat, $params);
print_r($resp);
/*
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
