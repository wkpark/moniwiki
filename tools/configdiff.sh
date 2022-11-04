#!/bin/sh
# Extract newily added config.php options
# by wkpark at gmail.com
# Since 2022/11/04
# LGPL/GPL dual license

if [ ! -f "$1" ] || [ ! -f "$2" ]; then
  echo "Usage: sh ./config.php config.php.default"

  exit 0
fi

SUCCESS="printf \033[1;32m"
FAILURE="printf \033[1;31m"
WARNING="printf \033[1;33m"
MESSAGE="printf \033[1;34m"
NORMAL="printf \033[0;39m"

O=o.$$
A=a.$$
B=b.$$
C=c.$$
N=n.$$
H=h.$$
E=confnew.php

grep '^#*\$[^=]\+' $1 |cut -d= -f1 | sed 's/ //g' | uniq > $O
cat $O | sort > uniq > $A
grep '^#*\$[^=]\+' $2 |cut -d= -f1 | sed 's/ //g' | sort | uniq > $B

diff -u $A $B | grep '^+#*\$' | cut -c2- > $C

# check already have config
rm -f $H
for x in $(cat $A | sed 's/^#*//' | sort | uniq); do
  grep "^#*$x" $C >> $H
done

diff -u $H $C | grep '^+#*\$' | cut -c2- > $N

# extract new config params
rm -f $E
echo "<?php" > $E
for x in $(grep '^#*\$[^=]\+' $2 |cut -d= -f1 | sed 's/ //g' | sed 's/^#*//' | uniq); do
  c=$(grep "^#*$x" $N)
  ca=$(grep "^#*$x" $E)
  if [ "$c" != "" ] && [ "$ca" = "" ]; then
    grep "^#*$x" $2 >> $E
  fi
done

newconf=$(wc -l < $E)
newconf=$(expr $newconf - 1)
[ "$newconf" = 0 ] && rm $E

rm -r $O $A $B $C $N $H

if [ -f $E ]; then
  $SUCCESS
  echo
  echo "Total $newconf config options found and saved in the confnew.php"
  echo
  $NORMAL
else
  $WARNING
  echo
  echo "No new config options found."
  echo
  $NORMAL
fi
