<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// Usage: [[EngDic(hello)]]
//
// $Id$

function macro_EngDic($formatter,$value) {
  if (!$value) {
     return "
     <form method='GET'>
<input type='hidden' name='action' value='EngDic' />
<input name='value' />
<input type='submit' value='Get' />
</form>";
  }
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
<a target='sound' href="http://kr.engdic.yahoo.com/sound.html?p=$value&amp;soundid=$soundid"><img
src="http://img.yahoo.co.kr/dic/sound.gif" border='0'></a>
<a href="$url$value">$value</a>
RET;

}

function do_EngDic($formatter,$options) {
  $formatter->send_header('',$options);
  $formatter->send_title('',$options);
  print macro_EngDic($formatter,$options['value']);
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}

#function do_test($formatter,$options) {
#  $formatter->send_header();
#  $formatter->send_title();
#  $ret= macro_Test($formatter,$options[value]);
#  $formatter->send_page($ret);
#  $formatter->send_footer("",$options);
#  return;
#}

// vim:et:sts=4:sw=4:
?>
