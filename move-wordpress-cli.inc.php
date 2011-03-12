<?php 
require_once('move-wordpress.inc.php');
$vars = array();
$vars['db_host'] = '1';
$vars['db_user'] = '2';
$vars['db_pass'] = '3';
$vars['db_name'] = '4';
$vars['db_table_prefix'] = '5';
$vars['url_old'] = '6';
$vars['url_new'] = '7';
$vars['path_old'] = '8';
$vars['path_new'] = '9';

unset($argv[0]);
$num_args = count($argv);

if ($num_args != 9) {
    echo " 9 args required: \n   ";
    foreach ($vars as $k => $v) { echo "$k  "; }
    echo "\n\n but $num_args were given: \n   ";
    foreach ($argv as $v) { echo "$v  "; }
    echo "\n\n";
} else {
    foreach ($vars as $k => $v) { $vars[$k] = $argv[$v]; }
    update_db($vars);
}
