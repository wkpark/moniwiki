<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a gnuplot processor plugin for the MoniWiki
//
// Usage: {{{#!gnuplot
// plot sin(x)
// }}}
// $Id$

function processor_gnuplot($formatter="",$value="") {
  global $DBInfo;
  #$gnuplot="gnuplot";
  $gnuplot="/usr/local/bin/gnuplot_pm3d";
  $vartmp_dir="/var/tmp";
  $cache_dir="pds/GnuPlot";

  #
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  $plt=$value;

# a sample for testing
#  $plt='
#set term gif
#!  ls
#plot sin(x)
#';

  # normalize plt
  $plt="\n".$plt."\n";
  $plt=preg_replace("/\n\s*![^\n]+\n/","\n",$plt); # strip shell commands
  $plt=preg_replace("/[ ]+/"," ",$plt);
  $plt=preg_replace("/\nset?\s+(t|o|si).*\n/", "\n",$plt);
  
  #print "<pre>$plt</pre>";
  
  $uniq=md5($plt);

  $outpath="$cache_dir/$uniq.png";

  $src="
  set size 0.5,0.6
set term png
set out '$outpath'
$plt
";

  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
    umask(022);
  }

  #if (1 || $formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {
  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {

     $flog=tempnam($vartmp_dir,"GNUPLOT");

     $cmd= "$gnuplot 2>$flog";
     $fp=popen($cmd,"w");
     fwrite($fp,$src);
  
#   while($s = fgets($fp, 1024)) {
#     $log.= $s;
#   }
     pclose($fp);
     $log=join(file($flog),"");
     unlink($flog);
  
     if ($log)
        $log ="<pre style='background-color:black;color:gold'>$log</pre>\n";
  }
  return $log."<img src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='gnuplot' />";
}

?>
