<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// Usage: [[EngDic(hello)]]
//
// $Id: EngDic.php,v 1.3 2010/04/26 07:20:01 wkpark Exp $

function macro_EngDic($formatter,$value) {
  if (!$value) {
     return "
     <form method='GET'>
<input type='hidden' name='action' value='EngDic' />
<input name='value' />
<input type='submit' value='Get' />
</form>";
  }
  $url='http://kr.dictionary.search.yahoo.com/search/dictionaryp?p=';

  if (empty($value)) $value='hello';
  $fp=fopen($url.$value,"r");
  if (!is_resource($fp))
    return '';
  while(!feof($fp)) {
    $buf=fgets($fp,1024);
    @preg_match("/mp3Src=(http:\/\/.*\.mp3)/",$buf,$match);
    if (isset($match[1])) {
      $soundid=$match[1];
      fclose($fp);
      break;
    }
  }
  return <<<RET
<a target='sound' href="$soundid"><img
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

// vim:et:sts=4:sw=4:
?>
