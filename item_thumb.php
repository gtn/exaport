<?php
require_once dirname(__FILE__).'/inc.php';
require_once dirname(__FILE__).'/lib/sharelib.php';

$item_id = optional_param('item_id', -1, PARAM_INT);

//require_login(0, true);

$item = null;
if ($item_id > 0) { 
	$item = $DB->get_record('block_exaportitem', array('id'=>$item_id, 'userid'=>$USER->id));
}

switch($item->type) {
	case "file": 
			// thumbnail of file
			$file = block_exaport_get_item_file($item);
			
			// serve file
			if ($file) {
				send_stored_file($file);
			} else {
				return false;
			}			

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