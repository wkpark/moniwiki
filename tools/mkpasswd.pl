#!/usr/bin/perl
# a simple password generator using the crypt() with a seed.
# wkpark@kldp.org
# $Id$
if ($ARGV[0]) {
   $seed=localtime;
   if ( -x "/usr/bin/md5sum" ) {
     $seed= `echo $seed$ARGV[0] |md5sum | cut -d' ' -f1`;
   } elsif ( -x "/usr/bin/md5" ) {
     $seed= `echo $seed$ARGV[0] |md5 | cut -d' ' -f1`;
   } else {
     $seed.=$ARGV[0];
   }
   chop $seed;
   if (not $seed) {
      $seed=time ^ $$;
   }

   print crypt("$ARGV[0]",$seed)."\n";
} else {
   print "Usage: perl mkpasswd.pl yourpassword\n";
}
