<?php
require_once("$CFG->libdir/externallib.php");

class block_exaport_external extends external_api {


	/**
	 * Returns description of method parameters
	 * @return external_function_parameters
	 */
	public static function get_childcategories_parameters() {
		return new external_function_parameters(
				array('pid' => new external_value(PARAM_INT, 'id parent categorie'))
		);

	}

	/**
	 * Get subjects
	 * @param int courseid
	 * @return array of course subjects
	 */
	public static function get_childcategories($pid) {
		global $CFG,$DB,$USER;

		if (empty($pid)) {
			$pid=0;
		}

		$params = self::validate_parameters(self::get_childcategories_parameters(), array('pid'=>$pid));
		
		$conditions=array("pid"=>$pid,"userid"=>$USER->id);
		$categories = $DB->get_records_sql('
				SELECT c.id, c.name
				FROM {block_exaportcate} c
				ORDER BY c.name
				', $conditions);
				
		if ($categories){
			return $categories;
		}else{
			return array();
		}
	
	}

	/**
	 * Returns desription of method return values
	 * @return external_multiple_structure
	 */
	public static function get_childcategories_returns() {
		return new external_multiple_structure(
				new external_single_structure(
						array(
								'id' => new external_value(PARAM_INT, 'id of category'),
								'name' => new external_value(PARAM_TEXT, 'title of category')
						)
				)
		);
	}



}