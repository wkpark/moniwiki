<?php
// Copyright 2003,2010 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a octave processor plugin for the MoniWiki
//
// Usage: {{{#!octave
// code..
// }}}
// $Id: octave.php,v 1.5 2010/04/19 11:26:47 wkpark Exp $

function processor_octave($formatter="",$value="") {
  global $DBInfo;

  $vartmp_dir=&$DBInfo->vartmp_dir;

  if(getenv("OS")=="Windows_NT") {
    $octave="woctave"; # Win32
  } else {
    #$octave="octave -q -H -V --no-init-file --no-line-editing -f ";
    $octave="octave -q -H -f ";
    $octave='HOME='.$vartmp_dir.' '.$octave;
  }

  $cache_dir=$DBInfo->upload_dir."/Octave";

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
  $plt=preg_replace("/\s*;\s*\n/",";\n",$plt);
  $plt=preg_replace("/\n\s*![^\n]+\n/","\n",$plt); # strip shell commands
  $plt=preg_replace("/[ ]+/"," ",$plt);
  $plt=preg_replace("/\ngset?\s+(t|o|si).*\n/", "\n",$plt);
  // strip system functions
  $plt = preg_replace("/(system|unix|dos|perl|python|popen|pclose|fork|exec|waitpid|kill|nthargout)/", "", $plt);

  #print "<pre>$plt</pre>";

  $uniq=md5($plt);

  $outpath="$cache_dir/$uniq.png";

  $src="
gset size 0.5,0.6
gset term png
gset output '$outpath'
$plt
";

  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
    umask(022);
  }

  $log = '';
  if (!empty($formatter->preview) || !empty($formatter->refresh) || !file_exists("$cache_dir/$uniq.png")) {
     $flog=tempnam($vartmp_dir,"OCTAVE");
     #
     # for Win32 woctave.exe
     #
     if(getenv("OS")=="Windows_NT") {
       $finp=tempnam($vartmp_dir,"OCTAVE");
       $ifp=fopen($finp,"w");
       fwrite($ifp,$src);
       fclose($ifp);

       $cmd= "$octave < $finp > $flog";
       $fp=system($cmd);
       $log=join(file($flog),"");
       unlink($flog);
       unlink($finp);
     } else {
       #
       # Unix
       #
       $formatter->errlog('Oct');
       $cmd= $octave;
       $fp=popen($cmd.$formatter->LOG,"w");
       if (is_resource($fp)) {
         fwrite($fp,$src);
         pclose($fp);
       }
       $log=$formatter->get_errlog();

       @unlink($vartmp_dir.'/.octave_hist');
     }

     if ($log)
        $log ="<pre class='errlog'>$log</pre>\n";
     if (filesize("$cache_dir/$uniq.png") == 0)
        unlink("$cache_dir/$uniq.png");
  }
  if (!file_exists("$cache_dir/$uniq.png")) return $log;
  return $log."<img src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='octave' />";
}

// vim:et:sts=2:
?>
