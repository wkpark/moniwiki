#!/bin/sh

SUCCESS="printf \033[1;32m"
FAILURE="printf \033[1;31m"
WARNING="printf \033[1;33m"
MESSAGE="printf \033[1;34m"
NORMAL="printf \033[0;39m"
MAGENTA="printf \033[1;35m"


$SUCCESS
echo
echo "+-------------------------------------+"
echo "|    MoniWiki configuration script    |"
echo "+-------------------------------------+"
echo
$NORMAL

RETVAL=1
while [ ! $RETVAL -eq 0 ]; do
  $WARNING
  echo -n " Please enter the permission "
  $MAGENTA
  echo -n 777
  $WARNING
  echo -n " or "
  $MAGENTA
  echo -n 2777
  $WARNING
  echo -n "(default 2777): "
  read PERM
  if [ x$PERM = x ]; then
    PERM=2777
  fi

  if [ ! -f config.php ]; then
    echo "*** chmod $PERM . data"
    chmod $PERM . data
    RETVAL=$?
  else
    RETVAL=0
  fi
done
$NORMAL

if [ ! -f config.php ]; then
  echo 'Please open monisetup.php on your browser'
  exit;
else
  echo "*** chmod 777 config.php"
  chmod 777 config.php 2>/dev/null
  RETVAL=$?
  [ ! $RETVAL -eq 0 ] && cp config.php config.php.$$ && mv config.php.$$ config.php && chmod 777 config.php

  DATA_DIR=`cat config.php |grep '$data_dir='|cut -d\' -f2`

  echo "*** chmod $PERM . $DATA_DIR"
  chmod $PERM . $DATA_DIR

  ID=`id -u`
  if [ $ID -eq 0 ]; then
    echo "*** You are the root user ***"
    PERM=755
  else
    $WARNING
    echo -n " Did you really want to make directories with permission '$PERM'(N/y): "
    $NORMAL
    read say
    if [ x$say = x ]; then
      say='n'
    fi
    if [ x$say = x'n' ]; then
      $WARNING
      echo ""
      echo "Please open monisetup.php again."
      echo ""
      $NORMAL
      exit
    fi
  fi  
fi


if [ ! -d $DATA_DIR/text ]; then
  echo " *** mkdir $DATA_DIR/{text,text/RCS,user,cache}"
  for x in text text/RCS user cache; do
    mkdir $DATA_DIR/$x
  done
fi

if [ ! -d pds ]; then
  echo "*** mkdir pds"
  mkdir pds
fi

echo "*** chmod $PERM $DATA_DIR/{text,text/RCS,user,cache}"
for x in text text/RCS user cache; do
  chmod $PERM $DATA_DIR/$x
done
chmod $PERM pds
RETVAL=$?
if [ ! $RETVAL -eq 0 ]; then
  $FAILURE
  echo ""
  echo "---------------------------------------------------------"
  echo "You can not change some directories permission with $PERM"
  echo "since you make it with the monisetup with sgid enabled"
  echo "simply ignore above error messages :)"
  echo "---------------------------------------------------------"
  echo ""
  $NORMAL
fi

echo "*** chmod $PERM config.php"
chmod $PERM config.php 2>/dev/null
RETVAL=$?
[ ! $RETVAL -eq 0 ] && cp config.php config.php.$$ && mv config.php.$$ config.php
chmod $PERM config.php

if [ $ID -eq 0 ]; then
  RETVAL=1
  while [ ! $RETVAL -eq 0 ]; do
    echo -n " Please enter the Apache user ID (e.g. nobody): "
    read owner
    if [ x$owner = x ]; then
      owner=nobody
    fi
    for x in text text/RCS cache user; do
      chown $owner $DATA_DIR/$x
    done
    chown $owner $DATA_DIR pds
    RETVAL=$?
  done

  RETVAL=1

  while [ ! $RETVAL -eq 0 ]; do
    echo -n " Please enter the Apache group ID (e.g. nobody): "
    read group
    if [ x$group = x ]; then
      group=nobody
    fi
    for x in text text/RCS cache user; do
      chgrp $group $DATA_DIR/$x
    done
    chgrp $group $DATA_DIR pds
    RETVAL=$?
  done
fi

$SUCCESS
echo
echo 'Your wiki is configured now.'
echo 'Please open monisetup.php in a browser'
echo ' to change some basic options for your wiki.'
echo
echo
$NORMAL
