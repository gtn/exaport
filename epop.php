<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

error_reporting(E_ALL);
require_once __DIR__.'/inc.php';
global $DB,$USER,$COURSE,$CFG;
 
$action = optional_param('action', 0, PARAM_ALPHANUMEXT);  //100

if ($action=="login"){
		
	$uname = optional_param('username', 0, PARAM_USERNAME);  //100
	$pword = optional_param('password', 0, PARAM_TEXT);	//32
	
	if ($uname!="0" && $pword!="0"){
	
		$uname=kuerzen($uname,100);
		$pword=kuerzen($pword,50);
		$uhash=0;
		$conditions = array("username" => $uname,"password" => $pword);
		if (!$user = $DB->get_record("user", $conditions)){
			
			$condition = array("username" => $uname);
			if ($user = $DB->get_record("user", $condition)){
				//$validiert=validate_internal_user_password($user,$pword);
			
				$validiert=authenticate_user_login($uname,$pword); 
			}else{
				$validiert=false;
			}
		}else{
			$validiert=true;//alte version bei der die passw�rter verschl�sselt geschickt werden
		}
		
		if ($validiert==true){
			
			if ($user->auth=='nologin' || $user->confirmed==0 || $user->suspended!=0 || $user->deleted!=0) $uhash=0;
			else{
				if (!$user_hash = $DB->get_record("block_exaportuser", array("user_id"=>$user->id))){
					if ($uhash=block_exaport_create_exaportuser($user->id)){
						block_exaport_installoez($user->id);
					}
				}else{
					if (empty($user_hash->user_hash_long)) {$uhash=block_exaport_update_userhash($user_hash->id);}
					else $uhash=$user_hash->user_hash_long;
					if ($user_hash->oezinstall==0) block_exaport_installoez($user->id);
					else {
						if (block_exaport_checkIfUpdate($user->id))	{
							block_exaport_installoez($user->id,true);
						}
					}
				}
			}
		}else{
			$uhash=0;
		}
		if (empty($uhash)) $uhash=0;
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
			$competence_category="";
			if(block_exaport_check_competence_interaction()){
				$sql="SELECT st.id,st.title FROM {block_exaportcate} cat INNER JOIN {block_exacompsubjects} s ON s.id=cat.subjid INNER JOIN {block_exacompschooltypes} st ON st.id=s.stid ";
				$sql.="WHERE cat.id=?";
				if ($schoolt=$DB->get_record_sql($sql,array($catid))){
					if ($schoolt->title=="Soziale Kompetenzen") $competence_category="sozial";
					elseif ($schoolt->title=="Personale Kompetenzen") $competence_category="personal";
					elseif ($schoolt->title=="Digitale Kompetenzen") $competence_category="digital";
				}
			}
			write_xml_items($conditions,0,$competence_category);
		}
	}
}else if ($action=="get_lastitemID"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$sql="SELECT id FROM {block_exaportitem} WHERE userid=? ORDER BY timemodified DESC LIMIT 0,1";
		if ($rs = $DB->get_record_sql($sql,array($user->id))) echo $rs->id;
		else echo "0"; 
	}
}else if ($action=="get_courseid"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$sql="SELECT c.id FROM {course} c INNER JOIN {enrol} e on e.courseid=c.id INNER JOIN {user_enrolments} ue ON ue.enrolid=e.id";
		$sql.=" WHERE c.visible=1 AND ue.userid=?";
		$sql.=" ORDER BY ue.timemodified DESC LIMIT 0,1";
		if ($rs = $DB->get_record_sql($sql,array($user->id))) echo $rs->id;
		else echo "0"; 
	}
}else if ($action=="newCat"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$parent_cat = optional_param('parent_cat', 0, PARAM_INT);
		$catname = optional_param('name', ' ', PARAM_TEXT);
		if ($newid = $DB->insert_record('block_exaportcate', array("pid"=>$parent_cat,"userid"=>$user->id,"name"=>$catname,"subjid"=>0,"topicid"=>0,"stid"=>0,"isoez"=>0,"source"=>0,"sourceid"=>0,"sourcemod"=>0,"timemodified"=>time()))) {
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
		$view_id = optional_param('view_id', ' ', PARAM_INT);
		$user=explode("_",$selected_user);
		$DB->delete_records("block_exaportviewshar",array("viewid"=>$view_id));
		
		if ($shareall==1){
			$DB->update_record('block_exaportview', array("id"=>$view_id,"timemodified"=>time(),"shareall"=>1));
		}else{
			$DB->update_record('block_exaportview', array("id"=>$view_id,"timemodified"=>time(),"shareall"=>0));
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
		
		//die kompetenzen werden nach subject gruppiert angezeigt, daher nur diese gruppe l�schen
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
		$select='activityid='.$item_id.' AND eportfolioitem=1';
		if ($dlist!="0") $select.=' AND compid IN ('.$dlist.')';
		//echo $select;
		$DB->delete_records_select("block_exacompcompactiv_mm",$select);
		foreach ($competences as $k=>$v){
			if (is_numeric($v)){
				$DB->insert_record('block_exacompcompactiv_mm', array("activityid"=>$item_id,"eportfolioitem"=>"1","compid"=>$v,"activitytitle"=>"","coursetitle"=>""));
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
		
			if ($newid = $DB->insert_record('block_exaportview', array("name"=>$title,"userid"=>$user->id,"description"=>$description,"timemodified"=>time(),"shareall"=>0,"externaccess"=>0,"externcomment"=>0,"langid"=>0,"hash"=>$hash,"layout"=>"2"))) {
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
		block_exaport_write_xml_user($tusers);
	}
}else if ($action=="delete_item"){
	$user=checkhash();
	$url="";
	if (!$user) echo "invalid hash";
	else{
		$itemid = optional_param('itemid', ' ', PARAM_INT);
		$result = $DB->delete_records('block_exacompcompactiv_mm', array("activityid" => $itemid));
		$result = $DB->delete_records('block_exacompcompuser_mm', array("activityid" => $itemid));
		$sql="SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
		WHERE i.id=?";
		if($resu = $DB->get_records_sql($sql,array($itemid))){
			foreach ($resu as $rs){
				delete_file($rs->pathnamehash);
			}
		}
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
		$sql="SELECT * FROM {block_exaportitem} WHERE isoez=1 AND userid=?";
		$items = $DB->get_records_sql($sql,array($user->id));
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
		$sql.=" {block_exaportview} v LEFT JOIN {block_exaportviewblock} vi ON v.id=vi.viewid INNER JOIN {block_exaportitem} i ON i.id=vi.itemid WHERE v.userid=?";
	  	
	  	$sql= "SELECT * FROM {block_exaportview} WHERE userid=?";
	  
		$views = $DB->get_records_sql($sql,array($user->id));
			header ("Content-Type:text/xml");
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<views>'."\r\n";
			foreach($views as $view){
				$inhalt.="<view  id='".$view->id."'>";
						$inhalt.='<name>'.cdatawrap($view->name).'</name>'."\r\n";
						$inhalt.='<description>'.cdatawrap($view->description).'</description>'."\r\n";
						$sql= "SELECT vi.id as viid, i.id as itemid,i.name as itemname,i.categoryid as catid,i.type, i.url,i.intro FROM ";
						$sql.=" {block_exaportviewblock} vi INNER JOIN {block_exaportitem} i ON i.id=vi.itemid WHERE vi.viewid=?";
	  				$items = $DB->get_records_sql($sql,array($view->id));
	  				foreach ($items as $item){
	  					$inhalt.="<item  id='".$item->itemid."' catid='".$item->catid."' url='".$item->url."' type='".$item->type."'>";
	  					$inhalt.='<name>'.cdatawrap($item->itemname).'</name>'."\r\n";
	  					$inhalt.=cdatawrap($item->intro);
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
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$sql = "SELECT t.id,t.title FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t, {block_exacompsubjects} s, {block_exacompschooltypes} ty, {block_exacompdescrtopic_mm} dt WHERE mt.stid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND dt.descrid=d.id AND (ty.isoez=1)";
		$sql.= " GROUP BY t.id,t.title";
		$topics = $DB->get_records_sql($sql);
		header ("Content-Type:text/xml");
		$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
		$inhalt.='<result>'."\r\n";
				foreach($topics as $topic){
					$inhalt.="<topic id='".$topic->id."'>"."\r\n";
					$inhalt.="<name>".cdatawrap($topic->title)."</name>"."\r\n";
					$inhalt.="</topic>"."\r\n";
				}
		$inhalt.='</result> '."\r\n";
		echo $inhalt;
	}
}else if ($action=="getSubjects"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		$itemid=optional_param('itemid', 0, PARAM_INT);
		$sql = "SELECT s.id,s.title FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t, {block_exacompcoutopi_mm} ctt, {block_exacompsubjects} s, {block_exacompschooltypes} ty, {block_exacompdescrtopic_mm} dt WHERE mt.stid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND ctt.topicid=t.id AND dt.descrid=d.id AND (ty.isoez=1)";
		$sql.= " GROUP BY s.id,s.title";
		$subjects = $DB->get_records_sql($sql);
		header ("Content-Type:text/xml");
		$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
		$inhalt.='<result>'."\r\n";
				foreach($subjects as $subject){
					$inhalt.="<subject  id='".$subject->id."'";
					if (block_exaport_competence_selected($subject->id,$user->id,$itemid)) $inhalt.=" competence_selected='true'";
					else $inhalt.=" competence_selected='false'";
					$inhalt.=">"."\r\n";
					$inhalt.="<name>".cdatawrap($subject->title)."</name>"."\r\n";
						$inhalt.="</subject>"."\r\n";
				}
		$inhalt.='</result> '."\r\n";
		echo $inhalt;
	}
}else if ($action=="getCompetences" || $action=="getExamples"){
	$user=checkhash();
	if (!$user) echo "invalid hash";
	else{
		if ($action=="getExamples"){
			if (block_exaport_checkIfUpdate($user->id))	block_exaport_installoez($user->id,true);
		}
		if(block_exaport_check_competence_interaction()) {
			$itemid=optional_param('itemid', 0, PARAM_INT);
			$subjectid=optional_param('subjectid', 0, PARAM_INT);
			$clist=",";
			if ($itemid>0){
				$compok=$DB->get_records("block_exacompcompactiv_mm", array("activityid"=>$itemid));
				foreach ($compok as $k=>$v){
					$clist.=$v->compid.",";
				}
			}
		//$sql = "SELECT CONCAT(dt.id,'_',ctt.id) as uniqueid,dt.id as dtid,d.id, d.title, t.title as topic, s.title as subject FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t,{block_exacompcoutopi_mm} ctt, {block_exacompsubjects} s, {block_exacompschooltypes} ty, {block_exacompdescrtopic_mm} dt WHERE mt.stid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND ctt.topicid=t.id AND dt.descrid=d.id AND (ty.isoez=1)";
		 //neu am 20.5.2014 weil descriptoren mehrfach vorkommen
		$sql = "SELECT CONCAT(dt.id,'_',ctt.id) as uniqueid,dt.id as dtid,d.id, d.title, t.title as topic, s.title as subject FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t,{block_exacompcoutopi_mm} ctt, {block_exacompsubjects} s, {block_exacompschooltypes} ty, {block_exacompdescrtopic_mm} dt WHERE mt.stid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND ctt.topicid=t.id AND dt.descrid=d.id AND (ty.isoez=1)";
		if ($subjectid>0 && $action=="getCompetences"){
			$sql.=" AND s.id=".$subjectid;
		}
		$sql.=" GROUP BY d.id, d.title ORDER BY d.sorting";
		//echo $sql;
		
		$descriptors = $DB->get_records_sql($sql);
			header ("Content-Type:text/xml");
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<result>'."\r\n";
			foreach($descriptors as $descriptor){
				
				if ($action=="getExamples"){
						$bsp=getExamples($descriptor->id);
				}else{
						$bsp="<id>".$descriptor->id."</id>"."\r\n";
				}
				if ($bsp!=""){
					$inhalt.="<competences id='".$descriptor->id."' ";

					if(strpos($clist,",".$descriptor->id.",")===false) $inhalt.=" selected='false'";
					else $inhalt.=" selected='true'";
					$inhalt.=">";
					$inhalt.=$bsp;
					$inhalt.="<name>".cdatawrap($descriptor->title)."</name>"."\r\n";
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
}else if ($action=="upload" || $action=="updatePic"){
	$user=checkhash();

	if (!$user) echo "invalid hash";
	else{

		$filepath="/";
		$title = addslashes(optional_param('title', 'mytitle', PARAM_TEXT));
		$description = addslashes(optional_param('description', '', PARAM_TEXT));
		//$description = sauber($_POST["description"]);
		$itemid=optional_param('itemid', 0, PARAM_INT);
		if ($itemid>0){
				$itemrs=$DB->get_record("block_exaportitem",array("id"=>$itemid));
				if (!empty($itemrs)){
					/* 
					//auch �zeps items k�nnen nicht aktualisiert werden, die m�ssen gel�scht werden, ab 31.5.13
					if ($itemrs->isoez==1){ //normale items k�nnen files nicht aktualisiert werden, da muss das ganze item gel�scht werden
						$sql="SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
						WHERE i.attachment<>0 AND i.id=?";
						$res = $DB->get_records_sql($sql,array($itemid));
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
					*/
					if ($action=="updatePic"){
						
							$sql="SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
							WHERE i.id=?";
							$res = $DB->get_records_sql($sql,array($itemid));
							foreach($res as $rs){
								
								if (!empty($rs))	{
									if (delete_file($rs->pathnamehash)){
										$DB->update_record('block_exaportitem', array("id"=>$itemid,"attachment"=>""));
										
									}
								}
							}
						
					}
				}else{
					if ($action=="updatePic"){
						//no update possible, nothing to do
						echo "0";die; 
					}
					$itemrs=new stdClass();
					$itemrs->isoez=0;
				}
		}else{
			$itemrs=new stdClass();
			$itemrs->isoez=0;
			$itemrs->type="note";
		}
		//$kompetenzen = addslashes(optional_param('competences', 0, PARAM_ALPHANUMEXT));
		//print_r($_FILES);
		//$competences=array();
		$new = new stdClass();
		if ($itemid>0) $new->id=$itemid;
		if ($action=="updatePic"){
			//only update picture
			$new->timemodified = time();
		}else{
			if ($itemrs->isoez!=1){ //wenn neues item, �zeps items k�nnen eh nicht neu sein
				$new->userid = $user->id;
				//$new->categoryid = $category;
				$new->name = $title;		
				$new->courseid = $COURSE->id;
				$new->categoryid = optional_param('catid', 0, PARAM_INT);		
			} 
			$new->url = optional_param('url', "", PARAM_URL);
			if (!empty($new->url)) {
				if (!preg_match('/^(http|https|ftp):\/\//i', $new->url)) {
					$new->url="http://".$new->url;
				}
			}
			$new->intro = $description;
			$new->timemodified = time();
			//$comp=optional_param('competences', 0, PARAM_ALPHANUMEXT);
			//$competences=explode("_",$comp);
			
			/* was ist der richtige typ?
				wenn datei dabei ist, immer datei, das wird unten immer gemacht, wenn if(block_exaport_checkfiles()
				wenn neu: url+text->note, url->link, text->note
				wenn update: wenn vorher datei, bleibts datei, sonst wie bei neu
			*/
			if ($itemid!=0 && $itemrs->type=="file"){ //update und type file, type bleibt immer file, weil file kann nicht updated werden, es muss hier also datei dabei sein
				$new->type = 'file'; 
			}else{
				if (!empty($new->url) && empty($description)) $new->type = 'link';
				else $new->type = 'note'; //weil wenn datei dabei ist, wird type nachher sowieso type
			}
			
			/*if ($itemrs->isoez!=1 && $itemid==0){
				if ($new->url!="" && empty($description)) $new->type = 'link';
				else $new->type = 'note';
			}else if ($itemrs->isoez==1 && $itemid!=0){
				if ($new->url!="" && empty($description)) $new->type = 'link';
				else $new->type = 'note';
			}*/
		}
		if(block_exaport_checkfiles()){
			
			$fs = get_file_storage();
			$totalsize = 0;
			//$context = get_context_instance(CONTEXT_USER,$user->id);
			$context = context_user::instance($user->id);
			
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
			$file_record->userid	= $user->id;
			$file_record->filearea  = 'item_file';
			$file_record->filename = $file->filename;
			$file_record->filepath  = $filepath;
			$file_record->itemid	= 0;
			$file_record->license   = $CFG->sitedefaultlicense;
			$file_record->author	= $user->lastname." ".$user->firstname;
			$file_record->source	= '';
		
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
									if ($action!="updatePic"){
					   		$DB->update_record('block_exaportitem', $new);
					   	}else{
					   		$new2=new stdClass;
					   		$new2->id=$new->id;
					   		$new2->type="file"; //wenn note soll file werden
					   		$DB->update_record('block_exaportitem', $new2);
					   	}
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
					 
				if ($tempfile = $fs->create_file_from_pathname($file_record, $file->filepath)){
					 
				if(strcmp(mimeinfo('type', $file->filename), "image/jpeg") == 0){
								$imageinfo = $tempfile->get_imageinfo();
								
								$file_record_img = new stdClass;
								$file_record_img->component = 'block_exaport';
								$file_record_img->contextid = $context->id;
								$file_record_img->userid	= $user->id;
								$file_record_img->filearea  = 'item_file';
								$file_record_img->filename = $file->filename;
								$file_record_img->filepath  = $filepath;
								$file_record_img->itemid	= $new->id;
								$file_record_img->license   = $CFG->sitedefaultlicense;
								$file_record_img->author	= $user->lastname." ".$user->firstname;
								$file_record_img->source	= '';
								
								$iw=intval($imageinfo['width']);
								$ih=intval($imageinfo['height']);
								$fakt=1;
								if ($iw>2000){
									$fakt=(2000/$iw);
									$iw=ceil($iw*$fakt);
									$ih=ceil($ih*$fakt);
								}
								if ($ih>2000){
									$fakt=(2000/$ih);
									$iw=ceil($iw*$fakt);
									$ih=ceil($ih*$fakt);
								}
								if ($fakt<>1){
									$newfile = $fs->convert_image($file_record_img, $tempfile->get_id(),  $iw, $ih);
								}else{
									$file_record->itemid=$new->id;
									$newfile = $fs->create_file_from_pathname($file_record, $file->filepath);
								}
								if($tempfile)
									$tempfile->delete();
						}else{
							$file_record->itemid=$new->id;
							$newfile = $fs->create_file_from_pathname($file_record, $file->filepath);
							if($tempfile)
								$tempfile->delete();
						}
					
						$attachm=$newfile->get_id();
						echo "ID=".$attachm;
				  $new2=$DB->update_record('block_exaportitem', array("id"=>$new->id,"attachment"=>$attachm));
			  }else{
					
				};
			   
							  
				$results[] = $file_record;
			
			}
	
		}else{
			if ($action!="updatePic"){
				if($itemid>0){
					   		$new->id=$itemid;
					   		$DB->update_record('block_exaportitem', $new);
					   		if ($itemrs->isoez!=1){
						   		//block_exaport_delete_competences($itemid,$user->id);
							   	//block_exaport_save_competences($competences,$new,$user->id,$new->name);
							  }else{
							  	block_exaport_delete_competences($itemid,$user->id);
							  	//wenn text oder link, dann beispiel gel�st, wenn type file ist datei dabei, auch gel�st
							  	if (!empty($new->intro) || !empty($new->url) || $new->type=="file"){
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
	$sql = "SELECT examp.* FROM {block_exacompexamples} examp INNER JOIN {block_exacompdescrexamp_mm} mm ON examp.id=mm.exampid WHERE examp.externalurl<>'' AND mm.descrid=?";

  $examples = $DB->get_records_sql($sql,array($descrid));
  foreach ($examples as $example){
  	$inhalt.='
  		<example id="'.$example->id.'"';
		if (isoezeps($example->externalurl)) $inhalt.=' oezeps="1"';
		else $inhalt.=' oezeps="0"';
		$inhalt.='>'."\r\n";
		$inhalt.="<name>".cdatawrap($example->title)."</name>"."\r\n";
		
		$inhalt.="<description>".cdatawrap($example->description)."</description>"."\r\n";
		$inhalt.="<task>".cdatawrap($example->task)."</task>"."\r\n";
		$inhalt.="<solution>".cdatawrap($example->solution)."</solution>"."\r\n";
		$inhalt.="<attachement>".cdatawrap($example->attachement)."</attachement>"."\r\n";
		$inhalt.="<completefile>".cdatawrap($example->completefile)."</completefile>"."\r\n";
		$inhalt.="<externalurl>".cdatawrap(create_autologin_moodle_example_link($example->externalurl))."</externalurl>"."\r\n";
		$inhalt.="<externalsolution>".cdatawrap($example->externalsolution)."</externalsolution>"."\r\n";
		$inhalt.="<externaltask>".cdatawrap($example->externaltask)."</externaltask>"."\r\n";
		
		$inhalt.="</example>"."\r\n";
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
	'filearea' => $filearea,	 // usually = table name
	'itemid' => $id,			   // usually = ID of row in table
	'contextid' => $contextid, // ID of context
	'filepath' => '/',		   // any path beginning and ending in /
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

function oezepsbereinigung($url,$nuroezeps=1){

	$url=str_replace("http://www.oezeps.at/moodle","",$url);
	$url=str_replace("http://oezeps.at/moodle","",$url);
	$url=str_replace("http://www.oezeps.at","",$url);
	$url=str_replace("http://oezeps.at","",$url);
	if ($nuroezeps==0){
		$url=str_replace("http://www.digikomp.at","http://www.digikomp.at/blocks/exaport/epopal.php?url=",$url);
	}
	return $url;
}

function create_autologin_moodle_example_link($url){

	$url=str_replace("oezeps.at/moodle","oezeps.at/moodle/blocks/exaport/epopal.php?url=",$url);
	$url=str_replace("digikomp.at","digikomp.at/blocks/exaport/epopal.php?url=",$url);
	$url=str_replace("www2.edumoodle.at/epop","www2.lernplattform.schule.at/epop/blocks/exaport/epopal.php?url=",$url);
	$url=str_replace("www2.lernplattform.schule.at/epop","www2.lernplattform.schule.at/epop/blocks/exaport/epopal.php?url=",$url);

	return $url;
}

function block_exaport_delete_competences($itemid,$userid){
	global $DB;
	$result = $DB->delete_records('block_exacompcompactiv_mm', array("activityid" => $itemid));
	//echo "delete from block_exacompdescractiv_mm where activityid=".$itemid;
	$result = $DB->delete_records('block_exacompcompuser_mm', array("activityid" => $itemid));
	//echo "delete from block_exacompdescuser_mm where activityid=".$itemid;
}

function block_exaport_save_competences($competences,$new,$userid,$aname){
	global $DB;
	 if (count($competences)>0){
			 	foreach ($competences as $k=>$v){
			 		if (is_numeric($v)){
			 			$DB->insert_record('block_exacompcompactiv_mm', array("compid"=>$v,"activityid"=>$new->id,"eportfolioitem"=>1,"activitytitle"=>$aname));
			 			$DB->insert_record('block_exacompcompuser_mm',array("compid"=>$v,"activityid"=>$new->id,"userid"=>$userid,"reviewerid"=>$userid,"eportfolioitem"=>1,"role"=>0));
			 		}
			 	}
	}
}
function exaport_get_shareable_users(){
	$tusers=array();
	$courses=exaport_get_shareable_courses_with_users('sharing');
		foreach($courses as $course){
				foreach ($course->users as $user){
					$tusers[$user->id] = $user->name;
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
			$inhalt.='<user name="'.$v.'" id="'.$k.'" '.$strshared.'="true" >'."\r\n";
			$inhalt.='<name>'.cdatawrap($v).'</name>'."\r\n";
			$inhalt.='</user>'."\r\n";
		}
	}else{
		$tusers2=array();
		$sql = "SELECT u.id,u.firstname,u.lastname FROM ";
		$sql.=" {block_exaportviewshar} s INNER JOIN {user} u ON s.userid=u.id WHERE s.viewid=?";
		
	  $users = $DB->get_records_sql($sql,array($view->id));
	  foreach ($users as $user){
	  	$tusers2[$user->id]=$user->lastname." ".$user->firstname;
	  }
	 
	  foreach($tusers as $k=>$v){
			$inhalt.='<user name="'.$v.'" id="'.$k.'" ';
			if (!empty($tusers2[$k])) $inhalt.=$strshared.'="true"';
			else $inhalt.=$strshared.'="false"';
			$inhalt.='>'."\r\n";
			$inhalt.='<name>'.cdatawrap($v).'</name>'."\r\n";
			$inhalt.='</user>'."\r\n";
		}
	}
	
	$inhalt.='		</users>'."\r\n";
	if ($viewusers==false) $inhalt.='	</intern>'."\r\n";
	if ($sharetag) $inhalt.="	</shares>"."\r\n";
	return $inhalt;
}
function block_exaport_write_xml_user($tusers){
	header ("Content-Type:text/xml");
	if ($tusers){
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<users>'."\r\n";
			foreach($tusers as $k => $v){
				$inhalt.='<user id="'.$k.'">'."\r\n";
				$inhalt.='<name>'.cdatawrap($v).'</name>'."\r\n";
				$inhalt.='</user>'."\r\n";
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
function write_xml_items($conditions,$view_id=0,$competence_category=""){
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
				
				/*itemauswahl f�r view: nur gel�ste aufgaben/items anzeigen*/
				if ($view_id>0){
					if($item->attachment!="" || $item->intro!="" || $item->url!="") $showitem=true;
					else $showitem=false;
				}else{
					$showitem=true;
				}
				/*itemauswahl f�r view ende*/
				
				if($showitem==true){
					if(empty($item->parentid) || $item->parentid==0 || block_exaport_parent_is_solved($item->parentid,$item->userid)){
						$inhalt.='<item id="'.$item->id.'"';
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
							if (!empty($item->intro)|| !empty($item->url)) $progress=1;
							else $progress=0;
						}
						//if ($competence_category!=""){
							$inhalt.=' competence_category="'.$competence_category.'"';
						//}
						$inhalt.=' catid="'.$item->categoryid.'" type="'.$item->type.'" progress="'.$progress.'" isOezepsItem="'.block_exaport_numtobool($item->isoez).'">'."\r\n";
						$inhalt.='<name>'.cdatawrap($item->name).'</name>'."\r\n";
						$inhalt.='<description>'.cdatawrap($item->intro).'</description>'."\r\n";
						$inhalt.='<url>'.cdatawrap($item->url).'</url>'."\r\n";
						$isPicture="false";
						if (!empty($fileurl)){
								if ($dateien = $DB->get_records("files",  array("component"=>"block_exaport","itemid"=>$item->id))){
									foreach($dateien as $datei){
										if ($datei->filesize>0){
											if (preg_match('/.+\/(jpeg|jpg|gif|png)$/', $datei->mimetype)) $isPicture="true";
										}
									}
								}
						}
						$inhalt.='<fileUrl isPicture="'.$isPicture.'">'.cdatawrap(block_exaport_ers_null($fileurl)).'</fileUrl>'."\r\n";
						$inhalt.='<beispiel_url>';
						if ($item->isoez==1) $inhalt.=cdatawrap(create_autologin_moodle_example_link(block_exaport_ers_null($item->beispiel_url)));
						else $inhalt.=cdatawrap(block_exaport_ers_null($item->beispiel_url));
						$inhalt.='</beispiel_url>'."\r\n";
						$inhalt.='<beispiel_description>'.cdatawrap($item->beispiel_angabe).'</beispiel_description>'."\r\n";
						$texteingabe=0;$bildbearbeiten=0;
						if ($item->iseditable==1 && !empty($item->example_url)) $bildbearbeiten=1;
						else if ($item->iseditable==1) $texteingabe=1;
				
						$inhalt.='<texteingabe>'.block_exaport_numtobool($texteingabe).'</texteingabe>'."\r\n";
						$inhalt.='<bildbearbeiten>'.block_exaport_numtobool($bildbearbeiten).'</bildbearbeiten>'."\r\n";
						$inhalt.='<originalbild>'.cdatawrap($item->example_url).'</originalbild>'."\r\n";
						$inhalt.='</item>'."\r\n";
					}
				}
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
	$catkomparr=array();
		if ($categories = $DB->get_records("block_exaportcate", $conditions," isoez DESC")){
			if ($sozkomp = $DB->get_records("block_exacompschooltypes", array("title"=>"Soziale Kompetenzen"))){
				foreach ($sozkomp as $ks){
					if($sozsubjs = $DB->get_records("block_exacompsubjects", array("stid"=>$ks->id))){
						foreach ($sozsubjs as $k=>$v){
							$catkomparr[$v->id]="sozial";
						}
					}
				}
			}		
			if ($sozkomp = $DB->get_records("block_exacompschooltypes", array("title"=>"Personale Kompetenzen"))){
				foreach ($sozkomp as $ks){
					if($sozsubjs = $DB->get_records("block_exacompsubjects", array("stid"=>$ks->id))){
						foreach ($sozsubjs as $k=>$v){
							$catkomparr[$v->id]="personal";
						}
					}
				}
			} 
			if ($sozkomp = $DB->get_records("block_exacompschooltypes", array("title"=>"Digitale Kompetenzen"))){
				foreach ($sozkomp as $ks){
					if($sozsubjs = $DB->get_records("block_exacompsubjects", array("stid"=>$ks->id))){
						foreach ($sozsubjs as $k=>$v){
							$catkomparr[$v->id]="digital";
						}
					}
				}
			} 
			//print_r($catkomparr);die;
			$inhalt='<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
			$inhalt.='<result>'."\r\n";
			foreach($categories as $categorie){
				
				$numsubcats=get_number_subcats($categorie->id);
				$prog=block_exaport_get_progress($categorie->id,$catid,$userid);
				$inhalt.='<categorie catid="'.$categorie->id.'" numsubcats="'.$numsubcats.'" progress="'.$prog->progress.'" numItems="'.$prog->anzahl.'" isOezepsItem="'.block_exaport_numtobool($categorie->isoez).'"';
				if (!empty($catkomparr[$categorie->subjid])) $inhalt.=' competence_category="'.$catkomparr[$categorie->subjid].'"';
				else $inhalt.=' competence_category=""';
				$inhalt.='>'."\r\n";
				$inhalt.='<name>'.cdatawrap($categorie->name).'</name>'."\r\n";
				$inhalt.='<description>'.cdatawrap(htmlwrap($categorie->description,$categorie->name)).'</description>'."\r\n";
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
		$wert = substr($wert, 0,$laenge ); // gibt "abcd" zur�ck 
	}
	return $wert;
}

function block_exaport_checkfiles(){
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
	if (empty($userhash) or $userhash=="0") return false;
	else{
		$sql="SELECT u.* FROM {user} u INNER JOIN {block_exaportuser} eu ON eu.user_id=u.id WHERE eu.user_hash_long=?";					 
		if (!$user=$DB->get_record_sql($sql,array($userhash))){
			return false;
		}else{
			$USER=$user;
			return $user;
		}
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
	$result=new stdClass();
	$result->progress=0;$result->anzahl=0;
	$catlist=block_exaport_get_subcategories($catid,$catidparent,$userid);
	$catlist = preg_replace("/^,/", "", $catlist);
	
	if ($catidparent==0){//kontinent, eine ebene tiefer graben
		$catarr=explode(",",$catlist);$catlistt="";
		foreach($catarr as $catl){
			$catlistt.=block_exaport_get_subcategories($catl,"",$userid);
		}
		$catlist = preg_replace("/^,/", "", $catlistt);
	}  
	$sql="SELECT count(id) as alle,sum(IF(attachment<>'' or intro<>'' or url<>'',1,0)) as mitfile FROM {block_exaportitem} WHERE isoez=1 AND categoryid IN (".$catlist.")";

	if ($rs=$DB->get_record_sql($sql)){
		if ($rs->alle>0){
			$result->progress = ($rs->mitfile/$rs->alle);
			$result->anzahl = $rs->alle;
		}
	}
	return $result;
}
function block_exaport_get_subcategories($catid,$catlist,$userid){
	global $DB;
	$catlist.=",".$catid;
	$sql="SELECT id FROM {block_exaportcate} WHERE pid=? AND userid=?";

	$cats=$DB->get_records_sql($sql,array($catid,$userid));
	foreach ($cats as $cat){
		$catlist.=",".$cat->id;
	}
	
	return $catlist;
}
function block_exaport_delete_oezepsitemfile($itemid){
			global $DB;
			$sql="SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
					WHERE i.attachment<>0 AND i.id=?";

			$res = $DB->get_records_sql($sql,array($itemid));
					foreach($res as $rs){
						//echo $rs->pathnamehash;
						if (!empty($rs))	{
							if (delete_file($rs->pathnamehash)){
								$DB->update_record('block_exaportitem', array("id"=>$itemid,"attachment"=>""));
							}
						}
					}

			if ($item=$DB->get_record("block_exaportitem",array("id"=>$itemid))){

				if(!empty($item->intro)) {$DB->update_record('block_exaportitem', array("id"=>$itemid,"type"=>"note"));}
				else if(!empty($item->url)) {$DB->update_record('block_exaportitem', array("id"=>$itemid,"type"=>"link"));}
			}
		}
function cdatawrap($wert){
	if (!empty($wert) && $wert!=" ") $wert='<![CDATA['.$wert.']]>';
	return $wert;
}
function htmlwrap($wert,$title="E-Pop"){
	if (!empty($wert) && $wert!=" ") $wert='<!doctype html><html><head><meta charset="utf-8"><title>'.$title.'</title></head><body><div>'.$wert.'</div></body></html>';
	return $wert;
}
function sauber($wert){
	$wert=strip_tags($wert,"<br><b><p><i><h1><h2>");
	$wert=str_replace("'","",$wert);
	//$wert=htmlspecialchars($wert,ENT_COMPAT);
	$wert=addslashes($wert);
	return $wert;
}
function block_exaport_competence_selected($subjid,$userid,$itemid){
	global $DB;
	$sql="SELECT CONCAT(dmm.id,'_',descr.id,'_',item.id) as uniqueid, dmm.id,descr.id FROM {block_exacompsubjects} subj 
		INNER JOIN {block_exacomptopics} top ON top.subjid=subj.id 
		INNER JOIN {block_exacompdescrtopic_mm} tmm ON tmm.topicid=top.id
		INNER JOIN {block_exacompdescriptors} descr ON descr.id=tmm.descrid
		INNER JOIN {block_exacompcompactiv_mm} dmm ON dmm.compid=descr.id
		INNER JOIN {block_exaportitem} item ON item.id=dmm.activityid AND eportfolioitem=1 AND dmm.comptype = 0 
		WHERE dmm.comptype = 0 AND subj.id=".$subjid." AND item.userid=".$userid;
		if ($itemid>0){
			$sql.=" AND dmm.activityid=".$itemid;
		}
		//echo $sql;
		//
		if ($res=$DB->get_records_sql($sql)) return true;
		else return false;
}
function block_exaport_checkIfUpdate($userid){
	
		//$conditions = array("username" => $uname,"password" => $pword);
		//if (!$user = $DB->get_record("user", $conditions)){
		global $DB;
		$sql="SELECT * FROM {block_exacompsettings} WHERE courseid=0 AND activities='importxml'";
		if ($modsetting = $DB->get_record_sql($sql)){
			if ($usersetting = $DB->get_record("block_exaportuser",array("user_id"=>$userid))){
				if (!empty($usersetting->import_oez_tstamp)){
					if ($usersetting->import_oez_tstamp >= $modsetting->tstamp)	return false;
					else return true;
				}else return true;
			}else return true;
		}else return true;
}

function block_exaport_installoez($userid,$isupdate=false){
	global $DB;
	
	$rem_ids=array();
	$where="";$catold=array();
	
	if (!$kont = $DB->get_records("block_exaportcate", array("userid"=>$userid,"isoez"=>2))){
		$newkontid=$DB->insert_record('block_exaportcate', array("name"=>"Eigener Kontinent","userid"=>$userid,"pid"=>0,"timemodified"=>time(),"courseid"=>0,"description"=>"Eigener Kontinent","isoez"=>2,"stid"=>0,"subjid"=>0,"topicid"=>0,"source"=>0,"sourceid"=>0,"sourcemod"=>0));
		$sql="UPDATE {block_exaportcate} SET pid=".$newkontid." WHERE pid=0 AND isoez=0 AND userid=".$userid;
		$DB->execute($sql);
	}
		
	if ($isupdate==true){
		//exacomp: timestamp hinterlegen, wann update
		//nur wenn neue daten, dann update
		//zuerst export_cate in array schreiben mit stid#subjid#topid, um abfragen zu sparen
		//dann neue daten durchlaufen, wenn neu dann insert, wenn vorhanden dann title und parentid pr�fen und bei bedarf update, f�r l�schen merker machen
		//echo $userid;die;
		if ($cats = $DB->get_records("block_exaportcate", array("userid"=>$userid))){
			
			foreach($cats as $cat){
				$catold[$cat->source."#".$cat->sourceid."#".$cat->sourcemod]=array("name"=>$cat->name,"pid"=>$cat->pid,"id"=>$cat->id);
			}
		} 
		
		/*$sql="SELECT group_concat(cast(exampid as char(11))) as ids FROM {block_exaportitem} where isoez=1 AND userid=?";
		$rse = $DB->get_record_sql($sql,array($userid));
		if (!empty($rse->ids)){$where=" AND examp.id NOT IN(".$rse->ids.")";}*/
	}
	$sql="SELECT DISTINCT concat(top.id,'_',examp.id) as id,st.title as kat0, st.id as stid,st.source as stsource,st.sourceid as stsourceid, subj.title as kat1,st.description as stdescription, subj.titleshort as kat1s,subj.id as subjid,subj.source as subsource,subj.sourceid as subsourceid,subj.description as subjdescription, top.title as kat2,top.titleshort as kat2s,top.id as topid,top.description as topdescription,top.source as topsource,top.sourceid as topsourceid, examp.title as item,examp.titleshort as items,examp.description as exampdescription,examp.externalurl,examp.externaltask,examp.task,examp.source as sourceexamp,examp.id as exampid,examp.completefile,examp.iseditable,examp.source,examp.sourceid,examp.parentid,examp.solution  
	FROM {block_exacompschooltypes} st INNER JOIN {block_exacompsubjects} subj ON subj.stid=st.id 
	INNER JOIN {block_exacomptopics} top ON top.subjid=subj.id 
	INNER JOIN {block_exacompdescrtopic_mm} tmm ON tmm.topicid=top.id
	INNER JOIN {block_exacompdescriptors} descr ON descr.id=tmm.descrid
	INNER JOIN {block_exacompdescrexamp_mm} emm ON emm.descrid=descr.id
	INNER JOIN {block_exacompexamples} examp ON examp.id=emm.exampid";
	$sql.=" WHERE st.isoez=1 OR st.epop=1 OR subj.epop=1 OR top.epop=1 OR descr.epop=1 OR examp.epop=1 OR (st.isoez=2 AND examp.source=2) OR (examp.source=3)".$where." ";
	$sql.=" ORDER BY st.id,subj.id,top.id";
//echo $sql;die;
	$row = $DB->get_records_sql($sql);
	$stid=-1;$subjid=-1;$topid=-1;
	$beispiel_url="";
	$catlist='0';
	$itemlist='0';
	//if ($isupdate==false){
		foreach($row as $rs){
			$parentid_is_old=false;
			if ($stid!=$rs->stid){
				$keyst=$rs->stsource."#".$rs->stsourceid."#3";
				$jetzn=time();
				if (array_key_exists($keyst,$catold)){
					$newstid=$catold[$keyst]["id"];
					//echo $rs->stid."--------------".$rs->stdescription."@";
					$DB->update_record('block_exaportcate', array("id"=>$newstid,"name"=>$rs->kat0,"stid"=>$rs->stid,"timemodified"=>$jetzn,"description"=>$rs->stdescription));
				}else{
					
					$datas=array("pid"=>0,"stid"=>$rs->stid,"sourcemod"=>"3","userid"=>$userid,"name"=>$rs->kat0,"timemodified"=>$jetzn,"course"=>"0","isoez"=>"1","subjid"=>0,"topicid"=>0,"source"=>$rs->stsource,"sourceid"=>$rs->stsourceid,"description"=>$rs->stdescription);
					$newstid=$DB->insert_record('block_exaportcate', $datas);
					
				}
				$stid=$rs->stid;
				$catlist.=','.$newstid;
			}
			if ($subjid!=$rs->subjid){ 
				$keysub=$rs->subsource."#".$rs->subsourceid."#5"; 
				if (!empty($rs->kat1s)) $kat1s=$rs->kat1s;
				else $kat1s=$rs->kat1;
				if (array_key_exists($keysub,$catold)){
					$newsubjid=$catold[$keysub]["id"];
					$DB->update_record('block_exaportcate', array("id"=>$newsubjid,"name"=>$kat1s,"pid"=>$newstid,"timemodified"=>time(),"stid"=>$stid,"subjid"=>$rs->subjid,"description"=>$rs->subjdescription));
				}else{
					$newsubjid=$DB->insert_record('block_exaportcate', array("pid"=>$newstid,"userid"=>$userid,"name"=>$kat1s,"timemodified"=>time(),"course"=>0,"isoez"=>"1","stid"=>$stid,"subjid"=>$rs->subjid,"topicid"=>0,"source"=>$rs->subsource,"sourceid"=>$rs->subsourceid,"sourcemod"=>5,"description"=>$rs->subjdescription));
				}
				$subjid=$rs->subjid;
				$catlist.=','.$newsubjid;
			}
			if ($topid!=$rs->topid){
				$keytop=$rs->topsource."#".$rs->topsourceid."#7";
				
				if (!empty($rs->kat2s)) $kat2s=$rs->kat2s;
				else $kat2s=$rs->kat2;
				//echo $keytop;print_r($catold);die;
				if (array_key_exists($keytop,$catold)){
					$newtopid=$catold[$keytop]["id"];
					$DB->update_record('block_exaportcate', array("id"=>$newtopid,"name"=>$kat2s,"pid"=>$newsubjid,"timemodified"=>time(),"description"=>$rs->topdescription,"stid"=>$stid,"subjid"=>$rs->subjid,"topicid"=>$rs->topid));
				}else{
					$newtopid=$DB->insert_record('block_exaportcate', array("pid"=>$newsubjid,"userid"=>$userid,"name"=>$kat2s,"timemodified"=>time(),"course"=>0,"isoez"=>"1","description"=>$rs->topdescription,"stid"=>$stid,"subjid"=>$rs->subjid,"topicid"=>$rs->topid,"source"=>$rs->topsource,"sourceid"=>$rs->topsourceid,"sourcemod"=>7));
				}
				$topid=$rs->topid;
				$catlist.=','.$newtopid;
			}
			$beispiel_url="";
			if ($rs->externaltask!="") $beispiel_url=$rs->externaltask;
			if ($rs->externalurl!="") $beispiel_url=$rs->externalurl;
			if ($rs->task!="") $beispiel_url=$rs->task;
	
			if (!empty($rs->items)) $items=$rs->items;
			else $items=$rs->item;
			$iteminsert=true;
			
			$pid=intval($rs->parentid);
			if ($pid>0){
					if (!empty($rem_ids[0][$pid])){
						$pid=$rem_ids[0][$pid];
					}else{
						$parentid_is_old=true;
					}
			}
			
			if ($isupdate==true){
				if($rs->source==3){
					 $sourceidtemp=$rs->exampid; //if example created from teacher in moodle, there is no sourceid. because sourceid is from komet xml tool exacomp_data.xml
					 //$example_url=$rs->solution;
					 $example_url=$rs->completefile;
				}else{
					 $sourceidtemp=$rs->sourceid;
					 $example_url=$rs->completefile;
				}
				if ($itemrs = $DB->get_records("block_exaportitem",array("isoez"=>1,"source"=>$rs->source,"sourceid"=>$sourceidtemp,"userid"=>$userid,"categoryid"=>$newtopid))){  //kategoryId mitnehmen, weil ein item kopiert und auf verschiedene kategorien zugeordnet werden kann. beim update soll dann nur das jeweilige item aktualisiert werden, sonst ist categorie falsch
					$iteminsert=false;
					foreach($itemrs as $item){
						$itemlist.=','.$item->id;
						$rem_ids[0][$rs->exampid]=$item->id; //remark relation for parentids later
						$data=array("id"=>$item->id,"userid"=>$userid,"categoryid"=>$newtopid,"name"=>$items,"beispiel_angabe"=>$rs->exampdescription,"timemodified"=>time(),"courseid"=>0,"isoez"=>"1","beispiel_url"=>$beispiel_url,"exampid"=>$rs->exampid,"iseditable"=>$rs->iseditable,"source"=>$rs->source,"sourceid"=>$rs->sourceid,"example_url"=>$example_url,"parentid"=>$pid);
						$DB->update_record('block_exaportitem', $data);
						if ($parentid_is_old) $rem_ids[1][$item->id]=intval($rs->parentid); //save old parentid from new id
					}
				}
			}
			if ($iteminsert==true) {
				if(!empty($items)){
					if($rs->source==3) {
						$sourceidtemp=$rs->exampid; //if example created from teacher in moodle, there is no sourceid. because sourceid is from komet xml tool exacomp_data.xml
						//$example_url=$rs->solution;
						$example_url=$rs->completefile;
					}
					else {
						$sourceidtemp=$rs->sourceid;
						$example_url=$rs->completefile;
					}
					$newid=$DB->insert_record('block_exaportitem', array("userid"=>$userid,"type"=>"note","categoryid"=>$newtopid,"name"=>$items,"url"=>"","intro"=>"","beispiel_angabe"=>$rs->exampdescription,"attachment"=>"","timemodified"=>time(),"courseid"=>0,"isoez"=>"1","beispiel_url"=>$beispiel_url,"exampid"=>$rs->exampid,"iseditable"=>$rs->iseditable,"source"=>$rs->source,"sourceid"=>$sourceidtemp,"example_url"=>$example_url,"parentid"=>$pid));
					$itemlist.=','.$newid;
					$rem_ids[0][$rs->exampid]=$newid; //remark relation for parentids later
					if ($parentid_is_old) $rem_ids[1][$newid]=intval($rs->parentid);
				}
			}
		} //end foreach $row
		
		$sql='DELETE FROM {block_exaportitem} WHERE id NOT IN ('.$itemlist.') AND userid='.$userid.' AND isoez=1 AND intro="" AND url="" AND attachment=""';
		$DB->execute($sql);
		
		$sql='SELECT * FROM {block_exaportcate} WHERE id NOT IN ('.$catlist.') AND userid='.$userid.' AND isoez=1';
		$rows = $DB->get_records_sql($sql);
		foreach($rows as $row){
			if (!$DB->get_record("block_exaportitem", array("categoryid"=>$row->id))){
				$DB->delete_records("block_exaportcate",array("id"=>$row->id));
			}
		}
		
		$sql="UPDATE {block_exaportuser} SET oezinstall=1,import_oez_tstamp=".time()." WHERE user_id=".$userid;
		$DB->execute($sql);
  	block_exaport_update_unset_pids('block_exaportitem',$rem_ids);

	/*}else{
		foreach($row as $rs){
			$sql="SELECT * FROM {block_exaportcate} WHERE topicid=? LIMIT 0,1";
			$rs2 = $DB->get_record_sql($sql,array($rs->topid));
			if (!empty($rs2)){$newtopid=$rs2->id;}
			else{
				$sql="SELECT * FROM {block_exaportcate} WHERE subjid=? LIMIT 0,1";
				$rs3 = $DB->get_record_sql($sql,array($rs->subjid));
				if (!empty($rs3)){$newsubjid=$rs3->id;}
				else{
					$newsubjid=$DB->insert_record('block_exaportcate', array("pid"=>0,"userid"=>$userid,"name"=>$rs->kat1,"timemodified"=>time(),"course"=>0,"isoez"=>"1","subjid"=>$rs->subjid,"topicid"=>0,"source"=>$rs->subsource,"sourceid"=>$rs->subsourceid));
				}
				$newtopid=$DB->insert_record('block_exaportcate', array("pid"=>$newsubjid,"userid"=>$userid,"name"=>$rs->kat2,"timemodified"=>time(),"course"=>0,"isoez"=>"1","description"=>$rs->topdescription,"subjid"=>$rs->subjid,"topicid"=>$rs->topid,"source"=>$rs->topsource,"sourceid"=>$rs->topsourceid));
			}
			if ($rs->externaltask!="") $beispiel_url=$rs->externaltask;
			if ($rs->externalurl!="") $beispiel_url=$rs->externalurl;
	
			if ($rs->completefile!="") $fileUrl=$rs->completefile;
			$DB->insert_record('block_exaportitem', array("userid"=>$userid,"type"=>"file","categoryid"=>$newtopid,"name"=>$rs->item,"url"=>"","intro"=>"","attachment"=>"","timemodified"=>time(),"courseid"=>0,"isoez"=>"1","beispiel_url"=>$beispiel_url,"exampid"=>$rs->exampid,"source"=>$rs->source,"sourceid"=>$rs->sourceid));
		}
	}*/

}
function block_exaport_update_unset_pids($utable,$rem_ids){
		global $DB;
		/*echo "<pre>";
		print_r($rem_ids);*/

		if (!empty($rem_ids[1])){
			foreach ($rem_ids[1] as $newid=>$v) {
				//echo "UPDATE mdl_".$utable." SET parentid=".$rem_ids[0][$v]." WHERE id=".$newid;
			$DB->update_record($utable, array("id"=>$newid,"parentid"=>$rem_ids[0][$v]));
			//echo "bei datensatz ".$newid." wird parentid=".$rem_ids[0][$v];
			}		
		}
}

function block_exaport_parent_is_solved($id,$userid){
	global $DB;
	$sql="SELECT i.id FROM {block_exaportitem} i WHERE id=? AND userid=? AND (attachment<>'' || url<>'' || intro<>'')";
	if ($DB->get_record_sql($sql,array($id,$userid))) return true;
	else return false;
}
