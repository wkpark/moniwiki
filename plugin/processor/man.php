<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a man processor plugin for the MoniWiki
//
// $Id$

function processor_man($formatter,$value="") {
  global $DBInfo;

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if ($line)
    list($tag,$args)=explode(' ',$line,2);
  $vartmp_dir=&$DBInfo->vartmp_dir;

  $tmpf=tempnam($vartmp_dir,"MAN");
  $fp= fopen($tmpf, "w");
  fwrite($fp, $value);
  fclose($fp);

  $man2html= "man2html $tmpf";
  $html='';
  while($s = fgets($fp, 1024)) $html.= $s;
  $fp=popen($man2html,'r');

  pclose($fp);
  unlink($tmpf);

  $html=str_replace('Content-type: text/html','',$html);
  $html=preg_replace('/<HTML>|<\/HTML>|<HEAD>|<\/HEAD>|<BODY>|<\/BODY>|<TITLE>.*<\/TITLE>/','',$html);
  $html=preg_replace('/http:\/\/localhost\/cgi\-bin\/man\/man2html\?.\+/',
                '?action=man_get&man=',$html);
  $html=preg_replace('/http:\/\/localhost\/cgi\-bin\/man\/man2html/',
                '?goto=ManPage',$html);

  return $html;
}

// vim:et:ts=2:
?>
