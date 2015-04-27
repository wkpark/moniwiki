<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a linuxdoc processor plugin for the MoniWiki
//
// Usage: {{{#!linuxdoc
// linuxdoc code
// }}}
// $Id: linuxdoc.php,v 1.8 2006/07/07 12:59:57 wkpark Exp $

function processor_linuxdoc($formatter,$value) {
  global $DBInfo;
#  $langs=array('en','de','nl','fr','es','da','no','se','pt','ca','it','ro');
  $langs=array('en','de','nl','fr','es','da','no','se','pt','ca','it','ro','ko','ja');
  $toutf=array('ko'=>'UHC','ja'=>'nippon');

  $pagename=$formatter->page->name;
  $vartmp_dir=&$DBInfo->vartmp_dir;
  $cache= new Cache_text("linuxdoc");

  if (!$formatter->refresh and !$formatter->preview and $cache->exists($pagename) and $cache->mtime($pagename) > $formatter->page->mtime())
    return $cache->fetch($pagename);

  $sgml2html= "sgml2html";
  $lang=strtok($DBInfo->lang,"_");
  $lang= in_array($lang,$langs) ? $lang:'en';

  $args= "--language=$lang ".
#        "--charset=$DBInfo->charset ".
#        "--toc=2 ".
         "--split=0 ";

  if ($value[0]=='#' and $value[1]=='!') {
    list($line,$value)=explode("\n",$value,2);
    # get parameters
    list($tag,$dummy)=explode(" ",$line,2);
  }

  $converted=0;
  $tmpf=tempnam($vartmp_dir,"SGML2HTML");
  $fp= fopen($tmpf.".sgml", "w");
  if (strtoupper($DBInfo->charset) == 'UTF-8' and isset($toutf[$lang])) {
    if (function_exists('iconv') and ($new=iconv('UTF-8',$toutf[$lang],$value)))
    {
      $value=$new;
      $converted=1;
    }
  }
  fwrite($fp, $value);
  fclose($fp);

  $cmd="$sgml2html $args $tmpf".".sgml";
  $cwd=getcwd();
  chdir($vartmp_dir);
  $log='';
  $fp=popen($cmd.$formatter->NULL,'r');
  while($s = fgets($fp, 1024)) $log.= $s;
  pclose($fp);

  $tmpfh=$tmpf.'.html';
  $fp=fopen($tmpfh,'r');
  $html=fread($fp,filesize($tmpfh));
  fclose($fp);

  unlink($tmpf.".sgml");
  unlink($tmpf); // XXX
  unlink($tmpfh);
  chdir($cwd);

  if (!$html) {
    $src=str_replace("<","&lt;",$value);
    return "<pre class='code'>$src\n</pre>\n";
  }
  if ($converted) {
    $new=iconv($toutf[$lang],'UTF-8',$html);
    if ($new) $html=$new;
  }

  if (!$formatter->preview) $cache->update($pagename,$html);
  return $log.$html;
}

// vim:et:sts=2:
?>
