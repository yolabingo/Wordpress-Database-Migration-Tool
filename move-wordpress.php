<?php 

# Change the filesystem path and/or URL for a Wordpress site
require_once('move-wordpress.inc.php');
print_page_header();

if ($_POST) { 
    $validation_errors = false;
    $vars = $_POST;
    // Do simple sanity checks 
    if ($vars['validate_form'] == 1) {
        $validation_errors = validate($vars);
    }
    if ($validation_errors) {
        $vars = array_merge($vars, $validation_errors);
    } else {
    	if (debug()) { echo print_r($vars); }
        update_db($vars);
        exit();
    }
} else {
    $vars = get_form_vars(array('mysql','url', 'path'));
} ?>

        Update a Wordpress database when moving a Wordpress site from one user account to another, <br />
        transferring a site from another host, etc. <?php

        $backup_alert =  ' onsubmit="return confirm(\'I hope you backed up the database.  Proceed?\');"';
        if ($_POST) {
            $backup_alert = ''; ?>
            <span class="errormsg"><h4>Please fix errors below</h4></span> <?php
        } ?>
        <form action="move-wordpress.php" name="wp-db-form" id="wp-db-form" method="post"<?php echo $backup_alert;?>> 
            <div class="formsubsection">
                <h4>Required Mysql info</h4>
                <?php print_form_section('mysql', $vars); ?>
            </div>
            <div class="formsubsection">
                <h4>Enter URL values to change, e.g., <code>http://www.example.com/blog</code></h4>
                Leave both fields blank to update only the filesystem path.<br />
                <?php print_form_section('url',$vars); ?>
            </div>
            <div class="formsubsection">
                <h4>Enter filesystem path to change, e.g., <code>/home/foobar/public_html/example.com/</code></h4>
                Leave both fields blank to update only the URL.<br />
                <?php print_form_section('path', $vars); ?>
            </div><br /> <?php
            if ($_POST) { ?>
                <label><input type="submit" value="Submit and revalidate" 
                              onclick="document.getElementById('validate_form').value = 1; return true;" />
                </label>
                <label><input type="submit" value="I know what I'm doing. Submit without revalidating"
                               onclick="document.getElementById('validate_form').value = 0; return true;" />
                </label> <?
            } else { ?>
                <label><input type="submit" value="Submit" /></label> <?php
            } ?>
        </form>
    </div>
</body>
</html>
