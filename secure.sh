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

[ -f imgs_htaccess ] && [ ! -f imgs/.htaccess ] &&
    cp imgs_htaccess imgs/.htaccess && rm imgs_htaccess

echo ""
echo "Your MoniWiki is now secure and cannot be configured."
echo "If you wish to reconfigure it, execute the following command:"
echo ""
echo "    % sh monisetup.sh"
echo ""
echo "and open 'monisetup.php' on a web browser."
echo ""
echo ""
