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
    echo "*** chmod 644 config.php"
fi

echo ""
echo "Your MoniWiki is now secure and cannot be configured.  If"
echo "you wish to reconfigure it, execute the following command:"
echo ""
echo "    % sh config.sh"
echo ""
echo "and open 'monisetup.php' on a webbrowser"
echo ""
echo ""
