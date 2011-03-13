#!/bin/bash

# Try this if the index file is ignored...
# echo "Options" >> .htaccess
# echo "  +FollowSymLinks" >> .htaccess
# chmod 644 .htaccess

# create symlinks

ln -fs move-wordpress.php index.php
ln -fs move-wordpress-cli.php do-move-wordpress-cli.php 

# set perms that should work for many situations 

chmod 755 .
chmod 644  favicon.png  grey-s.png  move-wordpress.inc.php  move-wordpress.php
chmod 600 README
chmod 700 install.sh  move-wordpress-cli.php 
