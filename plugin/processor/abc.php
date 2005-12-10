<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a abc2midi processor plugin for the MoniWiki
//
// Usage: {{{#!abc
// blah blah
// }}}
// $Id$

function processor_abc($formatter="",$value="") {
  global $DBInfo;
  $abc2midi="abc2midi"; # Unix

  $vartmp_dir=&$DBInfo->vartmp_dir;
  $cache_dir=$DBInfo->upload_dir."/Abc2Midi";

  #
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  $abc=$value;

# a sample for testing
#  $abc='
#
#';

  # normalize abc
  #print "<pre>$abc</pre>";
  
  $uniq=md5($abc);

  $outpath="$cache_dir/$uniq.midi";

  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
    umask(022);
  }

  #if (1 || $formatter->refresh || !file_exists("$cache_dir/$uniq.midi")) {
  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.midi")) {

    $tmpf=tempnam($vartmp_dir,"FOO");
    $fp= fopen($tmpf, "w");
    fwrite($fp, $abc);
    fclose($fp);

    $flog=tempnam($vartmp_dir,"ABC2MIDI");
#
# for Win32 wabc2midi.exe
#
#     $finp=tempnam($vartmp_dir,"ABC2MIDI");
#     $ifp=fopen($finp,"w");
#     fwrite($ifp,$abc);
#     fclose($ifp);
#
#     $cmd= "$abc2midi $finp -o $cache_dir/$uniq.midi > $flog";
#     $fp=system($cmd);
#     $log=join(file($flog),"");
#     unlink($flog);
#     unlink($finp);

#
# Unix
#
     $cmd= "$abc2midi $tmpf -o $cache_dir/$uniq.midi 2> $flog";
     $fp=popen($cmd,'w');
     pclose($fp);

     $log=join(file($flog),"");
     unlink($flog);
     unlink($tmpf);
  
     if ($log)
        $log ="<pre style='background-color:black;color:gold'>$log</pre>\n";
  }
  return $log."<a href='$DBInfo->url_prefix/$cache_dir/$uniq.midi' alt='abc2midi'>abc2midi</a>";
}

?>
