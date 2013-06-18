#!/usr/bin/env php
<?php
# a simple password generator using the crypt() with a seed.
# wkpark@kldp.org

if (isset($argv[1])) {
   $seed = md5(time());

   print crypt($argv[1], $seed)."\n";
} else {
   print "Usage: php mkpasswd.php yourpassword\n";
}
