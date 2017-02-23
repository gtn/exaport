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

defined('MOODLE_INTERNAL') || die;

$services = array(
	'exaportservices' => array(
		'requiredcapability' => '',
		'restrictedusers' => 0,
		'enabled' => 1,
		'shortname' => 'exaportservices',
		'functions' => [],
	)
);

$functions = [];

require_once __DIR__.'/../externallib.php';
$rc = new ReflectionClass('block_exaport_external');
$methods = $rc->getMethods( ReflectionMethod::IS_STATIC| ReflectionMethod::IS_PUBLIC);
foreach ($methods as $method) {
	if (!preg_match('!@ws-type-(read|write)!', $method->getDocComment(), $matches)) {
		continue;
	}

	$description = preg_replace('!^[/\t \\*]+!m', '', $method->getDocComment());
	$description = trim(preg_replace('!@.*!sm', '', $description));

	$func = 'block_exaport_'.$method->getName();

	$functions[$func] = [                             // web service function name
			'classname'   => 'block_exaport_external',         // class containing the external function
			'methodname'  => $method->getName(), // external function name, strip block_exacomp_ for function name
			'classpath'   => 'blocks/exaport/externallib.php', // file containing the class/external function
			'description' => $description,	               // human readable description of the web service function
			'type'		  => $matches[1],	               // database rights of the web service function (read, write)
	];

	$services['exaportservices']['functions'][] = $func;
}
