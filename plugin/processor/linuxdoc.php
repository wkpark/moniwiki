<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a linuxdoc processor plugin for the MoniWiki
//
// Usage: {{{#!linuxdoc
// linuxdoc code
// }}}
// $Id$

function processor_linuxdoc($formatter,$value) {
  global $DBInfo;
  $langs=array('en','de','nl','fr','es','da','no','se','pt','ca','it','ro');

  $pagename=$formatter->page->name;
  $cache= new Cache_text("linuxdoc");

  if (!$formatter->preview and $cache->exists($pagename) and $cache->mtime($pagename) > $formatter->page->mtime())
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
    list($tag,$args)=explode(" ",$line,2);
  }

  $tmpf=tempnam("/tmp","SGML2HTML");
  $fp= fopen($tmpf.".sgml", "w");
  fwrite($fp, $value);
  fclose($fp);

  $cmd="cd /tmp;$sgml2html $args $tmpf".".sgml";

  exec($cmd,$log);

  $log=join("",$log);
  $fp=fopen($tmpf.".html",'r');
  $html=fread($fp,filesize($tmpf.".html"));
  fclose($fp);

  unlink($tmpf.".sgml");
  unlink($tmpf.".html");

  if (!$html) {
    $src=str_replace("<","&lt;",$value);
    return "<pre class='code'>$src\n</pre>\n";
  }

  if (!$formatter->preview)
    $cache->update($pagename,$html);
  return $log.$html;
}

// vim:et:ts=2:
?>
