<?php
 require_once dirname(__FILE__) . '/inc.php';
 require_once dirname(__FILE__) . '/lib/lib.php';
 require_once dirname(__FILE__) . '/lib/sharelib.php';
 global $DB,$USER,$COURSE,$CFG;
 
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


 ?>