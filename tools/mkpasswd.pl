#!/usr/bin/perl
# a simple password generator using the crypt() with a seed.
# wkpark@kldp.org
# $Id$
if ($ARGV[0]) {
   $seed= `echo $ARGV[0] |md5sum | cut -d' ' -f1`;
   chop $seed;
   if (not $seed) {
      $seed=time ^ $$;
   }
   print crypt("$ARGV[0]",$seed)."\n";
} else {
   print "Usage: perl mkpasswd.pl yourpassword\n";
}
