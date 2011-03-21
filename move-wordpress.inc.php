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

$show_debug = false;

function debug() { 
    global $show_debug;
    return $show_debug;
}

############ CONTROLLER-ISH DATA-WRANGLING FUNCTIONS ############

# Change the filesystem path and/or URL for a Wordpress site

# values for our form
# $form_section can be one of more of 'mysql','url' or 'path'
#
# These values are used as array keys, and are also used as the
# name & id of form fields in the HTML form.
# 
# If validation fails, we also set an 'xx_error' key - say, "db_name_error",
# as a flag for the code that generates the HTML for the form.
#
function get_form_vars($form_section = '', $vars = array()) {
    if (is_string($form_section)) {
        $form_section = array($form_section);
    }

    // Create necessary array indexes and set some default values
    if (in_array('mysql', $form_section)) {
        $vars['db_host'] = 'localhost';
        $vars['db_user'] = '';
        $vars['db_pass'] = '';
        $vars['db_name'] = 'wordpress';
        $vars['db_table_prefix'] = 'wp_';
    } 
    if (in_array('url', $form_section)) { 
        $vars['url_old'] = '';
        $vars['url_new'] = '';
    } 
    if (in_array('path', $form_section)) { 
        $vars['path_old'] = '';
        $vars['path_new'] = '';
    }
    if (in_array('options', $form_section)) { 
        $vars['validate_form'] = '1';
        // sucky hack
        if ($_POST && (! isset($_POST['no_op']))) {
            $vars['no_op'] = 0;
        } else {
            $vars['no_op'] = 1;
        }
    }
    // Replace default values with POST'ed values if they exist.
    if ($_POST) {
        foreach ($vars as $k => $v) {
            if (array_key_exists($k, $_POST)) {
                $vars[$k] = trim( (string) $_POST[$k] );
            }
        }
    }
    return($vars);
}

# Labels for form input fields
function get_form_label($form_field) {
    $labels = array('db_host' => 'Mysql Server',
                  'db_user' => 'Mysql Username', 
                  'db_pass' => 'Mysql Password', 
                  'db_name' => 'Mysql Database Name', 
                  'db_table_prefix' => 'Mysql Table Prefix',
                  'url_old' => 'Old URL',
                  'url_new' => 'New URL',
                  'path_old' => 'Old filesystem path',
                  'path_new' => 'New filesystem path',
                  'debug' => 'Enable debugging output',
                  'no_op' => '<b>Do not modify database</b><br />Just print queries',
                  'error' => 'An error has occured');
    if (array_key_exists($form_field, $labels)) {
        return($labels[$form_field]);
    }
    return('');
}

# Do some sanity checks of submitted data
function validate($vars) {
    # If you have privileged MySQL users that you do not want 
    # running amok on your DB, add them here.
    $excluded_mysql_users = array('root');
    $errors = array();
    if ($vars['url_old'] == '' && $vars['url_new'] == '') {?>
        <b>No URL's given - skipping URL update.<br /></b> <?php
        unset($vars['url_old']);
        unset($vars['url_new']);
    }

    if ($vars['path_old'] == '' && $vars['path_new'] == '') { ?>
        <b>No filesystem paths given - skipping path update.<br /></b> <?php
        unset($vars['path_old']);
        unset($vars['path_new']);
    }

    foreach ($vars as $form_field => $v) {
        $error_key = $form_field .'_error';

        if ($form_field == 'db_user') {
            if (in_array($v, $excluded_mysql_users)) {
                $errors[$form_field] = 'Non-privileged user, please';
                $errors[$error_key] = 'Privileged MySQL users not allowed';
            }
        } elseif (in_array($form_field, array('db_host','db_user','db_pass','db_name'))) {
            if (! $v) { $errors[$error_key] = 'Required field.'; }
        } elseif (in_array($form_field, array('url_old', 'path_old'))) {
            // For both URL and path, either both fields must be empty, or
            // both fields are non-empyt.
            if ($form_field == 'url_old') { 
                $new_key = 'url_new';
            } elseif ($form_field == 'path_old') {
                $new_key = 'path_new'; 
            }
            if ( ($v && (! $vars[$new_key])) || ((! $v) && $vars[$new_key]) ) {
	        if ($v) {
                    $errors[$new_key . '_error'] = 'Both old and new values must be specified.  Leave both blank to skip.';
		} else {
                    $errors[$error_key] = 'Both old and new values must be specified.  Leave both blank to skip.';
		}
            } elseif ($v) {
                // URL's should start with https?://
                if ($form_field == 'url_old') {
                    $pattern = '/^https?:\/\//';
                    $pattern_error = "<br />URL's should start with 'http://' or 'https://'";
                    if (! (preg_match($pattern, $v) && preg_match($pattern, $vars[$new_key])) ) {
                        $errors[$error_key] .= $pattern_error;
		    }

                }
                /*  
                    // Add filesystem path regex validation here if you like.
                else {
                    $pattern = '/home\/[^\/]+\/public_html\//';
                    $pattern_error = "<br />Expected to something like '/home/foobar/public_html/' in the destination.";
                    if (! preg_match($pattern, $vars[$new_key])) {
                        $errors[$new_key .'_error'] .= $pattern_error;
                    }
                }
                */
            }
        }
    }
    if (debug()) { echo "Leaving validate()<br /><pre>". print_r($vars) ."</pre>"; }
    return($errors);
}

############ MODEL-ISH MYSQL FUNCTIONS ############

# update mysql database 
# this is the only function called by the "controller"
function update_db($vars) {
    $queries = array();
    if (in_array($vars['db_user'], array('root','webroot'))) {
        echo 'Privileged Mysql users not permitted';
        exit();
    }
    if (debug()) { echo "<pre>" . print_r($vars) . "</pre>"; }
    $dbh = mysql_connect($vars['db_host'], $vars['db_user'], $vars['db_pass']);
    if (!$dbh) {
        echo "Host: '" . $vars['db_host'] ."'<br />Username: '" . $vars['db_user'] ."'<br />Password: '" . $vars['db_pass'] ."'<br />";
        die('Could not connect to mysql database server: ' . mysql_error() );
    }
    if (! mysql_select_db($vars['db_name'], $dbh) ) {
        die('Could not use mysql database ' . $vars['db_name'] . ': ' . mysql_error() );
    }

    if ($vars['url_old'] && $vars['url_new']) {
        if (debug()) { echo "<br />Updating URL's<br />"; }
        // update URL in posts and such
        $queries = gen_sql($vars['url_old'], $vars['url_new'], $vars['db_table_prefix']);
    }
    
    if ($vars['path_old'] && $vars['path_new']) {
        if (debug()) { echo "<br />Updating filesystem path<br />"; }
        // update filesystem paths
        $queries = array_merge($queries, gen_sql($vars['path_old'], $vars['path_new'], $vars['db_table_prefix']) );
    }
    if (isset($vars['no_op']) && $vars['no_op']) { ?>
        <br /><b>No changes have been made.<br />These are the queries that would be executed:</b><br /> <?php
        foreach ($queries as $query) { 
            echo "<p> $query </p>"; 
        } ?>
        <b>Uncheck the <a href="#move-wp-options"><i>Do not modify database</i> option</a> to execute these queries.</b><br /><?php
    } else {
        foreach ($queries as $query) {
            do_update_query($query, $dbh);
        }
        echo "<h2>Migrate another site</h2><br />";
    }
}

## The rest of the MODEL-ISH functions below are helpers for update_db()

function gen_sql($old, $new, $table_prefix) {
    // Strip trailing slashes
    $old = rtrim($old, '/');
    $new = rtrim($new, '/');

    $queries = array();
    $update_cols = array();
    $update_cols[] = array('table' => $table_prefix . 'posts', 'col' => 'post_content');
    $update_cols[] = array('table' => $table_prefix . 'posts', 'col' => 'guid');
    $update_cols[] = array('table' => $table_prefix . 'options', 'col' => 'option_value');

    // Create basic update queries
    foreach ($update_cols as $v) {
        $t = $v['table'];
        $col = $v['col'];
        $queries[] = "UPDATE $t SET $col = REPLACE($col, '$old', '$new');";
    }

    // Create update queries for strings in serialized arrays
    $old = get_serial_chunk($old);
    $new = get_serial_chunk($new);
    foreach ($update_cols as $v) {
        $t = $v['table'];
        $col = $v['col'];
        $queries[] = "UPDATE $t SET $col = REPLACE($col, '$old', '$new') WHERE $col LIKE 'a:%:{%" . $old ."%;}' ;";
    }

    // Move the "serialized" queries to the start of the array.
    return array_reverse($queries);
}

function get_serial_chunk($str) {
    return 's:' . (string) strlen($str) . ':"' . $str . '"';
}

# execute SQL and output some useful information
function do_update_query($query, $dbh) {
    mysql_query($query, $dbh);
    if (mysql_affected_rows() > -1) { ?>
        <span>Successful query: 
            <pre><?php echo $query; ?><br /></pre> <?php
            echo ((string) mysql_affected_rows()) . "  row(s) updated."; ?>
        </span><hr /> <?php
    } else { ?>
        <span class="errormsg">Error executing query:<br /><pre><?php echo $query;?></pre></span><hr /><?php
    }
}

############ VIEWISH HTML-GENERATING FUNCTIONS ############

# Spit out HTML - e.g.,
# <span>
#    <label for="db_table_prefix">Mysql Table Prefix
#        <input type="text" size="50" id="db_table_prefix" name="db_table_prefix" value="wp_" />
#    </label>
# </span> 
function print_form_section($form_section, $vars) {
    foreach (get_form_vars($form_section) as $k => $v) { 
        if ($k == 'validate_form') { ?>
            <input type="hidden" name="validate_form" id="validate_form" value="<?php echo $v; ?>" /> <?php 
        } elseif ($k == 'no_op') { 
            $checked = $v ? ' checked="yes" ' : ''; ?>
            <span><label for="<?php echo $k;?>"> <?php 
                echo get_form_label($k); ?>
                <input type="checkbox" name="<?php echo $k; ?>" id="<?php echo $k; ?>" 
                       value="<?php echo $k; ?>" <?php echo $checked; ?> /> 
                </label>
            </span><?php 
        } else {
            $validation_error = $k . '_error';
            if (array_key_exists($validation_error, $vars)) { ?>
                <span class="errormsg"><?php echo $vars[$validation_error] . '  '; 
            } else { ?>
                <span> <?php
            } ?>
            <label for="<?php echo $k;?>">
            <?php echo get_form_label($k);

                $input_field_attrs  = '  type="text" size="50" ';
                $input_field_attrs .= ' id="' . $k . '" ';
                $input_field_attrs .= ' name="' . $k . '" ';
                if ($v) { $input_field_attrs .= ' value="' . $v . '" '; } ?>
                <input <?php echo $input_field_attrs; ?> /><br />
            </label>
            </span><?php
        }
    }
}

function print_page_header() { 
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"0>
    <title>Update Wordpress Database</title>
     <style type="text/css" media="screen">
     	body {
            background-color: gray;	
	}
        #content {
            background-color: white;
            width: 90%;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 60px;
            padding: 20px;
        } .formsubsection {
            padding: 12px;
            margin-top: 4px;
            margin-bottom: 4px;
            margin-left: auto;
            margin-right: auto;
            border-top: gray solid 12px;
            border-bottom: gray solid 12px;
            border-left: gray solid 2px;
            border-right: gray solid 2px;
	    float: left;
	    clear: both;
	    width: 90%;
            -moz-border-radius: 12px;      
            -webkit-border-radius: 12px;
            border-radius: 12px;
        }
        .errormsg { 
            padding-left: 10%;
            padding-top: 8px;
	    color: red; 
	    float: left; 
	    clear: left; 
	}
        label  { 
            padding-left: 10%;
            padding-top: 8px;
	    width: 200px;
	    float: left;
	    clear: both;
        }
        form { margin-bottom: 2000px; }
        input[type="text"] { float: left; clear: both; }
        input[type="text"]:focus { background-color: #99ccff; }
        input[type="submit"]:focus { border: solid 3px #99ccff;}
        input[type="submit"]:hover { border: solid 3px #99ccff; }
    </style>
    <link rel="icon" href="favicon.png"" type="image/png">
</head>
<body>
    <div id="content"> 
        <table>
            <tr><td><a href="move-wordpress.php"><img src="grey-s.png" /></a></td>

                <td valign="middle" style="margin:0px;"><h2><a href="move-wordpress.php">Wordpress Database Migration Tool</a></h2></td>
            </tr>
        </table>
        <br /> 
        <h3>Update URL's and filesystem paths in a Wordpress database</h3><br /> <?php
}
