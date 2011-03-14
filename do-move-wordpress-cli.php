<?php  /*
Copyright (C) 2011 Southwest Cyberport http://www.swcp.com/

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.  */

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


# Annoying, but the easiest way to simply strip HTML from the 
# normal output.

if ($argv[0] == 'do-move-wordpress-cli.php') {
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
} else {
    echo "Enter the following required arguments: \n";
    echo "  DB-HOST DB-USERNAME DB-PASSWORD DB-NAME DB-TABLE_PREFIX OLD_URL NEW_URL OLD_PATH NEW_PATH \n\n";
    echo "To omit the URL or path args, just give an empty string\n";
    echo "For example - to change only the filesystem path: \n";
    echo '  localhost wpuser haX0r wordpress wp_ "" "" /home/joe/oldserver /home/joe/newserver' . "\n\n";
    echo "Or vice-versa\n";
    echo '  localhost wpuser haX0r wordpress wp_ http://foo.com http://bar.com "" "" ' . "\n\n";
    $cmd = "php do-move-wordpress-cli.php " .  trim(fgets(STDIN));
    exec($cmd, $output);
    echo "\n\n\n";
    foreach ($output as $v) {
        $out = str_replace('<br />', "\n", strip_tags($v, '<br>')); 
        echo str_replace(array('    ', "\t"), "\n", $out);
        echo "\n";
    }
}
