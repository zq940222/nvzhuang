<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'express_query' => 
    array (
      0 => 'express',
    ),
    'action_begin' => 
    array (
      0 => 'geetest',
    ),
    'config_init' => 
    array (
      0 => 'geetest',
    ),
    'app_init' => 
    array (
      0 => 'log',
    ),
    'admin_login_init' => 
    array (
      0 => 'loginbg',
    ),
  ),
  'route' => 
  array (
    '/example$' => 'example/index/index',
    '/example/d/[:name]' => 'example/demo/index',
    '/example/d1/[:name]' => 'example/demo/demo1',
    '/example/d2/[:name]' => 'example/demo/demo2',
  ),
);