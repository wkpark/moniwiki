<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// Usage: [[EngDic(hello)]]
//
// $Id$
// vim:et:ts=2:

function macro_EngDic($formatter,$value) {
  $url='http://kr.engdic.yahoo.com/search/engdic?p=';

  $fp=fopen($url.$value,"r");
  while(!feof($fp)) {
    $buf=fgets($fp,1024);
    @preg_match("/javascript:ListenSound\('$value','([^']+)'\)/",$buf,$match);
    if ($match[1]) {
      $soundid=$match[1];
      fclose($fp);
      break;
    }
  }
  if (!$value) $value='wiki';
  return <<<RET
<a href="http://kr.engdic.yahoo.com/sound.html?p=$value&amp;soundid=$soundid"><img
src="http://img.yahoo.co.kr/dic/sound.gif" border='0'></a>
<a href="$url$value">$value</a>
RET;

}

#function do_test($formatter,$options) {
#  $formatter->send_header();
#  $formatter->send_title();
#  $ret= macro_Test($formatter,$options[value]);
#  $formatter->send_page($ret);
#  $formatter->send_footer("",$options);
#  return;
#}

?>
