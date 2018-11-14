<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

defined('MOODLE_INTERNAL') || die();

$functions = array (
  'block_exaport_get_items' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'get_items',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Returns categories and items for a particular level',
    'type' => 'read',
  ),
  'block_exaport_get_item' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'get_item',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Returns detailed information for a particular item',
    'type' => 'read',
  ),
  'block_exaport_add_item' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'add_item',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Adds a new item to the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_update_item' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'update_item',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Edit an item from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_delete_item' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'delete_item',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Delete an item from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_add_item_comment' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'add_item_comment',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Add a comment to an item',
    'type' => 'read',
  ),
  'block_exaport_list_competencies' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'list_competencies',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'List all available competencies',
    'type' => 'read',
  ),
  'block_exaport_set_item_competence' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'set_item_competence',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'assign a competence to an item',
    'type' => 'read',
  ),
  'block_exaport_get_views' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'get_views',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Return available views',
    'type' => 'read',
  ),
  'block_exaport_get_view' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'get_view',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Return detailed view',
    'type' => 'read',
  ),
  'block_exaport_add_view' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'add_view',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Add a new view to the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_update_view' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'update_view',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Edit a view from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_delete_view' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'delete_view',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Delete a view from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_get_all_items' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'get_all_items',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Return all items, independent from level',
    'type' => 'read',
  ),
  'block_exaport_add_view_item' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'add_view_item',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Add item to a view',
    'type' => 'write',
  ),
  'block_exaport_delete_view_item' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'delete_view_item',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Remove item from a view',
    'type' => 'write',
  ),
  'block_exaport_view_grant_external_access' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'view_grant_external_access',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Grant external access to a view',
    'type' => 'write',
  ),
  'block_exaport_view_get_available_users' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'view_get_available_users',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Get users who can get access',
    'type' => 'read',
  ),
  'block_exaport_view_grant_internal_access_all' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'view_grant_internal_access_all',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Grant internal access to a view to all users',
    'type' => 'write',
  ),
  'block_exaport_view_grant_internal_access' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'view_grant_internal_access',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Grant internal access to a view to one user',
    'type' => 'write',
  ),
  'block_exaport_get_category' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'get_category',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Get category infor',
    'type' => 'read',
  ),
  'block_exaport_delete_category' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'delete_category',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Delete category',
    'type' => 'write',
  ),
  'block_exaport_get_competencies_by_item' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'get_competencies_by_item',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Get competence ids for a ePortfolio item',
    'type' => 'read',
  ),
  'block_exaport_get_users_by_view' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'get_users_by_view',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Get view users',
    'type' => 'read',
  ),
  'block_exaport_export_file_to_externalportfolio' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'export_file_to_externalportfolio',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'Export file to external portfolio',
    'type' => 'write',
  ),
  'block_exaport_login' => array (
    'classname' => 'block_exaport_external',
    'methodname' => 'login',
    'classpath' => 'blocks/exaport/externallib.php',
    'description' => 'webservice called through token.php',
    'type' => 'read',
  ),
);

$services = array (
  'exaportservices' => array (
    'requiredcapability' => '',
    'restrictedusers' => 0,
    'enabled' => 1,
    'shortname' => 'exaportservices',
    'functions' => array (
      0 => 'block_exaport_get_items',
      1 => 'block_exaport_get_item',
      2 => 'block_exaport_add_item',
      3 => 'block_exaport_update_item',
      4 => 'block_exaport_delete_item',
      5 => 'block_exaport_add_item_comment',
      6 => 'block_exaport_list_competencies',
      7 => 'block_exaport_set_item_competence',
      8 => 'block_exaport_get_views',
      9 => 'block_exaport_get_view',
      10 => 'block_exaport_add_view',
      11 => 'block_exaport_update_view',
      12 => 'block_exaport_delete_view',
      13 => 'block_exaport_get_all_items',
      14 => 'block_exaport_add_view_item',
      15 => 'block_exaport_delete_view_item',
      16 => 'block_exaport_view_grant_external_access',
      17 => 'block_exaport_view_get_available_users',
      18 => 'block_exaport_view_grant_internal_access_all',
      19 => 'block_exaport_view_grant_internal_access',
      20 => 'block_exaport_get_category',
      21 => 'block_exaport_delete_category',
      22 => 'block_exaport_get_competencies_by_item',
      23 => 'block_exaport_get_users_by_view',
      24 => 'block_exaport_export_file_to_externalportfolio',
      25 => 'block_exaport_login',
    ),
  ),
);

