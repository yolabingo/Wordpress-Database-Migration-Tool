#!/bin/bash
# fix perms and create symlinks

# echo "Options \n  +FollowSymLinks" > .htaccess
ln -fs move-wordpress.php index.php
ln -fs move-wordpress-cli.php do-move-wordpress-cli.php 

chmod 755 .
chmod 644  favicon.png  grey-s.png  index.php  move-wordpress.inc.php  move-wordpress.php .htaccess
chmod 600 README
chmod 700 install.sh  move-wordpress-cli.php 
