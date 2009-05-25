#!/bin/sh
# $Id$

CHECKSUM=
PACKAGE=moniwiki

if [ -z "$1" ]; then
	cat <<HELP
Usage: $0 $PACKAGE-<ver>.tgz
HELP
	exit 0
fi

SUCCESS="echo -en \\033[1;32m"
FAILURE="echo -en \\033[1;31m"
WARNING="echo -en \\033[1;33m"
MESSAGE="echo -en \\033[1;34m"
NORMAL="echo -en \\033[0;39m"
MAGENTA="echo -en \\033[1;35m"

NAME="MoniWiki"

$SUCCESS
echo
echo "+-------------------------------+"
echo "|    $NAME upgrade script    |"
echo "+-------------------------------+"
echo "| This script compare all files |"
echo "|  between current and new.     |"
echo "|     All different files are   |"
echo "|  backuped in the backup       |"
echo "|  directory. And so you can    |"
echo "|  restore old one by manually. |"
echo "+-------------------------------+"
echo
$WARNING
echo -n " Press "
$MAGENTA
echo -n ENTER
$WARNING
echo -n " to continue or "
$MAGENTA
echo -n Control-C
$WARNING
echo -n " to exit "
$NORMAL
read

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

#
TMP=.tmp$$
$MESSAGE
echo "*** Extract tarball ***"
$NORMAL
mkdir -p $TMP/$PACKAGE
echo tar xzf $TAR --strip-components=1 -C$TMP/$PACKAGE
tar xzf $TAR --strip-components=1 -C$TMP/$PACKAGE
$MESSAGE

echo "*** Check new upgrade.sh script ***"
DIFF=
[ -f $TMP/$PACKAGE/upgrade.sh ] && DIFF=$(diff $0 $TMP/$PACKAGE/upgrade.sh)
if [ ! -z "$DIFF" ]; then
	$FAILURE
	echo "WARN: new upgrade.sh script found ***"
	$NORMAL
	cp -f $TMP/$PACKAGE/upgrade.sh up.sh
	$WARNING
	echo " new upgrade.sh file was copied as 'up.sh'"
	echo " Please execute following command"
	echo
	$MAGENTA
	echo " sh up.sh $TAR"
	echo
	$WARNING
	echo -n "Ignore it and try to continue ? (y/N) "
	read YES
	if [ x$YES != xy ]; then
		rm -r $TMP
		$NORMAL
		exit;
	fi
fi

$MESSAGE
echo "*** Make the checksum list for the new version ***"
$NORMAL

FILELIST=$(find $TMP/$PACKAGE -type f | sort | sed "s@^$TMP/$PACKAGE/@@")

rm -f checksum-new
(cd $TMP/$PACKAGE; for x in $FILELIST; do test -f $x && md5sum $x;done >> ../../checksum-new)

if [ ! -f "$CHECKSUM" ];then
	rm -rf checksum-current
	$MESSAGE
	echo "*** Make the checksum for current version ***"
	$NORMAL
	for x in $FILELIST; do test -f $x && md5sum $x;done >> checksum-current
	CHECKSUM=checksum-current
fi

UPGRADE=`diff checksum-current checksum-new |grep '^<'|cut -d' ' -f4`
NEW=`diff checksum-current checksum-new |grep '^\(<\|>\)' | cut -d' ' -f4|sort |uniq`

if [ -z "$UPGRADE" ] && [ -z "$NEW" ] ; then
	rm -r $TMP
	$FAILURE
	echo "You have already installed the latest version"
	$NORMAL
	exit
fi
$MESSAGE
echo "*** Backup the old files ***"
$NORMAL

$WARNING
echo -n " What type of backup do you want to ? ("
$MAGENTA
echo -n B
$WARNING
echo -n "ackup(default)/"
$MAGENTA
echo -n t
$WARNING
echo -n "ar/"
$MAGENTA
echo -n p
$WARNING
echo "atch) "
$NORMAL

echo "   (Type 'B/t/p')"
read TYPE

DATE=`date +%Y%m%d-%s`
if [ x$TYPE != xt ] && [ x$TYPE != xp ] ; then
        BACKUP=backup/$DATE
else
        BACKUP=$TMP/$PACKAGE-$DATE
fi
$MESSAGE

if [ ! -z "$UPGRADE" ]; then
	echo "*** Backup the old files ***"
	$NORMAL
	mkdir -p backup
	mkdir -p $BACKUP
	tar cf - $UPGRADE|(cd $BACKUP;tar xvf -)

	if [ x$TYPE = xt ]; then
		SAVED="backup/$DATE.tar.gz"
        	(cd $TMP; tar czvf ../backup/$DATE.tar.gz $PACKAGE-$DATE)
        	$MESSAGE
        	echo "   Old files are backuped as a backup/$DATE.tar.gz"
        	$NORMAL
	elif [ x$TYPE = xp ]; then
		SAVED="backup/$PACKAGE-$DATE.diff"
        	(cd $TMP; diff -ruN $PACKAGE-$DATE $PACKAGE > ../backup/$PACKAGE-$DATE.diff )
        	$MESSAGE
        	echo "   Old files are backuped as a backup/$PACKAGE-$DATE.diff"
        	$NORMAL
	else
		SAVED="$BACKUP/ dir"
        	$MESSAGE
        	echo "   Old files are backuped to the $SAVED"
        	$NORMAL
	fi
else
	$WARNING
	echo " You don't need to backup files !"
	$NORMAL
fi

$WARNING
echo " Are your really want to upgrade $PACKAGE ?"
$NORMAL
echo -n "   (Type '"
$MAGENTA
echo -n yes
$NORMAL
echo -n "' to upgrade or type others to exit)  "
read YES
if [ x$YES != xyes ]; then
	rm -r $TMP
	echo -n "Please type '"
	$MAGENTA
	echo -n yes
	$NORMAL
	echo "' to real upgrade"
	exit -1
fi
(cd $TMP/$PACKAGE;tar cf - $NEW|(cd ../..;tar xvf -))
rm -r $TMP
$SUCCESS
echo
echo "$PACKAGE is successfully upgraded."
echo
echo "   All different files are       "
echo "       backuped in the           "
echo "       $SAVED now. :)       "
$NORMAL
