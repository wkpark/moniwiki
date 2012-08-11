<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a man processor plugin for the MoniWiki
//
// $Id: man.php,v 1.6 2008/12/26 10:56:35 wkpark Exp $

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

  if (!empty($DBInfo->man_man2html) and $DBInfo->man_man2html == 'groff')
    $man2html= "groff -Thtml -mman $tmpf";
  else
    $man2html= "man2html $tmpf";
  $html='';
  $fp=popen($man2html.$formatter->NULL,'r');
  while($s = fgets($fp, 1024)) $html.= $s;

  pclose($fp);
  unlink($tmpf);

  $html=preg_replace('@^Content-type: text/html@','',$html);
  $html=preg_replace('/<\/?META[^>]*>|<\/?HTML>|<\/?HEAD>|<\/?BODY>|<TITLE>[^>]+<\/TITLE>/i','',$html);

  $html=preg_replace('/http:\/\/localhost\/cgi\-bin\/man\/man2html\?.\+/',
                '?action=man_get&man=',$html);
  $html=preg_replace('/http:\/\/localhost\/cgi\-bin\/man\/man2html/',
                '?goto=ManPage',$html);

  return $html;
}

// vim:et:ts=2:
?>
