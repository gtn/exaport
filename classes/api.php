<?php

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__.'/../lib/lib.php';

use \block_exaport\globals as g;

class api {
	static function active() {
		// check if block is active
		if (!g::$DB->get_record('block',array('name'=>'exaport', 'visible'=>1))) {
			return false;
		}
		
		return true;
	}

	static function delete_user_data($userid){
		global $DB;
		
		$DB->delete_records('block_exaportcate', array('userid'=>$userid));
		$DB->delete_records('block_exaportcatshar', array('userid'=>$userid));
		$DB->delete_records('block_exaportcat_structshar', array('userid'=>$userid));
		$DB->delete_records('block_exaportitem', array('userid'=>$userid));
		$DB->delete_records('block_exaportitemcomm', array('userid'=>$userid));
		$DB->delete_records('block_exaportitemshar', array('userid'=>$userid));
		$DB->delete_records('block_exaportview', array('userid'=>$userid));
		$DB->delete_records('block_exaportviewshar', array('userid'=>$userid));

		$DB->delete_records('block_exaportresume', array('user_id'=>$userid));
		$DB->delete_records('block_exaportuser', array('user_id'=>$userid));

		return true;
	}
}
