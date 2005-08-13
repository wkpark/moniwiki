<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a gnuplot processor plugin for the MoniWiki
//
// Usage: {{{#!gnuplot
// plot sin(x)
// }}}
// $Id$

function processor_gnuplot($formatter="",$value="") {
  global $DBInfo;

  if(getenv("OS")=="Windows_NT")
    $gnuplot="wgnuplot"; # Win32
  else
    $gnuplot="gnuplot";

  $vartmp_dir=$DBInfo->vartmp_dir;
  $cache_dir=$DBInfo->upload_dir."/GnuPlot";

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  list($dum,$szarg)=explode(' ',$line);
  if ($szarg) {
    $args= explode('x',$szarg,2);
    $xsize=intval($args[0]);$ysize=intval($args[1]);
    $value='#'.$line."\n".$value;
  }

  $default_size="set size 0.5,0.6";

  $body=$plt=$value;
  while ($body and $body[0] == '#') {
    # extract first line
    list($line, $body) = explode("\n",$body, 2);

    # skip comments (lines with two hash marks)
    if ($line[1] == '#') continue;

    # parse the PI
    list($verb, $arg) = explode(' ',$line,2);
    $verb = strtolower($verb);
    $arg = rtrim($arg);

    if (in_array($verb,array('#size'))) {
      $args= explode('x',$arg,2);
      $xsize=intval($args[0]);$ysize=intval($args[1]);
    }
  }
  if ($xsize != '') {
    if ($xsize > 640 or $xsize < 100) $xscale=0.5;
    if ($xscale and ($ysize > 480 or $ysize < 100)) $yscale=0.6;
    $xscale=$xsize/640.0;
    
    if (empty($yscale)) $yscale=$xscale/0.5*0.6;

    $size='set size '.$xscale.','.$yscale;
  } else $size=$default_size;

# a sample for testing
#  $plt='
#set term gif
#!  ls
#plot sin(x)
#';
  # normalize plt
  $plt=str_replace("\r\n","\n",$plt); 
  $plt="\n".$plt."\n";
  $plt=preg_replace("/\n\s*![^\n]+\n/","\n",$plt); # strip shell commands
  $plt=preg_replace("/[ ]+/"," ",$plt);
  $plt=preg_replace("/\nset?\s+(t|o|si).*\n/", "\n",$plt);
  #
  $plt=preg_replace("/\n\s*(s?plot)\s+('|\")<(\s*)/", "\n\\1 \\2\\3",$plt);
  
  #print "<pre>$plt</pre>";
  
  $plt="\n".$size."\n".$plt;
  $uniq=md5($plt);

  $outpath="$cache_dir/$uniq.png";

  $src="
set term png
set out '$outpath'
$plt
";

  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
    umask(022);
  }

  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {

     $flog=tempnam($vartmp_dir,"GNUPLOT");
     #
     # for Win32 wgnuplot.exe
     #
     if(getenv("OS")=="Windows_NT") {
       $finp=tempnam($vartmp_dir,"GNUPLOT");
       $ifp=fopen($finp,"w");
       fwrite($ifp,$src);
       fclose($ifp);

       $cmd= "$gnuplot $finp > $flog";
       $fp=system($cmd);
       $log=join(file($flog),"");
       if (file_exists($outpath)) {
         unlink($flog);
         unlink($finp);
       } else {
         print "<font color='red'>ERROR:</font> Gnuplot does not work correctly";
       }
     } else {
       #
       # Unix
       #
       $cmd= "$gnuplot 2> $flog";
       $fp=popen($cmd,"w");
       fwrite($fp,$src);
       pclose($fp);
       $log=join(file($flog),"");
       if (file_exists($outpath)) {
         unlink($flog);
       } else {
         print "<font color='red'>ERROR:</font> Gnuplot does not work correctly";
       }
     }

     if ($log)
        $log ="<pre style='background-color:black;color:gold'>$log</pre>\n";
  }
  return $log."<img src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='gnuplot' />";
}

?>
