<?php 
require_once('move-wordpress.inc.php');
$vars['db_host'] = 'mysql5.swcp.com';
$vars['db_user'] = '4109_wp_5';
$vars['db_pass'] = 'UXZ18eke';
$vars['db_name'] = '4109_wp_5';
$vars['db_table_prefix'] = 'wp_';
$vars['url_old'] = '/users/wayne/public_html/unmpersonaldefense.com';
$vars['url_new'] = '/users/joshua/public_html/unmpersonaldefense.com';

update_db($vars);
