<?php
$services = array(
		'exaportservices' => array(                        //the name of the web service
				'functions' => array (
						'block_exaport_get_items',
						'block_exaport_get_item',
						'block_exaport_add_item'/*,
						'block_exaport_update_item',
						'block_exaport_delete_item',
						'block_exaport_list_competencies',
						'block_exaport_set_item_competence',
						'block_exaport_get_views',
						'block_exaport_get_view',
						'block_exaport_add_view',
						'block_exaport_update_view',
						'block_exaport_delete_view',
						'block_exaport_get_all_items',
						'block_exaport_add_view_item',
						'block_exaport_delete_view_item',
						'block_exaport_view_grant_external_access',
						'block_exaport_view_get_available_users',
						'block_exaport_view_grant_internal_access'*/
						),
				'restrictedusers' =>0,                      //if enabled, the Moodle administrator must link some user to this service
				//into the administration
				'enabled'=>1,                               //if enabled, the service can be reachable on a default installation
		)
);


$functions = array(
		'block_exaport_get_items' => array(         //web service function name
				'classname'   => 'block_exaport_external',  //class containing the external function
				'methodname'  => 'get_items',          //external function name
				'classpath'   => 'blocks/exaport/externallib.php',  //file containing the class/external function
				'description' => 'Returns categories and items for a particular level',    //human readable description of the web service function
				'type'        => 'read'                  //database rights of the web service function (read, write)
		),
		'block_exaport_get_item' => array(         //web service function name
				'classname'   => 'block_exaport_external',  //class containing the external function
				'methodname'  => 'get_item',          //external function name
				'classpath'   => 'blocks/exaport/externallib.php',  //file containing the class/external function
				'description' => 'Returns detailed information for a particular item',    //human readable description of the web service function
				'type'        => 'read'                  //database rights of the web service function (read, write)
		),
		'block_exaport_add_item' => array(         //web service function name
				'classname'   => 'block_exaport_external',  //class containing the external function
				'methodname'  => 'add_item',          //external function name
				'classpath'   => 'blocks/exaport/externallib.php',  //file containing the class/external function
				'description' => 'Adds a new item to the users portfolio',    //human readable description of the web service function
				'type'        => 'write'                  //database rights of the web service function (read, write)
		)
);
?>