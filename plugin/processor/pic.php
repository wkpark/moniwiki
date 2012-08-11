<?php
// Copyright 2003-2010 Kim Jeong Yeon <see2002 at new-type.com>
// All rights reserved. Distributable under GPL see COPYING
// a PIC plugin for the MoniWiki
//
// $Id: pic.php,v 1.9 2010/09/07 12:11:49 wkpark Exp $
// Usage: {{{#!pic
// some codes
// }}}

function processor_pic($formatter,$value="") {
  global $DBInfo;

  $GROFF="groff -e -p -ms -Tps ";
  $CONVERT="convert -transparent white -density 120x120 -crop 0x0 -trim ";

  $vartmp_dir=&$DBInfo->vartmp_dir;
  if(getenv("OS")=="Windows_NT") {
    $NULL='NUL';
    $vartmp_dir=getenv('TEMP');
    #$convert="wconvert";
  }

  $cache_dir=$DBInfo->upload_dir."/PIC";
  $cache_url=!empty($DBInfo->upload_url) ? $DBInfo->upload_url.'/PIC':
    $DBInfo->url_prefix.'/'.$cache_dir;

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  $pic_script=$value;

  # print "<pre>$pic_script</pre>";

  $uniq=md5($pic_script);
  if ($DBInfo->cache_public_dir) {
    $fc = new Cache_text('pic',array('ext'=>'png', 'dir'=>$DBInfo->cache_public_dir));
    $pngname = $fc->getKey($uniq, false);
    $outpath_png= $DBInfo->cache_public_dir.'/'.$pngname;

    $png_url=
      $DBInfo->cache_public_url ? $DBInfo->cache_public_url.'/'.$pngname:
      $DBInfo->url_prefix.'/'.$outpath_png;
  } else {
    $outpath_png=$cache_dir.'/'.$uniq.'.png';
    $png_url=$cache_url.'/'.$uniq.'.png';

  }
  $outpath_pic="$vartmp_dir/$uniq.pic";
  $outpath_ps="$vartmp_dir/$uniq.ps";

  if (!file_exists(dirname($outpath_png))) {
    umask(000);
    _mkdir_p(dirname($outpath_png),0777);
    umask(022);
  }

  if ($formatter->refresh || !file_exists($outpath_png)) {
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
  return "<img class='tex' src='$png_url' alt='pic' />";
}

?>
