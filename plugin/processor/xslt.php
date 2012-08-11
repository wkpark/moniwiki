<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a xml processor plugin for the MoniWiki
//
// Usage: {{{#!xslt
// xml codes
// }}}
// $Id: xslt.php,v 1.3 2004/04/08 18:23:44 wkpark Exp $

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
  $buff="";
  while($line[0]=='<' and $line[1]=='?') {
    preg_match("/^<\?xml-stylesheet\s+href=\"([^\"]+)\"/",$line,$match);
    if ($match) {
      if ($DBInfo->hasPage($match[1])) {
        $xsl=getcwd().'/'.$DBInfo->text_dir.'/'.$match[1];
        $line='<?xml-stylesheet href="'.$xsl.'" type="text/xml"?>';
      }
      $flag=1;
    }
    $buff.=$line."\n";
    list($line,$body)=explode("\n",$body,2);
    if ($flag) break;
  }
  $src=$buff.$line."\n".$body;

  $arguments = array('/_xml' => $src);
  $html = xslt_process($xsltproc,'arg:/_xml',$xsl,NULL,$arguments);
#  $html = xslt_process($xsltproc,'arg:/_xml',NULL,NULL,$arguments);

  if (!$html) {
    return "<pre class='code'>\n$balue\n</pre>\n";
  }
  xslt_free($xsltproc);

  if (function_exists ("iconv"))
    $html=iconv('UTF-8',$DBInfo->charset,$html);
  return $html;
}

// vim:et:ts=2:
?>
