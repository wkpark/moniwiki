<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a metapost processor plugin for the MoniWiki
//
// Usage: {{{#!metapost
// }}}
// $Id$

function processor_metapost($formatter,$value="") {
  global $DBInfo;

  # site spesific variables
  $mpost="mpost";
  $dvips="dvips";
  $convert="convert -transparent white -crop 0x0 -density 120x120";
  $vartmp_dir=&$DBInfo->vartmp_dir;
  $cache_dir=$DBInfo->upload_dir."/MetaPost";
  $option='-T -interaction=batchmode ';

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if (!$value) return;

  if (preg_match('/beginfig\(/',$value)) {
    $out='<font color=red>Don\'t use beginfig()!</font>';
    $out.="<pre>\n";
    $out.=$value;
    $out.="</pre>\n";

    return $out;
  }

  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
    umask(022);
  }

  $mp=$value;

  $uniq=md5($mp);

  $src="beginfig(1);\n$mp\nendfig;\n";

  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {
     $fp= fopen($vartmp_dir."/$uniq.mp", "w");
     fwrite($fp, $src);
     fclose($fp);

     $outpath="$cache_dir/$uniq.png";

     # Unix specific FIXME
     $dir=getcwd();
     chdir($vartmp_dir);
     $cmd= "$mpost $option $uniq >/dev/null";
     $fp=popen($cmd.$formatter->NULL,'r');
     pclose($fp);
     chdir($dir);

     $cmd= "$convert $vartmp_dir/$uniq.1 $outpath";
     $fp=popen($cmd.$formatter->NULL,'r');
     pclose($fp);

     @copy("$vartmp_dir/$uniq.1","$cache_dir/$uniq.ps");
     unlink("$vartmp_dir/$uniq.1");
     unlink("$vartmp_dir/$uniq.mp");
     unlink("$vartmp_dir/$uniq.log");
  }
  return "<a href='$DBInfo->url_prefix/$cache_dir/$uniq.ps'><img class='tex' border='0' src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='mp'".
         "title=\"$mp\" /></a>";
}

?>
