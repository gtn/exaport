<?php
 require_once dirname(__FILE__) . '/inc.php';
 require_once dirname(__FILE__) . '/lib/lib.php';
 global $DB;
 
$action = optional_param('action', 0, PARAM_ALPHANUMEXT);  //100
echo $action;die;
if ($action=="login"){
	$uname = optional_param('username', 0, PARAM_USERNAME);  //100
	$pword = optional_param('password', 0, PARAM_ALPHANUM);	//32
	
	if ($uname!="0" && $pword!="0"){
		$uname=kuerzen($uname,100);
		$pword=kuerzen($pword,50);
	
		//todo authentifzierung besser prüfen (deleted....) beispiel moodle webserviceupload???
		$conditions = array("username" => $uname,"password" => $pword);
		if (!$user = $DB->get_record("user", $conditions)){
		echo "key=0";
			}else{
		echo "key=".md5($user->id);
			};
	}else{
		echo "key=0";
	}
}
else if ($action=="child_categories"){
	
	$catid = optional_param('catid', 0, PARAM_INT); 
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$conditions = array("userid" => $user->id,"pid" => $catid);
		print_r($conditions);
		write_xml_categories($conditions);
	}
}
else if ($action=="newCat"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$parent_cat = optional_param('parent_cat', 0, PARAM_INT);
		$catname = optional_param('name', ' ', PARAM_ALPHANUMEXT);
		if ($newid = $DB->insert_record('block_exaportcate', array("pid"=>$parent_cat,"userid"=>$user->id,"name"=>$catname,"timemodified"=>time()))) {
			echo $newid;
		}else{
			echo "-1";
		}
	}
}else if ($action=="parent_categories"){
	
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		header ("Content-Type:text/xml");
		$catid = optional_param('catid', 0, PARAM_INT);
		$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
		$inhalt.='<result>'."\r\n";

		if ($category = $DB->get_record("block_exaportcate",  array("id"=>$catid))){
			if ($categoryp = $DB->get_record("block_exaportcate",  array("id"=>$category->pid))){
				if ($categoryp->pid!=0){
					if ($categoryp2 = $DB->get_record("block_exaportcate",  array("id"=>$category->pid))){
						
					}
				else{
					$inhalt.='<categorie name="'.$categoryp->name.'" catid="'.$categoryp->id.'">'.$category->pid.'</categorie>'."\r\n";
				}
			}else{
				$inhalt.='<categorie name="no result" catid="-1">-1</categorie>'."\r\n";
			}
		}else{
			$inhalt.='<categorie name="no result" catid="-1">-1</categorie>'."\r\n";
		}
		$inhalt.='</result> '."\r\n";
		echo $inhalt;
	}
}else if ($action=="upload"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$filepath="/";
		$title = addslashes(optional_param('title', 0, PARAM_TEXT));
		$description = addslashes(optional_param('description', 0, PARAM_TEXT));
		//print_r($_FILES);
	
	

		if(1==1){
			$fs = get_file_storage();
			$totalsize = 0;
			$context = get_context_instance(CONTEXT_USER);
			foreach ($_FILES as $fieldname=>$uploaded_file) {
		    // check upload errors
		    if (!empty($_FILES[$fieldname]['error'])) {
		        switch ($_FILES[$fieldname]['error']) {
		        case UPLOAD_ERR_INI_SIZE:
		            throw new moodle_exception('upload_error_ini_size', 'repository_upload');
		            break;
		        case UPLOAD_ERR_FORM_SIZE:
		            throw new moodle_exception('upload_error_form_size', 'repository_upload');
		            break;
		        case UPLOAD_ERR_PARTIAL:
		            throw new moodle_exception('upload_error_partial', 'repository_upload');
		            break;
		        case UPLOAD_ERR_NO_FILE:
		            throw new moodle_exception('upload_error_no_file', 'repository_upload');
		            break;
		        case UPLOAD_ERR_NO_TMP_DIR:
		            throw new moodle_exception('upload_error_no_tmp_dir', 'repository_upload');
		            break;
		        case UPLOAD_ERR_CANT_WRITE:
		            throw new moodle_exception('upload_error_cant_write', 'repository_upload');
		            break;
		        case UPLOAD_ERR_EXTENSION:
		            throw new moodle_exception('upload_error_extension', 'repository_upload');
		            break;
		        default:
		            throw new moodle_exception('nofile');
		        }
		    }
		    $file = new stdClass();
		    $file->filename = clean_param($_FILES[$fieldname]['name'], PARAM_FILE);
		    // check system maxbytes setting
		    if (($_FILES[$fieldname]['size'] > get_max_upload_file_size($CFG->maxbytes))) {
		        // oversize file will be ignored, error added to array to notify
		        // web service client
		        $file->errortype = 'fileoversized';
		        $file->error = get_string('maxbytes', 'error');
		    } else {
		        $file->filepath = $_FILES[$fieldname]['tmp_name'];
		        // calculate total size of upload
		        $totalsize += $_FILES[$fieldname]['size'];
		    }
		    $files[] = $file;
			}
		
			//$fs = get_file_storage();
			
			$usedspace = 0;
			$privatefiles = $fs->get_area_files($context->id, 'user', 'draft', false, 'id', false);
			foreach ($privatefiles as $file) {
			    $usedspace += $file->get_filesize();
			}
			if ($totalsize > ($CFG->userquota - $usedspace)) {
		    throw new file_exception('userquotalimit');
			}
			$results = array();
			
			foreach ($files as $file) {
		    if (!empty($file->error)) {
		        // including error and filename
		        $results[] = $file;
		        continue;
		    }
		    $file_record = new stdClass;
		    $file_record->component = 'user';
		    $file_record->contextid = $context->id;
		    $file_record->userid    = $user->id;
		    $file_record->filearea  = 'private';
		    $file_record->filename = $file->filename;
		    $file_record->filepath  = $filepath;
		    $file_record->itemid    = 0;
		    $file_record->license   = $CFG->sitedefaultlicense;
		    $file_record->author    = $user->lastname." ".$user->firstname;
		    $file_record->source    = '';
		
		    //Check if the file already exist
		    $existingfile = $fs->file_exists($file_record->contextid, $file_record->component, $file_record->filearea,
		                $file_record->itemid, $file_record->filepath, $file_record->filename);
		    if ($existingfile) {
		        $file->errortype = 'filenameexist';
		        $file->error = "die datei existiert bereits";
		        $results[] = $file;
		    } else {
		    	
		        if ($newfile = $fs->create_file_from_pathname($file_record, $file->filepath)){
		        	 $DB->set_field("files","itemid",$newfile->get_id(),array("id"=>$newfile->get_id()));
		
		        	 $new = new stdClass();
		           $new->userid = $user->id;
		           //$new->categoryid = $category;
		           $new->name = $title;
		           $new->intro = $description;
		           $new->timemodified = time();
		           $new->type = 'file';
		           $new->course = $COURSE->id;
		           $new->attachment = $newfile->get_id();   
		           $new->categoryid = optional_param('catid', 0, PARAM_INT); 
		           if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
		           	echo "item gespeichert";
		           }else{
		           	echo "item NICHT gespeichert";
		           }
		
		           echo "OK";
		        }else{
		        	echo "falsch";
		        };
		                      
		        $results[] = $file_record;
		    }
			}
	
		} //dateiu > 0
	}
}





function kuerzen($wert,$laenge){
	if (strlen($wert)>$laenge){
		$wert = substr($wert, 0,$laenge ); // gibt "abcd" zurück 
	}
	return $wert;
}

function checkhash(){
	global $DB;
	$userhash = optional_param('key', 0, PARAM_ALPHANUM);
	$sql="SELECT * FROM {user} WHERE md5(id)='".$userhash."'";					 
	if (!$user=$DB->get_record_sql($sql)){
		return false;
	}else{
		return $user;
	}
}
	
/*$users=$DB->get_records("user", array("username" => $_POST["uname"],"password" => $_POST["pword"]));
foreach($users as $user){
	echo md5($user->id)."_".$user->lastname."<br>";
	
}
 print_r($_POST["uname"]);
echo md5("1");*/
/* $inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
				$inhalt.='<result>'."\r\n";
				$inhalt.='<game id="'.$recid.'">'."\r\n";*/
 
 ?>