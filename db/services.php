<?php
$services = array(
		'exaportservices' => array(                        //the name of the web service
				'functions' => array (
						'block_exaport_get_childcategories',
						),
				'restrictedusers' =>0,                      //if enabled, the Moodle administrator must link some user to this service
				//into the administration
				'enabled'=>1,                               //if enabled, the service can be reachable on a default installation
		)
);


$functions = array(
		'block_exaport_get_childcategories' => array(         //web service function name
				'classname'   => 'block_exaport_external',  //class containing the external function
				'methodname'  => 'get_childcategories',          //external function name
				'classpath'   => 'blocks/exaport/externallib.php',  //file containing the class/external function
				'description' => 'Get categories and items',    //human readable description of the web service function
				'type'        => 'read',                  //database rights of the web service function (read, write)
		),
);
?>