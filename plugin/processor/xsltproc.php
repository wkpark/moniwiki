<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a vim colorizer plugin for the MoniWiki
//
// Usage: {{{#!xml XslPage
// xml codes
// }}}
// $Id$

function processor_xsltproc($formatter,$value) {
  global $DBInfo;
  $xsltproc = "xsltproc ";
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  # get parameters
  list($tag,$args)=explode(" ",$line,2);
  $src=$value;

  $xsl = "include/terms.xsl";

  $tmpf=tempnam("/tmp","FOO");
  $fp= fopen($tmpf, "w");
  fwrite($fp, $src);
  fclose($fp);

  $cmd="$xsltproc $xsl $tmpf";

  $fp=popen($cmd,"r");
  #fwrite($fp,$src);

  while($s = fgets($fp, 1024)) $html.= $s;

  pclose($fp);
  unlink($tmpf);

  if (!$html) {
    $src=str_replace("<","&lt;",$src);
    return "<pre class='code'>$src\n</pre>\n";
  }

  if (function_exists ("iconv"))
    $html=iconv('UTF-8',$DBInfo->charset,$html);
  return $html;
}

// vim:et:ts=2:
?>
