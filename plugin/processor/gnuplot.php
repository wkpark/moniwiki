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

  #if(getenv("OS")=="Windows_NT") {
  #$gnuplot="wgnuplot"; # Win32
  #} else {
  #$gnuplot="gnuplot";
  $gnuplot="/usr/local/bin/gnuplot_pm3d";
  #}

  $vartmp_dir=$DBInfo->vartmp_dir;
  $cache_dir=$DBInfo->upload_dir."/GnuPlot";

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
#
# for Win32 wgnuplot.exe
#
#     $finp=tempnam($vartmp_dir,"GNUPLOT");
#     $ifp=fopen($finp,"w");
#     fwrite($ifp,$src);
#     fclose($ifp);
#
#     $cmd= "$gnuplot $finp > $flog";
#     $fp=system($cmd);
#     $log=join(file($flog),"");
#     unlink($flog);
#     unlink($finp);

#
# Unix
#
     $cmd= "$gnuplot 2> $flog";
     $fp=system($cmd);

     $log=join(file($flog),"");
     unlink($flog);
  
     if ($log)
        $log ="<pre style='background-color:black;color:gold'>$log</pre>\n";
  }
  return $log."<img src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='gnuplot' />";
}

?>
