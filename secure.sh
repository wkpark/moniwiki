#!/bin/bash
# $Id$
echo
echo "+-------------------------------------+"
echo "|    MoniWiki configuration script    |"
echo "+-------------------------------------+"
echo

if [ -f config.php ]; then
    mv config.php config.php.$$
    cp config.php.$$ config.php
    rm config.php.$$
    chmod 644 config.php
    chmod 711 . data
    echo "*** chmod 644 config.php"
fi

IMG_DIR=`cat config.php |grep '$imgs_dir='|cut -d\' -f2`
[ -n "$IMG_DIR" ] && [ -f imgs_htaccess ] && [ ! -f .$IMG_DIR/.htaccess ] &&
    cp imgs_htaccess .$IMG_DIR/.htaccess && rm imgs_htaccess

PDS_DIR=`cat config.php |grep '$upload_dir='|cut -d\' -f2`
[ -n "$PDS_DIR" ] && [ -f pds_htaccess ] && [ ! -f $PDS_DIR/.htaccess ] &&
    cp pds_htaccess $PDS_DIR/.htaccess && rm pds_htaccess

echo ""
echo "Your MoniWiki is now secure and cannot be configured."
echo "If you wish to reconfigure it, execute the following command:"
echo ""
echo "    % sh monisetup.sh"
echo ""
echo "and open 'monisetup.php' on a web browser."
echo ""
echo ""
