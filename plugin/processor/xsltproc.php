<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a vim colorizer plugin for the MoniWiki
//
// Usage: {{{#!xml
// xml codes
// }}}
// $Id$

function processor_xsltproc($formatter,$value) {
  global $DBInfo;
  $xsltproc = "xsltproc ";
  if ($value[0]=='#' and $value[1]=='!') {
    list($line,$value)=explode("\n",$value,2);
    # get parameters
    list($tag,$args)=explode(" ",$line,2);
  }

  list($line,$body)=explode("\n",$value,2);
  $value="";
  while($line[0]=='<' and $line[1]=='?') {
    preg_match("/^<\?xml-stylesheet\s+href=\"([^\"]+)\"/",$line,$match);
    if ($match) {
      if ($DBInfo->hasPage($match[1]))
        $line='<?xml-stylesheet href="'.getcwd().'/'.$DBInfo->text_dir.'/'.$match[1].'" type="text/xml"?>';
      $flag=1;
    }
    $value.=$line."\n";
    list($line,$body)=explode("\n",$body,2);
    if ($flag) break;
  }
  $src=$value.$line."\n".$body;

  $tmpf=tempnam("/tmp","FOO");
  $fp= fopen($tmpf, "w");
  fwrite($fp, $src);
  fclose($fp);

  $cmd="$xsltproc --xinclude $tmpf";

  $fp=popen($cmd,"r");
  #fwrite($fp,$src);

  while($s = fgets($fp, 1024)) $html.= $s;

  pclose($fp);
  #unlink($tmpf);

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
