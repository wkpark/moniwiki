<?php
// Copyright 2004-2007 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a media Play macro plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2004-08-02
// Name: Play macro
// Description: media Player Plugin
// URL: MoniWikiDev:PlayMacro
// Version: $Revision$
// License: GPL
//
// Usage: [[Play(http://blah.net/blah.mp3)]]
//
// $Id$

function macro_Play($formatter,$value) {
  global $DBInfo;
  static $autoplay=1;

  preg_match("/(^[^,]+)(\s*,\s*)?$/",$value,$match);
  if (!$match) return '[[Play()]]';

  $media=$match[1];
  if ($match[3]) {
    $attr='';
    list($x,$y)=explode(',',$match[3]);
  }
  if (!preg_match("/^(http|ftp|mms|rtsp):\/\//",$media)) {
    $fname=$formatter->macro_repl('Attachment',$media,1);
    if (!file_exists($fname)) {
      return $formatter->macro_repl('Attachment',$value);
    }
    $url=qualifiedUrl($DBInfo->url_prefix."/"._urlencode($fname));
  } else {
    $url=$media;
  }

  if ($autoplay==1) {
    $play="true";
  } else {
    $play="false";
  }

  if ($DBInfo->use_jwmediaplayer and preg_match("/(flv|mp3)$/i",$media,$ext)) {
    $swfobject_num=$GLOBALS['swfobject_num'] ? $GLOBALS['swfobject_num']:0;
    if (!$swfobject_num) {
      $swfobject_script="<script type=\"text/javascript\" src=\"$DBInfo->url_prefix/local/js/swfobject.js\"></script>\n";
      $num=1;
    } else {
      $num=++$swfobject_num;
    }
    $GLOBALS['swfobject_num']=$num;

    if (!$DBInfo->jwmediaplayer_prefix) {
      $_swf_prefix=qualifiedUrl("$DBInfo->url_prefix/local/JWPlayers");
    } else{
      $_swf_prefix=$DBInfo->jwmediaplayer_prefix;
    }

    if (!preg_match("/^(http|ftp):\/\//",$url)) {
      $url=qualifiedUrl($url);
    }

    if ($ext[1] == 'flv') {
      $jw_script=<<<EOS
    <p id="mediaplayer$num"></p>
    <script type="text/javascript">
        var _s$num = new SWFObject("$_swf_prefix/mediaplayer.swf","_mediaplayer$num","320","240","7");
        _s$num.addParam("allowfullscreen","true");
        _s$num.addVariable("file","$url");
        //_s$num.addVariable("image","preview.jpg");
        _s$num.write("mediaplayer$num");
</script>
EOS;
    } else { // mp3 only
      $jw_script=<<<EOS
    <p id="mediaplayer$num"></p>
    <script type="text/javascript">
        var _s$num = new SWFObject("$_swf_prefix/mediaplayer.swf", "_mediaplayer$num", "240", "20", "7");
        _s$num.addVariable("file","$url");
        //_s$num.addVariable("image","cover.jpg");
        _s$num.addVariable("width","240");
        _s$num.addVariable("height","20");
        _s$num.write("mediaplayer$num");
</script>
EOS;
    }

    return <<<EOS
      $swfobject_script$jw_script
EOS;
  } else {
  if (preg_match("/(wmv|mpeg4|avi|asf)$/",$media)) {
    $classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95";
    $type='type="application/x-mplayer2"';
    $attr='width="320" height="280" autoplay="'.$play.'"';
    $params="<param name='FileName' value='$url'>\n".
      "<param name='AutoStart' value='False'>\n".
      "<param name='ShowControls' value='True'>";
  } else if (preg_match("/(wav|mp3|ogg)$/",$media)) {
    $classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B";
    $attr='codebase="http://www.apple.com/qtactivex/qtplugin.cab" height="30"';
    $attr.=' autoplay="'.$play.'"';
    $params="<param name='src' value='$url'>\n".
      "<param name='AutoStart' value='$play'>";
  }
  $autoplay=0;

  return <<<OBJECT
<object classid="$classid" $type $attr>
$params
<param name="AutoRewind" value="True">
<embed $type src="$url" $attr></embed>
</object>
OBJECT;
  }
}

// vim:et:sts=2:
?>
