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
		if ($user->auth=='nologin' || $user->firstaccess==0 || $user->suspended!=0 || $user->deleted!=0){
			$gotologin=true;
		}else{
			
			/*$sql="SELECT c.id FROM {course} c INNER JOIN {enrol} e on e.courseid=c.id INNER JOIN {user_enrolments} ue ON ue.enrolid=e.id";
			$sql.=" WHERE c.visible=1 AND ue.userid=".$user->id;
			$sql.=" ORDER BY ue.timemodified DESC LIMIT 0,1";
			if ($rs = $DB->get_record_sql($sql)){*/
					
				if ((!isloggedin())){
					complete_user_login($user);
				}
				redirect($CFG->wwwroot);
				/*
				$courses=enrol_get_my_courses();
				if (!empty($courses) && is_array($courses)){
					$first_key = key($courses);
					redirect($CFG->wwwroot."/blocks/exaport/view.php?courseid=".$first_key);
					
				}else{
					$gotologin=true;
				}*/
			
			
		}
	}
	if ($gotologin==true){
		redirect(get_login_url());
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