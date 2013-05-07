<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a metapost processor plugin for the MoniWiki
//
// Usage: {{{#!metapost
// }}}
// $Id: metapost.php,v 1.4 2006/07/07 12:59:57 wkpark Exp $

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

  $mp=$value;

  $uniq = md5($mp);
  if (!empty($DBInfo->cache_public_dir)) {
    $fc = new Cache_text('metapost', array('dir'=>$DBInfo->cache_public_dir));
    $basename = $fc->getKey($mp);
    $png = $DBInfo->cache_public_dir.'/'.$basename.'.png';
    $ps = $DBInfo->cache_public_dir.'/'.$basename.'.ps';
    $png_url = $DBInfo->cache_public_url.'/'.$basename.'.png';
    $ps_url = $DBInfo->cache_public_url.'/'.$basename.'.ps';
  } else {
    $png = $cache_dir.'/'.$uniq.'.png';
    $ps = $cache_dir.'/'.$uniq.'.png';
    $png_url = $DBInfo->url_prefix.'/'.$png;
    $ps_url = $DBInfo->url_prefix.'/'.$ps;

    if (!file_exists($cache_dir)) {
      umask(000);
      mkdir($cache_dir,0777);
      umask(022);
    }
  }
  $vartmp_basename = $vartmp_dir.'/'.$uniq;

  $src="beginfig(1);\n$mp\nendfig;\n";

  if ($formatter->refresh || !file_exists($png)) {
     $fp= fopen($vartmp_dir."/$uniq.mp", "w");
     fwrite($fp, $src);
     fclose($fp);

     $outpath = $png;

     # Unix specific FIXME
     $dir=getcwd();
     chdir($vartmp_dir);
     $cmd= "$mpost $option $uniq >/dev/null";
     $fp=popen($cmd.$formatter->NULL,'r');
     pclose($fp);
     chdir($dir);

     $cmd= "$convert $vartmp_basename.1 $outpath";
     $fp=popen($cmd.$formatter->NULL,'r');
     pclose($fp);

     @copy("$vartmp_basename.1", $ps);
     unlink("$vartmp_basename.1");
     unlink("$vartmp_basename.mp");
     unlink("$vartmp_basename.log");
  }
  return "<a href='$ps_url'><img class='tex' border='0' src='$png_url' alt='mp'".
         "title=\"$mp\" /></a>";
}

?>
