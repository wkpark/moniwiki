#!/bin/bash
# $Id$
#
# sh thumbnails.sh pds/Gallery

DIR=$1

#SIZE=$2

IMGS=`find $1 -maxdepth 1`

echo -e "*** Make thumbnails ***"
if [ ! -d $1/thumbnails ]; then
  mkdir $1/thumbnails
fi
for X in $IMGS; do
  name=`basename $X`; dir=`dirname $X`;
  isimg=`file $X | grep -i 'image'`; [ "x$isimg" = "x" ] && continue;
  if [ ! -f $dir/thumbnails/$name ]; then
    echo -n " *** "
    echo "convert -scale 300x200 $X $dir/thumbnails/$name"
    convert -scale 300x200 $X $dir/thumbnails/$name
  fi
done

echo -e "*** Done ***"
