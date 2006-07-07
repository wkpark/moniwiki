<?php
// Copyright 2003-2006 Kim Jeong Yeon <see2002 at new-type.com>
// All rights reserved. Distributable under GPL see COPYING
// a PIC plugin for the MoniWiki
//
// $Id$
// Usage: {{{#!pic
// some codes
// }}}

function processor_pic($formatter,$value="") {
  global $DBInfo;

  $GROFF="groff -e -p -ms -Tps ";
  $CONVERT="convert -transparent white -density 120x120 -crop 0x0 -trim ";

  $vartmp_dir=&$DBInfo->vartmp_dir;
  $cache_dir=$DBInfo->upload_dir."/PIC";

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  $pic_script=$value;

  # print "<pre>$pic_script</pre>";

  $uniq=md5($pic_script);
  $outpath_pic="$vartmp_dir/$uniq.pic";
  $outpath_ps="$vartmp_dir/$uniq.ps";
  $outpath_png="$cache_dir/$uniq.png";


  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
    umask(022);
  }

  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {
    # write to pic script file
    $ifp=fopen("$outpath_pic","w");
    fwrite($ifp,$pic_script);
    fclose($ifp);

    # convert processing
    $fp=popen("$GROFF $outpath_pic >$outpath_ps".$formatter->NULL,'r');
    pclose($fp);
    $fp=popen("$CONVERT $outpath_ps $outpath_png".$formatter->NULL,'r');
    pclose($fp);

    # delete temporary files
    unlink($outpath_ps);
    unlink($outpath_pic);
  }
  return "<img class='tex' src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='pic' />";
}

?>
