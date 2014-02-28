<?php
require_once dirname(__FILE__).'/inc.php';
require_once dirname(__FILE__).'/lib/sharelib.php';

$item_id = optional_param('item_id', -1, PARAM_INT);

//require_login(0, true);

$item = null;
if ($item_id > 0) { 
	$item = $DB->get_record('block_exaportitem', array('id'=>$item_id, 'userid'=>$USER->id));
}
if (empty($item)) die('item not found');

switch ($item->type) {
	case "file": 
		// thumbnail of file
		$file = block_exaport_get_item_file($item);
		
		// serve file
		if ($file && $file->is_valid_image()) {
			send_stored_file($file);
			exit;
		}

		header('Location: pix/file_tile.png');
		break;

	case "link":
		$url = $item->url;
		if (strpos($url,'http')===false)
			$url = 'http://'.$url;

		$str = file_get_contents($url);

		if ($str && preg_match('/<img\s.*src=[\'"]([^\'"]+)[\'"]/im', $str, $matches)) {
			$first_img = $matches[1];
			if (strpos($first_img,'http')===false) {
				if ($first_img[0] == '/') {
					// google.com		+ /imgage.png
					// google.com/sub	+ /imgage.png
					$first_img = preg_replace('!([^:/])/.*$!m', '$1', $url).$first_img;
				} else {
					// google.com		+ imgage.png
					$first_img = $url."/".$first_img;
				}
			}
			
			$headers = get_headers($first_img, 1);
			$type = $headers["Content-Type"];
			
			$imgstr=@file_get_contents($first_img);
			//echo strlen($imgstr);
			if (strlen($imgstr)<50){
				header('Location: pix/link_tile.png');
				break;
			}
			header("Content-type: ".$type);
			echo $imgstr;
			
			exit;
		}

		header('Location: pix/link_tile.png');
		break;

	case "note":
		header('Location: pix/note_tile.png');
		break;
	default:
		die('wrong type');
}