<?php
 require_once dirname(__FILE__) . '/inc.php';
 global $DB;
$uid = optional_param('uid', 0, PARAM_INT);
if ($uid!=0)
	$condi=array("userid"=>$uid);
else
	$condi=array();
	echo "<h1>Items</h1>";	
if (!$items = $DB->get_records("block_exaportitem",$condi)){
echo "kein eintrag";
	}else{

		foreach($items as $item){
			echo "<hr>userid: ".$item->userid." name:".$item->name." intro:".$item->intro." attachement:".$item->attachment." kategorie:".$item->categoryid;
		}
	};
	
echo "<h1>Kategorien</h1>";		
if (!$cats = $DB->get_records("block_exaportcate",$condi)){
echo "kein eintrag";
	}else{

		foreach($cats as $cat){
			echo "<hr> id:".$cat->id." userid: ".$cat->userid." name:".$cat->name." parent:".$cat->pid;
		}
	};


 
 ?>