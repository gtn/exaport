<?php
require_once dirname(__FILE__).'/inc.php';
require_once dirname(__FILE__).'/lib/sharelib.php';

global $CFG, $USER, $DB, $PAGE;

$entrys = $DB->get_records('block_exaportitem');

foreach($entrys as $entry){
	if(strlen($entry->intro)>1200){
		$update = new stdClass();
		$update->id = $entry->id;
		
		$update->intro = substr($entry->intro, 0, 1200);
		
		$DB->update_record('block_exaportitem', $update);
		
		echo 'Item intro from item with id '.$entry->id.' shortened';
	}
	if(strlen($entry->beispiel_angabe)>1000){
		$update = new stdClass();
		$update->id = $entry->id;
		
		$update->beispiel_angabe = substr($entry->beispiel_angabe, 0, 1300);
		
		$DB->update_record('block_exaportitem', $update);
		
		echo 'Item beispiel_angabe from item with id '.$entry->id.' shortened';
	}
}
?>
