<?php
// Copyright 2003,2004 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple Play macro plugin for the MoniWiki
//
// Usage: [[Play(http://blah.net/blah.mp3)]]
//
// $Id$

function macro_Play($formatter,$value) {
  global $DBInfo;

  preg_match("/(^[^,]+)(\s*,\s*)?$/",$value,$match);
  if (!$match) return '[[Play()]]';

  $media=$match[1];
  if ($match[3]) {
    $attr='';
    list($x,$y)=explode(',',$match[3]);
  }
  $fname=$formatter->macro_repl('Attachment',$value,1);
  if (!file_exists($fname)) {
    return $formatter->macro_repl('Attachment',$value);
  }

  $url=qualifiedUrl($DBInfo->url_prefix."/"._urlencode($fname));

  if (preg_match("/(wmv|mpeg4|avi|asf)$/",$media)) {
    $classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95";
    $type='type="application/x-mplayer2"';
    $attr='width="320" height="280"';
    $params="<param name='FileName' value='$url'>\n".
      "<param name='AutoStart' value='False'>\n".
      "<param name='ShowControls' value='True'>";
  } else if (preg_match("/(wav|mp3|ogg)$/",$media)) {
    $classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B";
    $attr='codebase="http://www.apple.com/qtactivex/qtplugin.cab" height="30"';
    $params="<param name='src' value='$url'>\n".
      "<param name='AutoStart' value='True'>";
  }

  return <<<OBJECT
<object classid="$classid" $type $attr>
$params
<param name="AutoRewind" value="True">
<embed $type src="$url" $attr></embed>
</object>
OBJECT;
}

// vim:et:sts=2:
?>
