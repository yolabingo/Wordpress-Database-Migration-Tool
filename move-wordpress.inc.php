<?php 

function debug() { return false; }

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
        $vars['db_name'] = '';
        $vars['db_table_prefix'] = 'wp_';
        $vars['validate_form'] = '1';
    } 
    if (in_array('url', $form_section)) { 
        $vars['url_old'] = '';
        $vars['url_new'] = '';
    } 
    if (in_array('path', $form_section)) { 
        $vars['path_old'] = '';
        $vars['path_new'] = '';
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
    foreach ($vars as $form_field => $v) {
        $error_key = $form_field .'_error';

        if ($form_field == 'db_user') {
            if (in_array($v, $excluded_mysql_users)) {
                $errors[$form_field] = 'Non-privileged user, please';
                $errors[$error_key] = 'Privileged MySQL users not allowed';
            }
        }
        if (in_array($form_field, array('db_host','db_user','db_pass','db_name'))) {
            if (! $v) { $errors[$error_key] = 'Required field.'; }
        }

        if (in_array($form_field, array('url_old', 'path_old'))) {
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
                // 'old' and 'new' values should both end with a "/", or neither should...
                $last_chars = array(substr($v, -1, 1), substr($vars[$new_key], -1, 1));
                if (in_array('/', $last_chars) && ($last_chars[0] != $last_chars[1])) {
                    $errors[$error_key] = "Trailing slashes don't jive. ";
                }

                // URL's should start with https?://
                if ($form_field == 'url_old') {
                    $pattern = '/^https?:\/\//';
                    $pattern_error = "<br />URL's should start with 'http://' or 'https://'";
                    if (! (preg_match($pattern, $v) && preg_match($pattern, $vars[$new_key])) ) {
                        $errors[$error_key] .= $pattern_error;
                    } elseif (substr($v, -1, 1) == '/') {
                        $errors[$error_key] .= "Trailing slash on URL's are bad.";
		    }

                }
                /*  
                    Add filesystem path regex validation here if you like.
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
        if (debug()) { echo "change_url_queries()<br />"; }
        change_url_queries($dbh, $vars['url_old'], $vars['url_new'], $vars['db_table_prefix']);
        // update URL's in wp_options
        if (debug()) { echo "change_wp_options()<br />"; }
        change_wp_options($dbh, $vars['url_old'], $vars['url_new'], $vars['db_table_prefix']);
    }
    
    if ($vars['path_old'] && $vars['path_new']) {
        if (debug()) { echo "<br />Updating filesystem path<br />"; }
        // update filesystem paths in wp_options
        change_wp_options($dbh, $vars['path_old'], $vars['path_new'], $vars['db_table_prefix']);
    }
}

## The rest of the MODEL-ISH functions below are helpers for update_db()

function change_url_queries($dbh, $old, $new, $table_prefix) {
    $posts_table = $table_prefix . 'posts';
    do_update_query("UPDATE $posts_table SET post_content = REPLACE(post_content, '$old', '$new')", $dbh);
    do_update_query("UPDATE $posts_table SET guid = REPLACE(guid, '$old','$new')", $dbh);
}

# Some wp_options are stored as serialized PHP arrays.  Some are not.  
# If an option is just a regular string, we change it with MySQL REPLACE().
# Otherwise, we need to unserialize/change/reserialize.
#
function change_wp_options($dbh, $old, $new, $table_prefix) {
    $table = $table_prefix . 'options';
    $retrieved_ids = array();

    // This could be done as a simple loop, without the annoying "LIMIT 1" business.
    // But if you've got a large database and a server with limited RAM, you may run out of memory.
    $find_affected = "SELECT option_id, option_value FROM $table WHERE option_value LIKE '%$old%' LIMIT 1";
    $result = mysql_query($find_affected, $dbh);
    if (mysql_num_rows($result) === false) { ?>
        <span class="errormsg">Error executing query:<br /><pre><?php echo $find_affected;?></pre></span><hr /><?php
    }

    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
        unset($result);
        $retrieved_ids[] = $option_id = $row['option_id']; 
        // unserlalize() returns false if the passed value is not a valid serialized array...
        if (unserialize(trim($row['option_value']))) {
            $row['option_value'] = unserialize(trim($row['option_value']));
            $row['option_value'] = update_serialized_array($old, $new, $row['option_value']); 
            $row['option_value'] = serialize($row['option_value']);
            do_update_query("UPDATE $table SET option_value = '" . mysql_real_escape_string($row['option_value'], $dbh) . "' WHERE option_id = $option_id", $dbh);
        } elseif (preg_match('/^a:[0-9]+:/', trim($row['option_value']))) { ?>
            <span class="errormsg">This looks like a serialized array, but I can't unpack it.  Maybe it's broken?<br /><pre> <?php 
            echo substr(trim($row['option_value']), 0, 30) . '...</pre></span><hr />';
        } else {
            // Use SQL to update this row
            do_update_query("UPDATE $table SET option_value = REPLACE(option_value, '$old', '$new') WHERE option_id = $option_id", $dbh);
        }

        unset($row);
        // Get the next row to be updated.
        // Don't get the same row over and over - like a malformed serialized array, for instance
        $find_affected  = "SELECT option_id, option_value FROM $table WHERE (option_value LIKE '%$old%') ";
        if ($retrieved_ids) { $find_affected .= "AND option_id NOT IN ( " . trim(join(', ', $retrieved_ids), ', ') . " ) "; }
        $find_affected .= "LIMIT 1";
        $result = mysql_query($find_affected, $dbh);
        if (mysql_num_rows($result) === false) { ?>
            <span class="errormsg">Error executing query:<br /><pre><?php echo $find_affected;?></pre></span><hr /><?php
        }
    }
    if (! $retrieved_ids) {
        echo "<br />No options containing '" . $old ."' were found.<br />";
    }
}

# str_replace() claims to work on array's as subjects, but it isn't recursive :(
# Replace all occurances of $old with $new in string values in the array $subject
# This function is a modification of something from here: http://www.php.net/manual/en/function.serialize.php
# as I recall.
#
function update_serialized_array($search, $replace, $subject) { 
    if (is_array($subject)) { 
        foreach($subject as &$oneSubject) {
            $oneSubject = update_serialized_array($search, $replace, $oneSubject); 
        }
        unset($oneSubject); 
        return $subject; 
    } else { 
        return str_replace($search, $replace, $subject); 
    } 
} 

# all our queries will be UPDATE's
function do_update_query($query, $dbh) {
    mysql_query($query, $dbh);
    if (mysql_affected_rows() > -1) { ?>
        <span>Successful query: <pre><?php 
        foreach (str_split($query, 120) as $v) {
            echo htmlspecialchars($v) ."<br />";
	} ?>
	</pre><br /><?php
        echo ((string) mysql_affected_rows()) . "  row(s) updated. </span><hr />";
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
        // This hidden field is the only special case.
        if ($k == 'validate_form') { ?> 
            <input type="hidden" name="validate_form" id="validate_form" value="1" /> <?php 
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
                <input <? echo $input_field_attrs;?> /><br />
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

                <td valign="middle" style="margin:0px;"><h2>Wordpress Database Frobber</h2></td>
            </tr>
        </table> <?php
}
