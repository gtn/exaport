<?php
require_once dirname(__FILE__) . '/inc.php';
require_once dirname(__FILE__) . '/lib/lib.php';
//require_once dirname(__FILE__) . '/lib/moodlelib.php';
require_once dirname(__FILE__) . '/lib/sharelib.php';
global $DB,$USER,$COURSE,$CFG;

$user=checkhash();
$gotologin=false;
if (!$user) $gotologin=true;
else{
	
	if ($user->auth=='nologin' || $user->suspended!=0 || $user->deleted!=0){
		$gotologin=true;
	}else{
			
		if ((!isloggedin())){
			complete_user_login($user);
		}

		//if user is enrolled to a course with exaport, redirect to it
		//else redirect to moodle root
		$mycourses=enrol_get_my_courses();
		$mycourses[] = $DB->get_record('course',array('id'=>1));
		
		foreach($mycourses as $mycourse) {
			//$mycoursecontext = get_context_instance(CONTEXT_COURSE, $mycourse->id);
			$mycoursecontext = context_course::instance($mycourse->id);
			
			if($DB->record_exists('block_instances', array('blockname'=>'exaport', 'parentcontextid'=>$mycoursecontext->id))) {
				redirect($CFG->wwwroot.'/blocks/exaport/view_items.php?courseid='.$mycourse->id);die;
			}
		}
		unset($mycourses);
		unset($mycoursecontext);
		
		redirect($CFG->wwwroot);die;
	}
}
if ($gotologin==true){
	$logurl=get_login_url();
	redirect($CFG->wwwroot);die;
}

function checkhash(){
	global $DB;global $USER;
	$userhash = optional_param('key', 0, PARAM_ALPHANUM);
	$sql="SELECT u.* FROM {user} u INNER JOIN {block_exaportuser} eu ON eu.user_id=u.id WHERE eu.user_hash_long='".$userhash."'";
	if (!$user=$DB->get_record_sql($sql)){
		return false;
	}else{
		$USER=$user;
		return $user;
	}
}

?>