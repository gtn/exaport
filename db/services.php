<?php

$functions = array (
  'block_exaport_view_list' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_list',
    'description' => 'Return available views',
    'type' => 'read',
  ),
  'block_exaport_view_details' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_details',
    'description' => 'Return detailed view',
    'type' => 'read',
  ),
  'block_exaport_view_add' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_add',
    'description' => 'Add a new view to the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_view_update' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_update',
    'description' => 'Edit a view from the users portfolio',
    'type' => 'write',
  ),
  'block_exaport_view_delete' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_delete',
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
  'block_exaport_view_block_add' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_block_add',
    'description' => 'Add item to a view',
    'type' => 'write',
  ),
  'block_exaport_view_block_delete' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_block_delete',
    'description' => 'Remove item from a view',
    'type' => 'write',
  ),
  'block_exaport_login' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'login',
    'description' => 'webservice called through token.php',
    'type' => 'read',
  ),
  'block_exaport_view_block_add_multiple' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_block_add_multiple',
    'description' => '',
    'type' => 'write',
  ),
  'block_exaport_view_block_sorting' => 
  array (
    'classname' => '\\block_exaport\\externallib\\externallib',
    'methodname' => 'view_block_sorting',
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

