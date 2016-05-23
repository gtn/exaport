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

require_once __DIR__.'/inc.php';

 $url = $CFG->wwwroot."/pluginfile.php/1/block_exaport/attachment/437/alom.jpg";
 $url='http://gtn02.gtn-solutions.com/moodle20/blocks/exaport/portfoliofile.php?access=portfolio/id/7&itemid=437&att=437';
 echo "<img src='".$url."'>";
 //echo $url;
 /*
 $itemid=optional_param('id', 0, PARAM_ALPHANUMEXT);
 $hash = get_hash($itemid);
$fs = get_file_storage();
 $file = $fs->get_file_by_hash($hash);

		// Read contents
		if ($file) {
				send_stored_file($file);
		} else {
				not_found();
		}
		

		
		 
		
		function get_hash($itemid) {
	global $DB;

	if ($file_record = $DB->get_record_sql("select min(id), pathnamehash from {files} where itemid={$itemid} AND filename!='.' GROUP BY id, pathnamehash")) {
		return $file_record->pathnamehash;
	} else {
		return false;
	}
}
		*/
