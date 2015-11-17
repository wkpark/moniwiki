#!/bin/sh
SUCCESS="printf \033[1;32m"
FAILURE="printf \033[1;31m"
WARNING="printf \033[1;33m"
MESSAGE="printf \033[1;34m"
NORMAL="printf \033[0;39m"
MAGENTA="printf \033[1;35m"

$WARNING
echo
echo "+-------------------------------------+"
echo "|    MoniWiki configuration script    |"
echo "+-------------------------------------+"
echo
$NORMAL

if [ -f config.php ]; then
    $MESSAGE
    mv config.php config.php.$$
    cp config.php.$$ config.php
    rm config.php.$$
    chmod 644 config.php
    chmod 711 . data
    echo "*** chmod 644 config.php"
    $NORMAL
fi

IMG_DIR=`cat config.php |grep '$imgs_dir='|cut -d\' -f2`
[ -n "$IMG_DIR" ] && [ -f imgs_htaccess ] && [ ! -f .$IMG_DIR/.htaccess ] &&
    cp imgs_htaccess .$IMG_DIR/.htaccess && rm imgs_htaccess

PDS_DIR=`cat config.php |grep '$upload_dir='|cut -d\' -f2`
[ -n "$PDS_DIR" ] && [ -f pds_htaccess ] && [ ! -f $PDS_DIR/.htaccess ] &&
    cp pds_htaccess $PDS_DIR/.htaccess && rm pds_htaccess

$SUCCESS
echo ""
echo "Your MoniWiki is now secure and cannot be configured."
echo "If you wish to reconfigure it, execute the following command:"
echo ""
echo -n "    "
$MESSAGE
echo -n "$ "
$NORMAL
echo sh monisetup.sh
$SUCCESS
echo ""
echo "and open 'monisetup.php' on a web browser."
echo ""
echo ""
$NORMAL
