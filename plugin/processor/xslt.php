<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a vim colorizer plugin for the MoniWiki
//
// Usage: {{{#!xml XslPage
// xml codes
// }}}
// $Id$

function processor_xslt($formatter,$value) {
  global $DBInfo;
  if ($value[0]=='#' and $value[1]=='!') {
    list($line,$value)=explode("\n",$value,2);
    # get parameters
    $args=explode(" ",substr($lines[0],6),2);
  }

  $xsltproc = xslt_create();
#  xslt_set_encoding ($xsltproc, "UTF-8");
  $xsl=NULL;

  list($line,$body)=explode("\n",$value,2);
  $value="";
  while($line[0]=='<' and $line[1]=='?') {
    preg_match("/^<\?xml-stylesheet\s+href=\"([^\"]+)\"/",$line,$match);
    if ($match) {
      if ($DBInfo->hasPage($match[1])) {
        $xsl=getcwd().'/'.$DBInfo->text_dir.'/'.$match[1];
        $line='<?xml-stylesheet href="'.$xsl.'" type="text/xml"?>';
      }
      $flag=1;
    }
    $value.=$line."\n";
    list($line,$body)=explode("\n",$body,2);
    if ($flag) break;
  }
  $src=$value.$line."\n".$body;

  $arguments = array('/_xml' => $src);
  $html = xslt_process($xsltproc,'arg:/_xml',$xsl,NULL,$arguments);
#  $html = xslt_process($xsltproc,'arg:/_xml',NULL,NULL,$arguments);

  if (!$html) {
    return "<pre class='code'>\n$src\n</pre>\n";
  }
  xslt_free($xsltproc);

  if (function_exists ("iconv"))
    $html=iconv('UTF-8',$DBInfo->charset,$html);
  return $html;
}

// vim:et:ts=2:
?>
