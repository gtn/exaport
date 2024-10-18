<?php

$functions = array (
  'block_exaport_delete_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'delete_item',
    'description' => 'Delete an item from the users portfolio',
    'type' => 'write',
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
  'block_exaport_delete_view' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'delete_view',
    'description' => 'Delete a view from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_get_all_user_items' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'get_all_user_items',
    'description' => 'Return all items from user',
    'type' => 'read',
  ),
  'block_exaport_add_view_item' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'add_view_item',
    'description' => 'Add item to a view',
    'type' => 'write',
  ),
  'block_exaport_login' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'login',
    'description' => 'webservice called through token.php',
    'type' => 'read',
  ),
  'block_exaport_add_view_items' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'add_view_items',
    'description' => '',
    'type' => 'write',
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

