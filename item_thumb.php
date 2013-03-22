<?php
require_once dirname(__FILE__).'/inc.php';
require_once dirname(__FILE__).'/lib/sharelib.php';

$item_id = optional_param('item_id', -1, PARAM_INT);

//require_login(0, true);

if ($item_id > 0) { 
	$query = "select type, id, userid, url".
		" from {block_exaportitem}".
		" where id=?";
	$item = $DB->get_record_sql($query, array($item_id));	
	$type = $item->type;
};

switch($type) {
	case "file": 
			// thumbnail of file
			if ($img = $DB->get_record('files', array('contextid'=>get_context_instance(CONTEXT_USER, $item->userid)->id, 'component'=>'block_exaport', 'filearea'=>'item_file', 'itemid'=>$item->id), 'id, filename')) {
				// get file
				$fs = get_file_storage();
				$file = $fs->get_file(get_context_instance(CONTEXT_USER, $item->userid)->id, 'block_exaport', 'item_file', $item->id, '/', $img->filename);	
				// serve file
				if ($file) {
					send_stored_file($file);
				} else {
					return false;
				}			
			};/**/			
			break;
	case "link" :
			$url = $item->url;
			if (strpos($url,'http')===false)
				$url = 'http://'.$url;
//			$url = @urlencode($url);
			$str = @file_get_contents($url);
			$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $str, $matches);
			$first_img = $matches[1][0];
			if ($output>0) {
				if (strpos($first_img,'http')===false)
					$first_img = $url."/".$first_img;
			}
			else 
				$first_img = $CFG->wwwroot.'/blocks/exaport/pix/item_link_default.png';					
				$headers = get_headers($first_img, 1);
				$type = $headers["Content-Type"];
				header("Content-type: ".$type);
				echo @file_get_contents($first_img);
			break;
	default: break;
};

?>