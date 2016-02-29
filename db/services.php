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

call_user_func(function() use (&$functions, &$services) {
	$definitions = [
		[ 'block_exaport_get_items', 'read', 'Returns categories and items for a particular level' ],
		[ 'block_exaport_get_item', 'read', 'Returns detailed information for a particular item' ],
		[ 'block_exaport_add_item', 'write', 'Adds a new item to the users portfolio' ],
		[ 'block_exaport_update_item', 'write', 'Edit an item from the users portfolio' ],
		[ 'block_exaport_delete_item', 'write', 'Delete an item from the users portfolio' ],
		[ 'block_exaport_list_competencies', 'read', 'List all available competencies' ],
		[ 'block_exaport_set_item_competence', 'read', 'assign a competence to an item' ],
		[ 'block_exaport_get_views', 'read', 'Return available views' ],
		[ 'block_exaport_get_view', 'read', 'Return detailed view' ],
		[ 'block_exaport_add_view', 'write', 'Add a new view to the users portfolio' ],
		[ 'block_exaport_update_view', 'write', 'Edit a view from the users portfolio' ],
		[ 'block_exaport_delete_view', 'write', 'Delete a view from the users portfolio' ],
		[ 'block_exaport_get_all_items', 'read', 'Return all items, independent from level' ],
		[ 'block_exaport_add_view_item', 'write', 'Add item to a view' ],
		[ 'block_exaport_delete_view_item', 'write', 'Remove item from a view' ],
		[ 'block_exaport_view_grant_external_access', 'write', 'Grant external access to a view' ],
		[ 'block_exaport_view_get_available_users', 'read', 'Get users who can get access' ],
		[ 'block_exaport_view_grant_internal_access_all', 'write', 'Grant internal access to a view to all users' ],
		[ 'block_exaport_view_grant_internal_access', 'write', 'Grant internal access to a view to one user' ],
		[ 'block_exaport_get_category', 'read', 'Get category infor' ],
		[ 'block_exaport_delete_category', 'write', 'Delete category' ],
		[ 'block_exaport_get_competencies_by_item', 'read', 'Get competence ids for a ePortfolio item' ],
		[ 'block_exaport_get_users_by_view', 'read', 'Get view users' ],
		[ 'block_exaport_export_file_to_externalportfolio', 'write', 'Export file to external portfolio' ],
	];

	foreach ($definitions as $definition) {
		$functions[$definition[0]] = [                             // web service function name
				'classname'   => 'block_exaport_external',         // class containing the external function
				'methodname'  => str_replace('block_exaport_', '', $definition[0]), // external function name, strip block_exacomp_ for function name
				'classpath'   => 'blocks/exaport/externallib.php', // file containing the class/external function
				'description' => $definition[2],	               // human readable description of the web service function
				'type'		  => $definition[1],	               // database rights of the web service function (read, write)
		];

		$services['exaportservices']['functions'][] = $definition[0];
	}
});
