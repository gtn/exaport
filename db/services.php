<?php

$functions = array (
  'block_exaport_get_items' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_items',
    'description' => 'Returns categories and items for a particular level',
    'type' => 'read',
  ),
  'block_exaport_get_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_item',
    'description' => 'Returns detailed information for a particular item',
    'type' => 'read',
  ),
  'block_exaport_add_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'add_item',
    'description' => 'Adds a new item to the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_update_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'update_item',
    'description' => 'Edit an item from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_delete_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'delete_item',
    'description' => 'Delete an item from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_add_item_comment' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'add_item_comment',
    'description' => 'Add a comment to an item',
    'type' => 'read',
  ),
  'block_exaport_list_competencies' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'list_competencies',
    'description' => 'List all available competencies',
    'type' => 'read',
  ),
  'block_exaport_set_item_competence' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'set_item_competence',
    'description' => 'assign a competence to an item',
    'type' => 'read',
  ),
  'block_exaport_get_views' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_views',
    'description' => 'Return available views',
    'type' => 'read',
  ),
  'block_exaport_get_view' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_view',
    'description' => 'Return detailed view',
    'type' => 'read',
  ),
  'block_exaport_add_view' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'add_view',
    'description' => 'Add a new view to the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_update_view' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'update_view',
    'description' => 'Edit a view from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_delete_view' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'delete_view',
    'description' => 'Delete a view from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_get_all_items' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_all_items',
    'description' => 'Return all items, independent from level',
    'type' => 'read',
  ),
  'block_exaport_add_view_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'add_view_item',
    'description' => 'Add item to a view',
    'type' => 'write',
  ),
  'block_exaport_delete_view_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'delete_view_item',
    'description' => 'Remove item from a view',
    'type' => 'write',
  ),
  'block_exaport_view_grant_external_access' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_grant_external_access',
    'description' => 'Grant external access to a view',
    'type' => 'write',
  ),
  'block_exaport_view_get_available_users' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_get_available_users',
    'description' => 'Get users who can get access',
    'type' => 'read',
  ),
  'block_exaport_view_grant_internal_access_all' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_grant_internal_access_all',
    'description' => 'Grant internal access to a view to all users',
    'type' => 'write',
  ),
  'block_exaport_view_grant_internal_access' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_grant_internal_access',
    'description' => 'Grant internal access to a view to one user',
    'type' => 'write',
  ),
  'block_exaport_get_category' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_category',
    'description' => 'Get category infor',
    'type' => 'read',
  ),
  'block_exaport_delete_category' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'delete_category',
    'description' => 'Delete category',
    'type' => 'write',
  ),
  'block_exaport_get_competencies_by_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_competencies_by_item',
    'description' => 'Get competence ids for a ePortfolio item',
    'type' => 'read',
  ),
  'block_exaport_get_users_by_view' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_users_by_view',
    'description' => 'Get view users',
    'type' => 'read',
  ),
  'block_exaport_export_file_to_externalportfolio' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'export_file_to_externalportfolio',
    'description' => 'Export file to external portfolio',
    'type' => 'write',
  ),
  'block_exaport_login' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'login',
    'description' => 'webservice called through token.php',
    'type' => 'read',
  ),
  'block_exaport_get_shared_categories' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_shared_categories',
    'description' => '',
    'type' => 'read',
  ),
);

$services = array (
  'exaportservices' => 
  array (
    'requiredcapability' => '',
    'restrictedusers' => 0,
    'enabled' => 1,
    'shortname' => 'exaportservices',
    'functions' => array_keys($functions),
    'downloadfiles' => 1,
    'uploadfiles' => 1,
  ),
);

