#!/bin/sh
# $Id$

if [ -z "$1" ]; then
	cat <<HELP
Usage: $0 moniwiki-<ver>.tgz
HELP
	exit 0
fi

SUCCESS="echo -en \\033[1;32m"
FAILURE="echo -en \\033[1;31m"
WARNING="echo -en \\033[1;33m"
MESSAGE="echo -en \\033[1;34m"
NORMAL="echo -en \\033[0;39m"

$SUCCESS
echo
echo "+-------------------------------+"
echo "|    MoniWiki upgrade script    |"
echo "+-------------------------------+"
echo
$WARNING
echo -n " Press enter to continue "
$NORMAL
read

CHECKSUM=
PACKAGE=moniwiki
FILELIST="wiki.php wikilib.php wikismiley.php plugin/*.php plugin/processor/*.php"
for arg; do

        case $# in
        0)
                break
                ;;
        esac

        option=$1
        shift

        case $option in
        -show|-s)
		show=1
                ;;
	*)
		TAR=$option
	esac
done


if [ ! -f "$CHECKSUM" ];then
	$MESSAGE
	echo "*** Make a checksum for current version ***"
	$NORMAL
	md5sum $FILELIST > checksum-current
	CHECKSUM=checksum-current
fi

#
TMP=.tmp$$
$MESSAGE
echo "*** Extract tarball ***"
$NORMAL
mkdir -p $TMP
echo tar xzf $TAR -C$TMP
tar xzf $TAR -C$TMP
$MESSAGE
echo "*** Make checksum list for the new version ***"
$NORMAL
(cd $TMP/$PACKAGE; md5sum $FILELIST > ../../checksum-new)

UPGRADE=`diff checksum-current checksum-new |grep '^<'|cut -d' ' -f4`

if [ -z $UPGRADE ]; then
	echo "You have already installed the latest version"
	exit
fi
$MESSAGE
echo "*** Backup the old files ***"
$NORMAL
DATE=`date +%Y%m%d-%s`
BACKUP=backup/$DATE
mkdir -p $BACKUP
tar cf - $UPGRADE|(cd $BACKUP;tar xvf -)
$MESSAGE
echo "   Old files are backuped to the $BACKUP/ dir"
$NORMAL
$WARNING
echo " Are your really want to upgrade $PACKAGE ?"
$NORMAL
echo "   (Type 'yes' to upgrade or Control-C to exit)"
read YES
if [ x$YES != xyes ]; then
	echo "Please type 'yes' to real upgrade"
	exit -1
fi
(cd $TMP/$PACKAGE;tar cf - $UPGRADE|(cd ../..;tar xvf -))
rm -r $TMP
echo "$PACKAGE is successfully upgraded."
