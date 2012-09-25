<?php
 require_once dirname(__FILE__) . '/inc.php';
 require_once dirname(__FILE__) . '/lib/lib.php';
 //require_once dirname(__FILE__) . '/lib/moodlelib.php';
 require_once dirname(__FILE__) . '/lib/sharelib.php';
 global $DB,$USER,$COURSE,$CFG;
 
$action = optional_param('action', 0, PARAM_ALPHANUMEXT);  //100

if ($action=="login"){
		
	$uname = optional_param('username', 0, PARAM_USERNAME);  //100
	$pword = optional_param('password', 0, PARAM_TEXT);	//32
	
	if ($uname!="0" && $pword!="0"){
		$uname=kuerzen($uname,100);
		$pword=kuerzen($pword,50);
		
		$conditions = array("username" => $uname,"password" => $pword);
		if (!$user = $DB->get_record("user", $conditions)){
			$condition = array("username" => $uname);
			if ($user = $DB->get_record("user", $condition)){
				$validiert=validate_internal_user_password($user,$pword);
			}else{
				$validiert=false;
			}
		}else{
			$validiert=true;//alte version bei der die passwörter verschlüsselt geschickt werden
		}
		
		if ($validiert==true){
			if ($user->auth=='nologin' || $user->firstaccess==0 || $user->suspended!=0 || $user->deleted!=0) $uhash=0;
			else{
				if (!$user_hash = $DB->get_record("block_exaportuser", array("user_id"=>$user->id))){
					$uhash=block_exaport_create_exaportuser($user->id);
				}else{
					if (empty($user_hash->user_hash_long)) {$uhash=block_exaport_update_userhash($user_hash->id);}
					else $uhash=$user_hash->user_hash_long;
					if ($user_hash->oezinstall==0) block_exaport_installoez($user->id);
					else block_exaport_installoez($user->id,true);
				}
			}
		}else{
			$uhash=0;
		}
		echo "key=".$uhash;
	}else{
		echo "key=0";
	}
}else if ($action=="child_categories"){
	
	$catid = optional_param('catid', 0, PARAM_INT); 
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$conditions = array("userid" => $user->id,"pid" => $catid);
		if (write_xml_categories($conditions,$catid,$user->id)==false) {
			$conditions = array("userid" => $user->id,"categoryid" => $catid);
			write_xml_items($conditions);
		}
	}
}else if ($action=="get_lastitemID"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$sql="SELECT id FROM {block_exaportitem} WHERE userid=".$user->id." ORDER BY timemodified DESC LIMIT 0,1";
		if ($rs = $DB->get_record_sql($sql)) echo $rs->id;
		else echo "0"; 
	}
}else if ($action=="newCat"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$parent_cat = optional_param('parent_cat', 0, PARAM_INT);
		$catname = optional_param('name', ' ', PARAM_TEXT);
		if ($newid = $DB->insert_record('block_exaportcate', array("pid"=>$parent_cat,"userid"=>$user->id,"name"=>$catname,"subjid"=>0,"topicid"=>0,"timemodified"=>time()))) {
			echo $newid;
		}else{
			echo "-1";
		}
	}
}else if ($action=="save_selected_user"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$selected_user = optional_param('selected_user', 0, PARAM_ALPHANUMEXT);
		$shareall=optional_param('shareall', 0, PARAM_INT);
		$externaccess=optional_param('externaccess', 1, PARAM_INT);
		$view_id = optional_param('view_id', ' ', PARAM_INT);
		$user=explode("_",$selected_user);
		$DB->delete_records("block_exaportviewshar",array("viewid"=>$view_id));
		
		if ($shareall==1){
			$DB->update_record('block_exaportview', array("id"=>$view_id,"timemodified"=>time(),"shareall"=>1,"externaccess"=>$externaccess));
		}else{
			$DB->update_record('block_exaportview', array("id"=>$view_id,"timemodified"=>time(),"shareall"=>0,"externaccess"=>$externaccess));
			foreach ($user as $k=>$v){
				if (is_numeric($v)){
					$DB->insert_record('block_exaportviewshar', array("viewid"=>$view_id,"userid"=>$v));
				}
			}
		}
	}
}else if ($action=="save_selected_items"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$selected_items = optional_param('selected_items', 0, PARAM_ALPHANUMEXT);
		$text = optional_param('text', '', PARAM_ALPHANUMEXT);
		$view_id = optional_param('view_id', ' ', PARAM_INT);
		$items=explode("_",$selected_items);
		$DB->delete_records("block_exaportviewblock",array("viewid"=>$view_id));
		$i=1;
		foreach ($items as $k=>$v){
			if (is_numeric($v)){
				$DB->insert_record('block_exaportviewblock', array("viewid"=>$view_id,"type"=>"item","itemid"=>$v,"text"=>$text,"positionx"=>1,"positiony"=>$i));
				$i++;
			}
		}
	}
}else if ($action=="save_selected_competences"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$selected_competences = optional_param('selected_competences', 0, PARAM_ALPHANUMEXT);
		$competences=explode("_",$selected_competences);
		$item_id = optional_param('item_id', ' ', PARAM_INT);
		$subject_id = optional_param('subject_id', '0', PARAM_INT);
		//$subject_id = optional_param('subject_id', ' ', PARAM_INT);
		
		//die kompetenzen werden nach subject gruppiert angezeigt, daher nur diese gruppe löschen
		$sql="SELECT descr.id FROM {block_exacompsubjects} subj 
		INNER JOIN {block_exacomptopics} top ON top.subjid=subj.id 
		INNER JOIN {block_exacompdescrtopic_mm} tmm ON tmm.topicid=top.id
		INNER JOIN {block_exacompdescriptors} descr ON descr.id=tmm.descrid";
		if ($subject_id>0) $sql.=" WHERE subj.id=".$subject_id;
		$descriptors = $DB->get_records_sql($sql);
		$dlist="0";//init
		foreach ($descriptors as $descriptor){
			$dlist.=",".$descriptor->id;
		}
		$select='activityid='.$item_id.' AND activitytype=2000';
		if ($dlist!="0") $select.=' AND descrid IN ('.$dlist.')';
		//echo $select;
		$DB->delete_records_select("block_exacompdescractiv_mm",$select);
		foreach ($competences as $k=>$v){
			if (is_numeric($v)){
				//echo "insert into block_exacompdescractiv_mm".$v."_".$item_id."<br>";
				$DB->insert_record('block_exacompdescractiv_mm', array("activityid"=>$item_id,"activitytype"=>"2000","descrid"=>$v,"activitytitle"=>"","coursetitle"=>""));
			}
		}
	}
}else if ($action=="save_view_title"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		
		$title = optional_param('title', ' ', PARAM_TEXT);
		$description = optional_param('description', '', PARAM_TEXT);
		$view_id = optional_param('view_id', ' ', PARAM_INT);
		if ($view = $DB->get_record("block_exaportview",  array("id"=>$view_id))){
			$DB->update_record('block_exaportview', array("id"=>$view_id,"name"=>$title,"description"=>$description,"timemodified"=>time()));
		}else{
			do {
			$hash = substr(md5(microtime()), 3, 8);
        } while ($DB->record_exists("block_exaportview", array("hash"=>$hash)));
        
			if ($newid = $DB->insert_record('block_exaportview', array("name"=>$title,"userid"=>$user->id,"description"=>$description,"timemodified"=>time(),"shareall"=>0,"externaccess"=>0,"externcomment"=>0,"langid"=>0,"hash"=>$hash))) {
				echo $newid;
			}else{
				echo "-1";
			}
		}
		
		
	}
}else if ($action=="all_users"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$tusers=array();
		$tusers=exaport_get_shareable_users();
		block_exacomp_write_xml_user($tusers);
	}
}else if ($action=="delete_item"){
	$user=checkhash();
	$url="";
	if (!$user) echo "invalid hash";
	else{
		$itemid = optional_param('itemid', ' ', PARAM_INT);
		$result = $DB->delete_records('block_exacompdescractiv_mm', array("activityid" => $itemid));
		$result = $DB->delete_records('block_exacompdescuser_mm', array("activityid" => $itemid));
		$sql="SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.attachment=f.itemid
		WHERE i.id=".$itemid;
		
		$rs = $DB->get_record_sql($sql);
		delete_file($rs->pathnamehash);

		$result = $DB->delete_records('block_exaportviewblock', array("itemid" => $itemid));
		$result = $DB->delete_records('block_exaportitemshar', array("itemid" => $itemid));
		$result = $DB->delete_records('block_exaportitemcomm', array("itemid" => $itemid));
		$result = $DB->delete_records('block_exaportitem', array("id" => $itemid));
	}
}else if ($action=="delete_view"){
	$user=checkhash();
	$url="";
	if (!$user) echo "invalid hash";
	else{
		$viewid = optional_param('viewid', ' ', PARAM_INT);
		$result = $DB->delete_records('block_exaportviewblock', array("viewid" => $viewid));
		$result = $DB->delete_records('block_exaportview', array("id" => $viewid));
	}
}else if ($action=="get_users_for_view"){
	$user=checkhash();
	$view_id = optional_param('view_id', 0, PARAM_INT);
	if (!$user) echo "invalid hash";
	else{
		header ("Content-Type:text/xml");
	  $view = $DB->get_record("block_exaportview",  array("id"=>$view_id));
	  $inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
		//$inhalt.='<result>'."\r\n";
		$inhalt.= block_exaport_getshares($view,$user->id,false,"selected",true);
		//$inhalt.='</result> '."\r\n";
		echo $inhalt;
	}
}else if ($action=="get_Extern_Link"){
	$user=checkhash();
	$url="";
	if (!$user) echo "invalid hash";
	else{
		$view_id = optional_param('view_id', ' ', PARAM_INT);
		if ($view = $DB->get_record("block_exaportview",  array("id"=>$view_id))){
			$DB->update_record('block_exaportview', array("id"=>$view_id,"timemodified"=>time(),"externaccess"=>1));
	  	$url = block_exaport_get_external_view_url($view,$user->id);
	  }
	}
	echo "externLink=".$url;
}else if ($action=="all_items"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
	  $conditions = array("userid" => $user->id);
			write_xml_items($conditions);
		}
}
else if ($action=="get_items_for_view"){
	$user=checkhash();
	$view_id = optional_param('view_id', 0, PARAM_INT);
	if (!$user) echo "invalid hash";
	else{
	  $conditions = array("userid" => $user->id);
			write_xml_items($conditions,$view_id);
	}
}else if ($action=="oezepsinstalltonull"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
	 $sql="UPDATE {block_exaportuser} SET oezinstall=0 WHERE user_id=".$user->id;
		$DB->execute($sql);
	}		
}else if ($action=="deleteFile_OezepsExample"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$itemid = optional_param('id', 0, PARAM_INT);
		block_exaport_delete_oezepsitemfile($itemid);
		block_exaport_delete_competences($itemid,$user->id);
	}
}else if ($action=="delete_all_oezeps"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$sql="SELECT * FROM {block_exaportitem} WHERE isoez=1 AND userid=".$user->id;
		$items = $DB->get_records_sql($sql);
		foreach ($items as $item){
			block_exaport_delete_oezepsitemfile($item->id);
			block_exaport_delete_competences($item->id,$user->id);
		}
		$DB->delete_records('block_exaportitem', array("isoez" => 1,"userid"=>$user->id));
		echo "delete userid".$user->id;
		$DB->delete_records('block_exaportcate', array("isoez" => 1,"userid"=>$user->id));
		$sql="UPDATE {block_exaportuser} SET oezinstall=0 WHERE user_id=".$user->id;
		$DB->execute($sql);
	}
}else if ($action=="getViews"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
	    $sql = "SELECT vi.id as viid, i.id as itemid,v.id,v.name,v.description,v.externaccess,v.shareall,v.hash,i.name as itemname,i.categoryid as catid,i.type, i.url,i.intro FROM ";
	    $sql.=" {block_exaportview} v LEFT JOIN {block_exaportviewblock} vi ON v.id=vi.viewid INNER JOIN {block_exaportitem} i ON i.id=vi.itemid WHERE v.userid=".$user->id;
	  	
	  	$sql= "SELECT * FROM {block_exaportview} WHERE userid=".$user->id;
	  
	    $views = $DB->get_records_sql($sql);
			header ("Content-Type:text/xml");
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<views>'."\r\n";
			foreach($views as $view){
				$inhalt.="<view name='".$view->name."'  id='".$view->id."' description='".$view->description."'>";
						$sql= "SELECT vi.id as viid, i.id as itemid,i.name as itemname,i.categoryid as catid,i.type, i.url,i.intro FROM ";
						$sql.=" {block_exaportviewblock} vi INNER JOIN {block_exaportitem} i ON i.id=vi.itemid WHERE vi.viewid=".$view->id;
	  				$items = $DB->get_records_sql($sql);
	  				foreach ($items as $item){
	  					$inhalt.="<item name='".$item->itemname."'  id='".$item->itemid."' catid='".$item->catid."' url='".$item->url."' type='".$item->type."' intro=''>";
	  					$inhalt.=$item->intro;
	  					$inhalt.="</item>"."\r\n";
	  				}
	  				
	  				
	  				
	  				$inhalt.=block_exaport_getshares($view,$user->id);
				$inhalt.="</view>"."\r\n";
			}
			$inhalt.="</views>"."\r\n";
			/*
			$viewid="init";
			$i=0;
			foreach($views as $view){
				echo $viewid."  ".$view->id."<br>";
				if ($viewid!=$view->id){
					if ($i>0){
						$inhalt.="</items>"."\r\n";
						$inhalt.=block_exaport_getshares($viewold,$user->id);
						$inhalt.="</view>"."\r\n";
					}
					$inhalt.="<view name='".$view->name."'  id='".$view->id."' description='".$view->description."'>"."\r\n";
					$inhalt.="<items>"."\r\n";
					$viewid=$view->id;
					
				}
				$inhalt.="<item name='".$view->itemname."'  id='".$view->itemid."' catid='".$view->catid."' url='".$view->url."' type='".$view->type."' intro='".$view->intro."'></item>"."\r\n";
				$viewold=$view;
				$i++;
			}
			if ($i>0){
						$inhalt.="</items>"."\r\n";
						$inhalt.=block_exaport_getshares($view,$user->id);
						$inhalt.="</view>"."\r\n";
					}
			$inhalt.='</views> '."\r\n";
			echo $inhalt;
			*/
			echo $inhalt;
		
	}
	
}else if ($action=="getTopics"){
	$sql = "SELECT t.title,t.id FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t, {block_exacompsubjects} s, {block_exacompschooltypes} ty, {block_exacompdescrtopic_mm} dt WHERE mt.typeid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND dt.descrid=d.id AND (ty.isoez=1)";
	$sql.= " GROUP BY t.title,t.id";
	$topics = $DB->get_records_sql($sql);
	header ("Content-Type:text/xml");
	$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
	$inhalt.='<result>'."\r\n";
			foreach($topics as $topic){
				$inhalt.="<topic name='".$topic->title."'  id='".$topic->id."'>";
					$inhalt.="</topic>"."\r\n";
			}
	$inhalt.='</result> '."\r\n";
	echo $inhalt;
}else if ($action=="getSubjects"){
	$sql = "SELECT s.title,s.id FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t, {block_exacompsubjects} s, {block_exacompschooltypes} ty, {block_exacompdescrtopic_mm} dt WHERE mt.typeid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND dt.descrid=d.id AND (ty.isoez=1)";
	$sql.= " GROUP BY s.title,s.id";
	$subjects = $DB->get_records_sql($sql);
	header ("Content-Type:text/xml");
	$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
	$inhalt.='<result>'."\r\n";
			foreach($subjects as $subject){
				$inhalt.="<subject name='".$subject->title."'  id='".$subject->id."'>";
					$inhalt.="</subject>"."\r\n";
			}
	$inhalt.='</result> '."\r\n";
	echo $inhalt;
}else if ($action=="getCompetences" || $action=="getExamples"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		if ($action=="getExamples"){
						block_exaport_installoez($user->id,true);
		}
		if(block_exaport_check_competence_interaction()) {
			$itemid=optional_param('itemid', 0, PARAM_INT);
			$subjectid=optional_param('subjectid', 0, PARAM_INT);
			$clist=",";
			if ($itemid>0){
				$compok=$DB->get_records("block_exacompdescractiv_mm", array("activityid"=>$itemid));
				foreach ($compok as $k=>$v){
					$clist.=$v->descrid.",";
				}
			}
	    $sql = "SELECT d.id, d.title, t.title as topic, s.title as subject FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t, {block_exacompsubjects} s, {block_exacompschooltypes} ty, {block_exacompdescrtopic_mm} dt WHERE mt.typeid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND dt.descrid=d.id AND (ty.isoez=1)";
	    if ($subjectid>0 && $action=="getCompetences"){
	    	$sql.=" AND s.id=".$subjectid;
	    }
	    //echo $sql;
	    
	    $descriptors = $DB->get_records_sql($sql);
			header ("Content-Type:text/xml");
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<result>'."\r\n";
			foreach($descriptors as $descriptor){
				
				if ($action=="getExamples"){
						$bsp=getExamples($descriptor->id);
				}else{
						$bsp=$descriptor->id;
				}
				if ($bsp!=""){
					$inhalt.="<competences name='".$descriptor->title."'  id='".$descriptor->id."' ";

					if(strpos($clist,",".$descriptor->id.",")===false) $inhalt.=" selected='false'";
					else $inhalt.=" selected='true'";
					$inhalt.=">";
					$inhalt.=$bsp;
					$inhalt.="</competences>"."\r\n";
				}
			}
			$inhalt.='</result> '."\r\n";
			echo $inhalt;
			
			
		}else{
			echo "no interaction";
		}
	}
	
}else if ($action=="parent_categories"){
	
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		header ("Content-Type:text/xml");
		$catid = optional_param('catid', 0, PARAM_INT);
		/*$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
		$inhalt.='<result>'."\r\n";*/
		
		if ($category = $DB->get_record("block_exaportcate",  array("id"=>$catid))){
			$conditions = array("pid"=>$category->pid,"userid" => $user->id);
			/*if ($categoryp = $DB->get_record("block_exaportcate",  array("id"=>$category->pid))){
				$conditions = array("id"=>$categoryp->id);
				if ($categoryp->pid!=0){
					if ($categoryp2 = $DB->get_record("block_exaportcate",  array("id"=>$categoryp->pid))){
						$conditions = array("pid"=>$categoryp2->id);
					}
				}
			}*/
		}else{
			$conditions = array("pid"=>"-1");	
		}
		write_xml_categories($conditions,$catid,$user->id);
	}
}else if ($action=="upload"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{

		$filepath="/";
		$title = addslashes(optional_param('title', '', PARAM_TEXT));
		$description = addslashes(optional_param('description', '', PARAM_TEXT));
		$itemid=optional_param('itemid', 0, PARAM_INT);
		if ($itemid>0){
				$itemrs=$DB->get_record("block_exaportitem",array("id"=>$itemid));
				if (!empty($itemrs)){
					if ($itemrs->isoez==1){ //normale items können files nicht aktualisiert werden, da muss das ganze item gelöscht werden
						$sql="SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
						WHERE i.attachment<>0 AND i.id=".$itemid;
						$res = $DB->get_records_sql($sql);
						foreach($res as $rs){
							//echo $rs->pathnamehash;
							if (!empty($rs))	{
								if (delete_file($rs->pathnamehash)){
									block_exaport_delete_competences($itemid,$user->id);
									$DB->update_record('block_exaportitem', array("id"=>$itemid,"attachment"=>""));
								}
							}
						}
					}
				}else{
					$itemrs=new stdClass();
				}
		}else{
			$itemrs=new stdClass();
			$itemrs->isoez=0;
		}
		//$kompetenzen = addslashes(optional_param('competences', 0, PARAM_ALPHANUMEXT));
		//print_r($_FILES);
		//$competences=array();
		$new = new stdClass();
		if ($itemid>0) $new->id=$itemid;
		if ($itemrs->isoez!=1){
		$new->userid = $user->id;
	//$new->categoryid = $category;
		$new->name = $title;
		
		$new->courseid = $COURSE->id;
		$new->categoryid = optional_param('catid', 0, PARAM_INT);
		$new->url = optional_param('url', "", PARAM_URL);
		}
		$new->intro = $description;
		$new->timemodified = time();
		//$comp=optional_param('competences', 0, PARAM_ALPHANUMEXT);
		//$competences=explode("_",$comp);
		if ($itemrs->isoez!=1 && $itemid==0){
			if ($new->url!="") $new->type = 'link';
			else $new->type = 'note';
		}
		
		if(block_exacomp_checkfiles()){
			$fs = get_file_storage();
			$totalsize = 0;
			$context = get_context_instance(CONTEXT_USER,$user->id);
			
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
			$privatefiles = $fs->get_area_files($context->id, 'block_exaport', 'item_file', false, 'id', false);
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
		    $file_record->component = 'block_exaport';
		    $file_record->contextid = $context->id;
		    $file_record->userid    = $user->id;
		    $file_record->filearea  = 'item_file';
		    $file_record->filename = $file->filename;
		    $file_record->filepath  = $filepath;
		    $file_record->itemid    = 0;
		    $file_record->license   = $CFG->sitedefaultlicense;
		    $file_record->author    = $user->lastname." ".$user->firstname;
		    $file_record->source    = '';
		
		    //Check if the file already exist
		   /* $existingfile = $fs->file_exists($file_record->contextid, $file_record->component, $file_record->filearea,
		                $file_record->itemid, $file_record->filepath, $file_record->filename);*/
		    
		    	$file_record->filename=get_unique_filename($fs, $file_record,$file_record->filename);
		    	//print_r($file_record);die;
		       // $file_record->filename = $file->filename."_01";
		    $new->type = 'file';
		           
		           
		           //print_r($new); 
		           if($itemid>0){
		           		//$new->id=$itemid;
		           	/* $newarr=(array)$new;
		           	 print_r($newarr);	
		           $newarr2=array("id"=>$itemid,"userid"=>"7","courseid"=>0,"categoryid"=>0,"name"=>$new->name);
		           		print_r($newarr2);	*/
		           		$DB->update_record('block_exaportitem', $new);
		           		if ($itemrs->isoez!=1){
		           			//nicht mehr beim upload dabei
			           		//block_exaport_delete_competences($itemid,$user->id);
				           	//block_exaport_save_competences($competences,$new,$user->id,$new->name);
				          }else{
				          	block_exaport_delete_competences($itemid,$user->id);
				          	$competencesoez=block_exaport_get_oezcompetencies($itemrs->exampid);
				           	block_exaport_save_competences($competencesoez,$new,$user->id,$itemrs->name);
				          }
		           }else{
			           if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
			           	//block_exaport_save_competences($competences,$new,$user->id,$new->name);
			           	echo $new->id;
			           }else{
			           	//echo "saved2=false";
			           }
			         }
		    	$file_record->itemid=$new->id;
		        if ($newfile = $fs->create_file_from_pathname($file_record, $file->filepath)){
		        	 //$DB->set_field("files","itemid",$newfile->get_id(),array("id"=>$newfile->get_id()));
		           
							//$new->attachment = $newfile->get_id();  
		          $new2=$DB->update_record('block_exaportitem', array("id"=>$new->id,"attachment"=>$newfile->get_id()));
		        }else{
		        	
		        };
		                      
		        $results[] = $file_record;
		    
			}
	
		}else{
			
			if($itemid>0){
		           		$new->id=$itemid;
		           		$DB->update_record('block_exaportitem', $new);
		           		if ($itemrs->isoez!=1){
			           		//block_exaport_delete_competences($itemid,$user->id);
				           	//block_exaport_save_competences($competences,$new,$user->id,$new->name);
				          }else{
				          	block_exaport_delete_competences($itemid,$user->id);
				          	if ($new->intro!="" && $new->intro!=" "){
					          	$competencesoez=block_exaport_get_oezcompetencies($itemrs->exampid);
					           	block_exaport_save_competences($competencesoez,$new,$user->id,$itemrs->name);
					          }
				          	//kein fileupload, keine kompetenzen erworben
				          }
				          echo $new->id;
		  }else{
		  /*foreach($new as $k=>$v){
		  	echo "<br>".$k."--".$v;
		  }
		  die;*/
				if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
			     echo $new->id;
			     //$competences = $DB->delete_records('block_exacompdescractiv_mm', array("activityid" => $existing->id, "activitytype" => 2000));
			     //block_exaport_save_competences($competences,$new,$user->id,$new->name);
			  }else{
			     //echo "saved=false";
			  }
			}
		}
	}
}
function block_exaport_get_oezcompetencies($exampid){
	global $DB;
	$comp=array();
	$descr=$DB->get_records("block_exacompdescrexamp_mm", array("exampid"=>$exampid));
	foreach ($descr as $rs){
		$comp[]=$rs->descrid;
	}
	return $comp;
}
function getExamples($descrid){
	global $DB;
	$inhalt='';
	$sql = "SELECT examp.* FROM {block_exacompexamples} examp INNER JOIN {block_exacompdescrexamp_mm} mm ON examp.id=mm.exampid WHERE examp.externalurl<>'' AND mm.descrid=".$descrid;

  $examples = $DB->get_records_sql($sql);
  foreach ($examples as $example){
  	$inhalt.='
  		<example name="'.$example->title.'" description="'.$example->description.'" id="'.$example->id.'"
    		task="'.$example->task.'" solution="'.$example->solution.'" attachement="'.$example->attachement.'"
    		completefile="'.$example->completefile.'" externalurl="'.oezepsbereinigung($example->externalurl).'" externalsolution="'.$example->externalsolution.'"
				externaltask="'.$example->externaltask.'"';
		if (isoezeps($example->externalurl)) $inhalt.=' oezeps="1"';
		else $inhalt.=' oezeps="0"';
		$inhalt.='></example>'."\r\n";
  }
  return $inhalt;
}
function isoezeps($url){
	if (strpos($url,"oezeps.at")===false){ return false;}
	else return true;
}

function delete_file($hash) {

    $fs = get_file_storage();
 $file = $fs->get_file_by_hash($hash);
// Prepare file record object
/*$fileinfo = array(
    'component' => $component,
    'filearea' => $filearea,     // usually = table name
    'itemid' => $id,               // usually = ID of row in table
    'contextid' => $contextid, // ID of context
    'filepath' => '/',           // any path beginning and ending in /
    'filename' => $filename); // any filename
    //print_r($fileinfo);die;
    $file = $fs->get_file($fileinfo["contextid"], $fileinfo["component"], $fileinfo["filearea"], 
        $fileinfo["itemid"], $fileinfo["filepath"], $fileinfo["filename"]);
 print_r($fileinfo);*/
// echo $file."----";
	// Delete it if it exists

	/*$array = get_object_vars($file);
print_r($array);*/

	if ($file) {
		//116f95207c5be238b1bd2a7ee1b1e3dba771a32c
	    $file->delete();
	    return true;
	}else return false;
 
}

function oezepsbereinigung($url){

	$url=str_replace("http://www.oezeps.at/moodle","",$url);
	$url=str_replace("http://oezeps.at/moodle","",$url);
	$url=str_replace("http://www.oezeps.at","",$url);
	$url=str_replace("http://oezeps.at","",$url);
	
	return $url;
}
function block_exaport_delete_competences($itemid,$userid){
	global $DB;
	$result = $DB->delete_records('block_exacompdescractiv_mm', array("activityid" => $itemid));
	//echo "delete from block_exacompdescractiv_mm where activityid=".$itemid;
	$result = $DB->delete_records('block_exacompdescuser_mm', array("activityid" => $itemid));
	//echo "delete from block_exacompdescuser_mm where activityid=".$itemid;
}

function block_exaport_save_competences($competences,$new,$userid,$aname){
	global $DB;
	 if (count($competences)>0){
		     	foreach ($competences as $k=>$v){
		     		if (is_numeric($v)){
		     			$DB->insert_record('block_exacompdescractiv_mm', array("descrid"=>$v,"activityid"=>$new->id,"activitytype"=>2000,"activitytitle"=>$aname));
		     			$DB->insert_record('block_exacompdescuser_mm',array("descid"=>$v,"activityid"=>$new->id,"userid"=>$userid,"reviewerid"=>$userid,"activitytype"=>"2000","role"=>0));
		     		}
		     	}
	}
}
function exaport_get_shareable_users(){
	$tusers=array();
	$courses=exaport_get_shareable_courses_with_users('sharing');
		foreach($courses as $course){
				foreach ($course["users"] as $user){
					$tusers[$user["id"]]=$user["name"];
				}
		}
	return $tusers;
}
function block_exaport_getshares($view,$usrid,$sharetag=true,$strshared="viewShared",$viewusers=false){
	global $DB;
	$inhalt="";
	if ($sharetag) $inhalt="<shares>"."\r\n";
	if ($view->externaccess==1 && $viewusers==false){
		$url = block_exaport_get_external_view_url($view,$usrid);
		$inhalt.="	<extern>".$url."</extern>"."\r\n";
	}
	if ($viewusers==false) $inhalt.="	<intern>"."\r\n";
	$inhalt.="		<users>"."\r\n";
	$tusers=array();

	$tusers=exaport_get_shareable_users();
	if($view->shareall==1){
		foreach($tusers as $k=>$v){
			$inhalt.='<user name="'.$v.'" id="'.$k.'" '.$strshared.'="true" ></user>'."\r\n";
		}
	}else{
		$sql = "SELECT u.firstname,u.lastname,u.id FROM ";
		$sql.=" {block_exaportviewshar} s INNER JOIN {user} u ON s.userid=u.id WHERE s.viewid=".$view->id;
		//echo $sql;
	  $users = $DB->get_records_sql($sql);
	  foreach ($users as $user){
	  	$tusers2[$user->id]=$user->lastname." ".$user->firstname;
	  }
	  foreach($tusers as $k=>$v){
			$inhalt.='<user name="'.$v.'" id="'.$k.'" ';
			if (!empty($tusers2[$k])) $inhalt.=$strshared.'="true"';
			else $inhalt.=$strshared.'="false"';
			$inhalt.='></user>'."\r\n";
		}
	}
	
	$inhalt.='		</users>'."\r\n";
	if ($viewusers==false) $inhalt.='	</intern>'."\r\n";
	if ($sharetag) $inhalt.="	</shares>"."\r\n";
	return $inhalt;
}
function block_exacomp_write_xml_user($tusers){
	header ("Content-Type:text/xml");
	if ($tusers){
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<users>'."\r\n";
			foreach($tusers as $k => $v){
				
				$inhalt.='<user id="'.$k.'" name="'.$v.'"></user>'."\r\n";
			}
			$inhalt.='</users> '."\r\n";
			echo $inhalt;
	}
}

function get_unique_filename($fs, $file_record,$fn){
	
$existingfile = $fs->file_exists($file_record->contextid, $file_record->component, $file_record->filearea,
		                $file_record->itemid, $file_record->filepath, $fn);
if ($existingfile) {
	$laenge=strlen($fn);
	$ext=strrchr ($fn, ".");
	$rest=substr($fn,0,($laenge-strlen($ext)));

	if (strpos($rest,"_")===false){
		$fnnew=$rest."_1".$ext;
	}else{
		$num=substr(strrchr ($rest, "_"),1);
		if (is_numeric($num)) $numn=intval($num);
		else $numn="0";
		$numn++;
		$fnnew=substr($rest,0,(strlen($rest)-strlen($num)-1))."_".$numn.$ext;
	}
	$fn=get_unique_filename($fs, $file_record,$fnnew);
}
return $fn;

}	    	
function get_number_subcats($id){
	global $DB;
	$conditions=array("pid"=>$id);
	if ($items = $DB->get_records("block_exaportcate", $conditions," isoez DESC")){
		return count($items);
	}else return 0;
}
function write_xml_items($conditions,$view_id=0){
	global $DB,$CFG;
	/*foreach($conditions as $key=>$value){
		echo $key."-".$value."<br>";
	}
	die;*/
	header ("Content-Type:text/xml");
	if ($view_id>0){
		$vitemar=array();
		$vitems = $DB->get_records("block_exaportviewblock", array("viewid"=>$view_id));
		foreach ($vitems as $k=>$v){
			$vitemar[$v->itemid]=1;
		}
		//print_r($vitemar);die;
	}

		
	if ($items = $DB->get_records("block_exaportitem", $conditions, " isoez DESC,name")){
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<result>'."\r\n";
			foreach($items as $item){
				//if ($view_id>0 && $item->isoez==1 && $item->attachment==""){
					//$inhalt.='<item id="'.$item->id.'" name="'.$item->name.'" isoez="'.$item->isoez.'" url="'.$item->attachment.'"></item>';
				//}else{
					$inhalt.='<item name="'.$item->name.'" id="'.$item->id.'"';
					if ($view_id>0){
						if (!empty($vitemar[$item->id])) $inhalt.=' selected="true"';
						else $inhalt.=' selected="false"';
					}
					if ($item->attachment!="") {
						$progress=1;
						$userhash = optional_param('key', 0, PARAM_ALPHANUM);
						$fileurl=$CFG->wwwroot.'/blocks/exaport/portfoliofile.php?access=portfolio/id/'.$item->userid.'&itemid='.$item->id.'&att='.$item->id.'&hv='.$userhash;
						//$fileurl=$CFG->wwwroot.'/blocks/exaport/portfoliofile.php?access=portfolio/id/'.$item->userid.'&itemid='.$item->id;
					}
					else{ 
						$fileurl="";
						if ($item->intro!="") $progress=1;
						else $progress=0;
					}
					$inhalt.=' catid="'.$item->categoryid.'" type="'.$item->type.'" url="'.$item->url.'" progress="'.$progress.'" isOezepsItem="'.block_exaport_numtobool($item->isoez).'">'."\r\n";
					$inhalt.='<name>'.cdatawrap($item->name).'</name>'."\r\n";
					$inhalt.='<description>'.cdatawrap($item->intro).'</description>'."\r\n";
					$inhalt.='<fileUrl>'.cdatawrap(block_exaport_ers_null($fileurl)).'</fileUrl>'."\r\n";
					$inhalt.='<beispiel_url>';
					if ($item->isoez==1) $inhalt.=oezepsbereinigung(block_exaport_ers_null($item->beispiel_url));
					else $inhalt.=block_exaport_ers_null($item->beispiel_url);
					$inhalt.='</beispiel_url>'."\r\n";
					$inhalt.='<beispiel_description>'.cdatawrap($item->beispiel_angabe).'</beispiel_description>'."\r\n";
					if(strpos($item->name,"Neun")===false){$inhalt.='<texteingabe>false</texteingabe>'."\r\n";}
					else{
						$inhalt.='<texteingabe>true</texteingabe>'."\r\n";
					}
					//else $inhalt.='<texteingabe>false</texteingabe>'."\r\n";
					$inhalt.='</item>'."\r\n";
				//}
			}
			$inhalt.='</result> '."\r\n";
			echo $inhalt;
	}
}
function block_exaport_ers_null($wert){
	if ($wert=="") return " ";
	else return $wert;
}
function write_xml_categories($conditions,$catid,$userid){
	global $DB;
	
	header ("Content-Type:text/xml");
	/*foreach($conditions as $key=>$value){
		echo $key."-".$value."<br>";
	}
	print_r($conditions);*/
	$sozkomparr=array();
		if ($categories = $DB->get_records("block_exaportcate", $conditions," isoez DESC")){
			if ($sozkomp = $DB->get_record("block_exacompschooltypes", array("title"=>"Soziale Kompetenzen"))){
				if($sozsubjs = $DB->get_records("block_exacompsubjects", array("stid"=>$sozkomp->id))){
					foreach ($sozsubjs as $k=>$v){
						$sozkomparr[$v->id]=1;
					}
				}
			}
			//print_r($sozkomparr);die;
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<result>'."\r\n";
			foreach($categories as $categorie){
				
				$numsubcats=get_number_subcats($categorie->id);
				$inhalt.='<categorie catid="'.$categorie->id.'" numsubcats="'.$numsubcats.'" progress="'.block_exaport_get_progress($categorie->id,$catid,$userid).'" isOezepsItem="'.block_exaport_numtobool($categorie->isoez).'"';
				if (!empty($sozkomparr[$categorie->subjid])) $inhalt.=' isSozialeKompetenz="true"';
				$inhalt.='>'."\r\n";
				$inhalt.='<name>'.cdatawrap($categorie->name).'</name>'."\r\n";
				$inhalt.='<description>'.cdatawrap($categorie->description).'</description>'."\r\n";
				$inhalt.='</categorie>'."\r\n";
			}
			$inhalt.='</result> '."\r\n";
			echo $inhalt;
			return true;
		}else{
			return false;
		}
}
function block_exaport_numtobool($wert){
	if ($wert=="1") return "true";
	else return "false";
}
function kuerzen($wert,$laenge){
	if (strlen($wert)>$laenge){
		$wert = substr($wert, 0,$laenge ); // gibt "abcd" zurück 
	}
	return $wert;
}

function block_exacomp_checkfiles(){
	if (empty($_FILES)) {return false;}
	else{
		$ret=true;

		foreach ($_FILES as $datei){
		
			if ($datei["error"]>0) $ret=false;
		}
		return $ret;
	}
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

function block_exaport_create_exaportuser($userid){
	global $DB;
	$uhash=block_exaport_unique_hash();
	$newid = $DB->insert_record('block_exaportuser', array("user_id"=>$userid,"persinfo_timemodified"=>time(),"user_hash_long"=>$uhash,"description"=>""));
	return $uhash;
}
function block_exaport_update_userhash($id){
	global $DB;
	$uhash=block_exaport_unique_hash();
	$DB->update_record('block_exaportuser', array("id"=>$id,"persinfo_timemodified"=>time(),"user_hash_long"=>$uhash));
	return $uhash;
}		
function block_exaport_unique_hash(){
	global $DB;
	$id = substr(md5(uniqid(rand(),true)),0,29);
	return $id;
}
function block_exaport_get_progress($catid,$catidparent,$userid){
	global $DB;
	
	$catlist=block_exaport_get_subcategories($catid,$catidparent,$userid);
	$catlist = preg_replace("/^,/", "", $catlist);
	     
	$sql="SELECT count(id) as alle,sum(IF(attachment<>'' or intro<>'',1,0)) as mitfile FROM {block_exaportitem} WHERE isoez=1 AND categoryid IN (".$catlist.")";
//echo $sql;
	if ($rs=$DB->get_record_sql($sql)){
		if ($rs->alle>0){
			return ($rs->mitfile/$rs->alle);
		}else return 0;
	}else return 0;
}
function block_exaport_get_subcategories($catid,$catlist,$userid){
	global $DB;
	$catlist.=",".$catid;
	$sql="SELECT id FROM {block_exaportcate} WHERE pid=".$catid." AND userid=".$userid;

	$cats=$DB->get_records_sql($sql);
	foreach ($cats as $cat){
		$catlist.=",".$cat->id;
	}
	
	return $catlist;
}
function block_exaport_delete_oezepsitemfile($itemid){
			global $DB;
			$sql="SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
					WHERE i.attachment<>0 AND i.id=".$itemid;

			$res = $DB->get_records_sql($sql);
					foreach($res as $rs){
						//echo $rs->pathnamehash;
						if (!empty($rs))	{
							if (delete_file($rs->pathnamehash)){
								$DB->update_record('block_exaportitem', array("id"=>$itemid,"attachment"=>""));
							}
						}
					}
		}
function cdatawrap($wert){
	if (!empty($wert) && $wert!=" ") $wert='<![CDATA['.$wert.']]>';
	return $wert;
}
function block_exaport_installoez($userid,$isupdate=false){
	global $DB;
	$where="";
	if ($isupdate==true){
		$sql="SELECT group_concat(cast(exampid as char(11))) as ids FROM {block_exaportitem} where isoez=1 AND userid=".$userid;
		$rse = $DB->get_record_sql($sql);
		if (!empty($rse->ids)){$where=" AND examp.id NOT IN(".$rse->ids.")";}
	}
	$sql="SELECT DISTINCT concat(top.id,examp.id) as id, subj.title as kat1, subj.titleshort as kat1s,subj.id as subjid, top.title as kat2,top.titleshort as kat2s,top.id as topid,top.description as topdescription, examp.title as item,examp.titleshort as items,examp.description as exampdescription,examp.externalurl,examp.externaltask,examp.ressources,examp.task,examp.id as exampid,examp.completefile FROM {block_exacompschooltypes} st INNER JOIN {block_exacompsubjects} subj ON subj.stid=st.id 
	INNER JOIN {block_exacomptopics} top ON top.subjid=subj.id 
	INNER JOIN {block_exacompdescrtopic_mm} tmm ON tmm.topicid=top.id
	INNER JOIN {block_exacompdescriptors} descr ON descr.id=tmm.descrid
	INNER JOIN {block_exacompdescrexamp_mm} emm ON emm.descrid=descr.id
	INNER JOIN {block_exacompexamples} examp ON examp.id=emm.exampid";
	$sql.=" WHERE st.isoez=1".$where." ";
	$sql.=" ORDER BY subjid,topid";

	$row = $DB->get_records_sql($sql);
	$subjid=-1;$topid=-1;
	$beispiel_url="";
	if ($isupdate==false){
		foreach($row as $rs){
			if ($subjid!=$rs->subjid){ 
				if (!empty($rs->kat1s)) $kat1s=$rs->kat1s;
				else $kat1s=$rs->kat1;
				$newsubjid=$DB->insert_record('block_exaportcate', array("pid"=>0,"userid"=>$userid,"name"=>$kat1s,"timemodified"=>time(),"course"=>0,"isoez"=>"1","subjid"=>$rs->subjid,"topicid"=>0));$subjid=$rs->subjid;
			}
			if ($topid!=$rs->topid){
				if (!empty($rs->kat2s)) $kat2s=$rs->kat2s;
				else $kat2s=$rs->kat2;
				$newtopid=$DB->insert_record('block_exaportcate', array("pid"=>$newsubjid,"userid"=>$userid,"name"=>$kat2s,"timemodified"=>time(),"course"=>0,"isoez"=>"1","description"=>$rs->topdescription,"subjid"=>$rs->subjid,"topicid"=>$rs->topid));$topid=$rs->topid;
			}
			if ($rs->externaltask!="") $beispiel_url=$rs->externaltask;
			if ($rs->externalurl!="") $beispiel_url=$rs->externalurl;
	
			if ($rs->completefile!="") $fileUrl=$rs->completefile;
			if (!empty($rs->items)) $items=$rs->items;
			else $items=$rs->item;
			$DB->insert_record('block_exaportitem', array("userid"=>$userid,"type"=>"note","categoryid"=>$newtopid,"name"=>$items,"url"=>"","intro"=>"","beispiel_angabe"=>$rs->exampdescription,"attachment"=>"","timemodified"=>time(),"courseid"=>0,"isoez"=>"1","beispiel_url"=>$beispiel_url,"exampid"=>$rs->exampid));
		}
		$sql="UPDATE {block_exaportuser} SET oezinstall=1 WHERE user_id=".$userid;
		$DB->execute($sql);
	}else{
		foreach($row as $rs){
			$sql="SELECT * FROM {block_exaportcate} WHERE topicid=".$rs->topid." LIMIT 0,1";
			$rs2 = $DB->get_record_sql($sql);
			if (!empty($rs2)){$newtopid=$rs2->id;}
			else{
				$sql="SELECT * FROM {block_exaportcate} WHERE subjid=".$rs->subjid." LIMIT 0,1";
				$rs3 = $DB->get_record_sql($sql);
				if (!empty($rs3)){$newsubjid=$rs3->id;}
				else{
					$newsubjid=$DB->insert_record('block_exaportcate', array("pid"=>0,"userid"=>$userid,"name"=>$rs->kat1,"timemodified"=>time(),"course"=>0,"isoez"=>"1","subjid"=>$rs->subjid,"topicid"=>0));
				}
				$newtopid=$DB->insert_record('block_exaportcate', array("pid"=>$newsubjid,"userid"=>$userid,"name"=>$rs->kat2,"timemodified"=>time(),"course"=>0,"isoez"=>"1","description"=>$rs->topdescription,"subjid"=>$rs->subjid,"topicid"=>$rs->topid));
			}
			if ($rs->externaltask!="") $beispiel_url=$rs->externaltask;
			if ($rs->externalurl!="") $beispiel_url=$rs->externalurl;
	
			if ($rs->completefile!="") $fileUrl=$rs->completefile;
			$DB->insert_record('block_exaportitem', array("userid"=>$userid,"type"=>"file","categoryid"=>$newtopid,"name"=>$rs->item,"url"=>"","intro"=>"","attachment"=>"","timemodified"=>time(),"courseid"=>0,"isoez"=>"1","beispiel_url"=>$beispiel_url,"exampid"=>$rs->exampid));
		}
	}

}

 ?>