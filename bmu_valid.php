<?php
 require_once dirname(__FILE__) . '/inc.php';
 require_once dirname(__FILE__) . '/lib/lib.php';
 require_once dirname(__FILE__) . '/lib/sharelib.php';
 global $DB,$USER,$COURSE;
 
		
	$uname = optional_param('username', 0, PARAM_USERNAME);  //100
	$pword = optional_param('password', 0, PARAM_ALPHANUM);	//32
	
	if ($uname!="0" && $pword!="0"){
		$uname=kuerzen($uname,100);
		$pword=kuerzen($pword,50);

		//todo authentifzierung besser prüfen (deleted....) beispiel moodle webserviceupload???
		$conditions = array("username" => $uname,"password" => $pword);
		if ($user = $DB->get_record("user", $conditions)){
			bmu_write_xml(true);
		}
		else
			bmu_write_xml(false);

	}else{
		bmu_write_xml(false);
	}

function kuerzen($wert,$laenge){
	if (strlen($wert)>$laenge){
		$wert = substr($wert, 0,$laenge ); // gibt "abcd" zurück 
	}
	return $wert;
}
function bmu_write_xml($valid){
	
	header ("Content-Type:text/xml");

	$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
	$inhalt.='<result>';
	if($valid)
		$inhalt.='true';
	else
		$inhalt.='false';
	$inhalt.='</result> '."\r\n";
	echo $inhalt;

}
?>