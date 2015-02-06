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

function block_exaport_get_external_view_url(stdClass $view,$userid=-1)
{
	global $CFG, $USER;
	if ($userid==-1) $userid=$USER->id; //bei epop wird userid mitgegeben, sonst aus global USER holen
	return $CFG->wwwroot.'/blocks/exaport/shared_view.php?access=hash/'.$userid.'-'.$view->hash;
}

function block_exaport_get_user_from_access($access,$epopaccess=false)
{
	global $CFG, $USER, $DB;

	$accessPath = explode('/', $access);
	if (count($accessPath) != 2)
		return;
	
	if ($accessPath[0] == 'hash') {
		$hash = $accessPath[1];
		
		$conditions = array("user_hash" => $hash);
		if (!$portfolioUser = $DB->get_record("block_exaportuser", $conditions)) {
			// no portfolio user with this hash
			return;
		}
		$conditions = array("id" => $portfolioUser->user_id);
		if (!$user = $DB->get_record("user", $conditions)) {
			// user not found
			return;
		}

		// keine rechte �berpr�fung, weil �ber den hash user immer erreichbar ist aber nur die geshareten items angezeigt werden
		// vielleicht in zukunft eine externaccess eingenschaft f�r den user einf�gen?

		$user->access = new stdClass();
		$user->access->request = 'extern';
		return $user;
	} elseif ($accessPath[0] == 'id') {
		// guest not allowed
		// require exaport:use -> guest hasn't this right
		$context = context_system::instance();
		if ($epopaccess==false)	require_capability('block/exaport:use', $context);

		$userid = $accessPath[1];
		
		$conditions = array("user_id" => $userid);
		if (!$portfolioUser = $DB->get_record("block_exaportuser", $conditions)) {
			// no portfolio user with this id
			return;
		}
		
		$conditions = array("id" => $portfolioUser->user_id);
		if (!$user = $DB->get_record("user", $conditions)) {
			// user not found
			return;
		}

		// no more checks needed

		$user->access = new stdClass();
		$user->access->request = 'intern';
		return $user;
	}
}


function block_exaport_get_view_from_access($access)
{
	global $CFG, $USER, $DB;

	if (!block_exaport_feature_enabled('views')) {
		// only allowed if views are enabled
		return;
	}

	$accessPath = explode('/', $access);
	if (count($accessPath) != 2)
		return;

	$view = null;
	
	if ($accessPath[0] == 'hash') {
		$hash = $accessPath[1];
		$hash = explode('-', $hash);

		if (count($hash) != 2)
			return;

	    $userid = clean_param($hash[0], PARAM_INT);
	    $hash =  clean_param($hash[1], PARAM_ALPHANUM);
		//$userid = $hash[0];
		//$hash = $hash[1];
		
		if (empty($userid) || empty($hash)) {
			return;
		}
		$conditions = array("userid" => $userid, "hash" => $hash, "externaccess" => 1);
		if (!$view = $DB->get_record("block_exaportview", $conditions)) {
			// view not found
			return;
		}
		
		
		$view->access = new stdClass();
		$view->access->request = 'extern';
	} elseif ($accessPath[0] == 'id') {
		// guest not allowed
		// require exaport:use -> guest hasn't this right
		$context = context_system::instance();
		require_capability('block/exaport:use', $context);

		$hash = $accessPath[1];
		$hash = explode('-', $hash);

		if (count($hash) != 2)
			return;
	
	    $userid = clean_param($hash[0], PARAM_INT);
	    $viewid =  clean_param($hash[1], PARAM_INT);
		//$userid = $hash[0];
		//$viewid = $hash[1];
		
		$view = $DB->get_record_sql("SELECT v.* FROM {block_exaportview} v".
							" LEFT JOIN {block_exaportviewshar} vshar ON v.id=vshar.viewid AND vshar.userid=?".
							" WHERE v.userid=? AND v.id=? AND".
							" ((v.userid=?)". // myself
							"  OR (v.shareall=1)". // shared all
							"  OR (v.shareall=0 AND vshar.userid IS NOT NULL))", array($USER->id, $userid, $viewid, $USER->id)); // shared for me

		if (!$view) {
			// view not found
			return;
		}

		$view->access = new stdClass();
		$view->access->request = 'intern';
	}

	return $view;
}
function block_exaport_get_item_epop($id,$user){
	global $DB;
	$sql="SELECT i.* FROM {block_exaportitem} i WHERE id=? AND userid=?";		
	//echo $sql;die;			 
	if (!$item=$DB->get_record_sql($sql, array($id, $user->id))){
		return false;
	}else{
		return $item;
	}
}

function block_exaport_epop_checkhash($userhash){
	global $DB;
	
	$sql="SELECT u.* FROM {user} u INNER JOIN {block_exaportuser} eu ON eu.user_id=u.id WHERE eu.user_hash_long=?";					 
//echo $sql;die;
	if (!$user=$DB->get_record_sql($sql, array($userhash))){
		return false;
	}else{
		return $user;
	}
}

function block_exaport_get_item($itemid, $access, $epopaccess=false)
{
	global $CFG, $USER, $DB;

	$itemid = clean_param($itemid, PARAM_INT);
	
	$item = null;
	if (preg_match('!^view/(.+)$!', $access, $matches)) {
		// in view mode

		if (!$view = block_exaport_get_view_from_access($matches[1])) {
			print_error("viewnotfound", "block_exaport");
		}
		//Parameter richtig?!
		//$conditions = array("viewid" => $view->id, "type" => 'item', "itemid" => $itemid);
		if(strcmp($CFG->dbtype, "sqlsrv")==0){
			$sql = "SELECT b.* FROM {block_exaportviewblock} b
					WHERE b.viewid=? AND
					b.itemid=? AND
					CAST(b.type AS varchar) = 'item'
					LIMIT 1";
		}
        else{
        	$sql = "SELECT b.* FROM {block_exaportviewblock} b
          			WHERE b.viewid=? AND
          			b.itemid=? AND
          			b.type = 'item'
					LIMIT 1";
        }
								     
		$viewblock = $DB->get_record_sql($sql, array($view->id, $itemid)); // nobody, but me
		if(!$viewblock) {						
			// item not linked to view -> no rights
                }
		$conditions = array("id" => $itemid, "userid" => $view->userid);
		if (!$item = $DB->get_record("block_exaportitem", $conditions)) {
			// item not found
                                                echo 'pfeift';
			return;
		}

		$item->access = $view->access;
		$item->access->page = 'view';

		// comments allowed?
		if ($item->access->request == 'extern') {
			$item->allowComments = false;
			$item->showComments = block_exaport_external_comments_enabled() && $view->externcomment;
		} else {
			$item->allowComments = true;
			$item->showComments = true;
		}

	} elseif (preg_match('!^portfolio/(.+)$!', $access, $matches)) {
		// in user portfolio mode

		if (!$user = block_exaport_get_user_from_access($matches[1],$epopaccess)) {
			
			return;
		}

		if ($user->access->request == 'extern') {
			$conditions = array("id" => $itemid, "userid" => $user->id);
			if (!$item = $DB->get_record("block_exaportitem", $conditions, "externaccess", 1)) {
				// item not found
				return;
			}
		} else {
			// intern

			$item = $DB->get_record_sql("SELECT i.* FROM {block_exaportitem} i".
								" LEFT JOIN {block_exaportitemshar} ishar ON i.id=ishar.itemid AND ishar.userid=?".
								" WHERE i.id=? AND".
								" ((i.userid=?)". // myself
								"  OR (i.shareall=1 AND ishar.userid IS NULL)". // all and ishar not set?
								"  OR (i.shareall=0 AND ishar.userid IS NOT NULL))", array($USER->id, $itemid, $USER->id)); // nobody, but me

			if (!$item) {
				// item not found
				return;
			}
		}

		$item->access = $user->access;
		$item->access->page = 'portfolio';

		// comments allowed?
		if ($item->access->request == 'extern') {
			$item->allowComments = false;
			$item->showComments = $item->externcomment;
		} else {
			$item->allowComments = true;
			$item->showComments = true;
		}
	} else {
		return;
	}

	$item->access->access = $access;
	$item->access->parentAccess = substr($item->access->access, strpos($item->access->access, '/')+1);

	return $item;
}


function exaport_get_shareable_courses() {
	global $USER, $COURSE;

	$courses = array();

	// loop through all my courses
	foreach (get_my_courses($USER->id, 'fullname ASC') as $dbCourse) {

		$course = array(
			'id' => $dbCourse->id,
			'fullname' => $dbCourse->fullname
		);

		$courses[$course['id']] = $course;
	}

	// move active course to first position
	if (isset($courses[$COURSE->id])) {
		$course = $courses[$COURSE->id];
		unset($courses[$COURSE->id]);
		$courses = array_merge(array($course['id']=>$course), $courses);
	}

	return $courses;
}

function exaport_get_view_shared_users($viewid) {
	global $DB;
	
	$sharedUsers = $DB->get_records_menu('block_exaportviewshar', array("viewid" => $viewid), null, 'userid, userid AS tmp');
	return $sharedUsers;
}

function exaport_get_shareable_courses_with_users_for_view($viewid) {
	global $DB;
	
	$sharedUsers = exaport_get_view_shared_users($viewid);
	$courses = exaport_get_shareable_courses_with_users('sharing');
	
	foreach ($courses as $course) {
		foreach ($course->users as $user) {
			if (isset($sharedUsers[$user->id])) {
				$user->shared_to = true;
				unset($sharedUsers[$user->id]);
			} else {
				$user->shared_to = false;
			}
		}
	}

	if ($sharedUsers) {
		$extraUsers = array();
		
		foreach ($sharedUsers as $userid) {
			$user = $DB->get_record('user', array('id' => $userid), user_picture::fields());
			if (!$user)
				// doesn't exist anymore
				continue;

			$extraUsers[] = (object)array(
				'id' => $user->id,
				'name' => fullname($user),
				'rolename' => '',
				'shared_to' => true
			);
		}

		array_unshift($courses, (object)array(
			'id' => -1,
			'fullname' => get_string('other_users_course', 'block_exaport'),
			'users' => $extraUsers
		));
	}
	
	return $courses;
}

function exaport_get_shareable_courses_with_users($type) {
	global $USER, $COURSE;

	$courses = array();
//, 'suspended' => 0, 'deleted' => 0
	// loop through all my courses
	foreach (enrol_get_my_courses(null, 'fullname ASC') as $dbCourse) {

		$course = (object)array(
			'id' => $dbCourse->id,
			'fullname' => $dbCourse->fullname,
			'users' => array()
		);
		
		$context = context_course::instance($dbCourse->id);
		$roles = get_roles_used_in_context($context);
		//print_r($roles);
		
		foreach ($roles as $role) {
			$users = get_role_users($role->id, $context, false, user_picture::fields('u'), null, true, '', '', '', ' deleted=0 AND suspended=0');
			if (!$users) {
				continue;
			}

			foreach ($users as $user) {
				if ($user->id == $USER->id)
					continue;

				$course->users[$user->id] = (object)array(
					'id' => $user->id,
					'name' => fullname($user),
					'rolename' => $role->name ? $role->name : $role->shortname
				);
			}
		}

		$courses[$course->id] = $course;
	}

	// move active course to first position
	if (isset($courses[$COURSE->id]) && ($type != 'shared_views')) {
		$course = $courses[$COURSE->id];
		unset($courses[$COURSE->id]);
		$courses = array_merge(array($course->id=>$course), $courses);
	}
	
	// test courses
	/*
	$courses[] = array(
		'id' => 1004,
		'fullname' => 'test 4',
		'users' => array(
			array(
				'id' => 100001,
				'name' => 'non existing 100001',
				'rolename' => ''
			),
			array(
				'id' => 100002,
				'name' => 'non existing 100002',
				'rolename' => ''
			),
			array(
				'id' => 100003,
				'name' => 'non existing 100003',
				'rolename' => ''
			),
		)
	);
	$courses[] = array(
		'id' => 1005,
		'fullname' => 'test 5',
		'users' => array(
			array(
				'id' => 100001,
				'name' => 'non existing 100001',
				'rolename' => ''
			),
		)
	);
	$courses[] = array(
		'id' => 1006,
		'fullname' => 'test 6',
		'users' => array(
			array(
				'id' => 100005,
				'name' => 'non existing 100005',
				'rolename' => ''
			),
			array(
				'id' => 100001,
				'name' => 'non existing 100001',
				'rolename' => ''
			),
			array(
				'id' => 100006,
				'name' => 'non existing 100006',
				'rolename' => ''
			),
		)
	);
	*/

	return $courses;
}

function exaport_get_extern_access($userid) {
	$userpreferences = block_exaport_get_user_preferences($userid);
   	return "extern.php?id={$userpreferences->user_hash}";
}

function exaport_print_js() {
    echo "<script type=\"text/javascript\">\n";
    echo "<!--\n";
    echo "function SetAllCheckBoxes(FormName, FieldName, CheckValue)\n";
    echo "{\n";
    echo "	if(!document.getElementById(FormName))\n";
    echo "		return;\n";
    echo "	var objCheckBoxes = document.getElementById(FormName).elements[FieldName];\n";
    echo "	if(!objCheckBoxes)\n";
    echo "		return;\n";
    echo "	var countCheckBoxes = objCheckBoxes.length;\n";
    echo "	if(!countCheckBoxes)\n";
    echo "		objCheckBoxes.checked = CheckValue;\n";
    echo "	else\n";
    echo "		// set the check value for all check boxes\n";
    echo "		for(var i = 0; i < countCheckBoxes; i++)\n";
    echo "			objCheckBoxes[i].checked = CheckValue;\n";
    echo "      if (CheckValue == true)\n";
    echo "              document.getElementById(FormName).selectall.value = \"1\";\n";
    echo "      else\n";
    echo "              document.getElementById(FormName).selectall.value = \"0\";\n";
    echo "}\n";
    echo "// -->\n";
    echo "</script>\n";
}
