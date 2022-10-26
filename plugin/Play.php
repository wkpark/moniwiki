<?php
// Copyright 2004-2015 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a media Play macro plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2004-08-02
// Name: Play macro
// Description: media Player Plugin
// URL: MoniWikiDev:PlayMacro
// Version: $Revision: 1.15 $
// License: GPL
//
// Usage: [[Play(http://blah.net/blah.mp3)]]
//
// $Id: Play.php,v 1.12 2010/09/07 12:11:49 wkpark Exp $

function macro_Play($formatter, $value, $params = array()) {
  global $DBInfo;
  static $autoplay=1;
  $max_width=600;
  $max_height=400;

  $default_width=320;
  $default_height=240;

  // media_url_mode for mdict etc.
  if (!empty($DBInfo->media_url_mode))
    $text_mode = 1;

  // get the macro alias name
  $macro = 'play';
  if (!empty($params['macro_name']) and $params['macro_name'] != 'play')
    $macro = $params['macro_name'];
  // use alias macro name as [[Youtube()]], [[Vimeo()]]
  #
  $media=array();
  #
  preg_match("/^(([^,]+\s*,?\s*)+)$/",$value,$match);
  if (!$match) return '[[Play(error!! '.$value.')]]';

  $align = '';
  // parse arguments height, width, align
  if (($p=strpos($match[1],','))!==false) {
    $my=explode(',',$match[1]);
    $my = array_map('trim', $my);
    for ($i=0,$sz=count($my);$i<$sz;$i++) {
      if (strpos($my[$i],'=')) {
        list($key,$val)=explode('=',$my[$i], 2);
        $val = trim($val, '"\'');
        $val = trim($val);
        if ($key == 'width' and $val > 1) {
          $width = intval($val);
        } else if ($key == 'height' and $val > 1) {
          $height = intval($val);
        } else if ($key == 'align') {
          if (in_array($val, array('left', 'center', 'right'))) {
            $align = ' obj'.ucfirst($val);
          }
        } else {
          $media[] = $my[$i];
        }
      } else { // multiple files
        $media[]=$my[$i];
      }
    }
  } else {
    $media[] = trim($match[1]);
  }
  # set embeded object size
  $mywidth = !empty($width) ? min($width, $max_width) : null;
  $myheight = !empty($height) ? min($height, $max_height) : null;

  $width=!empty($width) ? min($width,$max_width):$default_width;
  $height=!empty($height) ? min($height,$max_height):$default_height;

  $url=array();
  $my_check=1;
  for ($i=0,$sz=count($media);$i<$sz;$i++) {
    if (!preg_match("/^((https?|ftp|mms|rtsp):)?\/\//",$media[$i])) {
      if ($macro != 'play') {
        // will be parsed later
        $url[] = $media[$i];
        continue;
      }

      $fname=$formatter->macro_repl('Attachment',$media[$i],array('link'=>1));
      if ($my_check and !file_exists($fname)) {
        return $formatter->macro_repl('Attachment',$value);
      }
      $my_check=1; // check only first file.
      $fname=str_replace($DBInfo->upload_dir, $DBInfo->upload_dir_url,$fname);
      $url[]=qualifiedUrl(_urlencode($fname));
    } else {
      $url[]=$media[$i];
    }
  }

  if ($autoplay==1) {
    $play="true";
  } else {
    $play="false";
  }

  $media_id = !empty($GLOBALS['.mediaobject']) ? $GLOBALS['.mediaobject'] : 0;
  $media_id++;
  $GLOBALS['.mediaobject'] = $media_id;
  #
    $out='';
    $mysize = '';
    if (!empty($mywidth)) $mysize.= 'width="'.$mywidth.'px" ';
    if (!empty($myheight)) $mysize.= ' height="'.$myheight.'px" ';

    for ($i=0,$sz=count($media);$i<$sz;$i++) {
      $mediainfo = 'External object';
      $classid = '';
      $objclass = '';
      $iframe = '';
      $mediaurl = '';
      $custom = '';
      // http://code.google.com/p/google-code-project-hosting-gadgets/source/browse/trunk/video/video.js
      if ($macro == 'youtube' && preg_match("@^([a-zA-Z0-9_-]+)(?:\?.*)?$@", $media[$i], $m) ||
          preg_match("@(?:https?:)?//(?:[a-z-]+[.])?(?:youtube(?:[.][a-z-]+)+|youtu\.be)/(?:watch[?].*v=|v/|embed/)?([a-z0-9_-]+)$@i",$media[$i],$m)) {

        $iframe = '//www.youtube.com/embed/'.$m[1];
        $mediaurl = 'https:'.$iframe;
        $attr = 'frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen';
        if (empty($mysize))
          $attr.= ' width="500px" height="281px"';
        else
          $attr.= ' '.$mysize;

        $mediainfo = 'Youtube movie';
        $objclass = ' youtube';
      } else if (preg_match('@^https?://(?:tv)\.kakao\.com/(?:.*?(?:clipid=|vid=|v/))?(\d+)@i', $media[$i], $m)) {
        // like as https://tv.kakao.com/v/432929738
        $mediaurl = 'https://tv.kakao.com/v/'.$m[1];

        $iframe = '//tv.kakao.com/embed/player/cliplink/'.$m[1].
                  '?section=channel&amp;autoplay=1&amp;profile=HIGH&amp;wmode=transparent';
        $attr = 'frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen style="border: 0px;"';
        if (empty($mysize))
          $attr.= ' width="500px" height="281px"';
        else
          $attr.= ' '.$mysize;

        $mediainfo = 'Kakao TV';
        $objclass = ' kakao';
      } else if ($macro == 'vimeo' && preg_match("@^(\d+)$@", $media[$i], $m) || preg_match("@(?:https?:)?//(?:player\.)?vimeo\.com\/(?:video/)?(.*)$@i", $media[$i], $m)) {
        $mediaurl = 'https://player.vimeo.com/v/'.$m[1];
          $iframe = '//player.vimeo.com/video/'.$m[1].'?portrait=0&color=333';
          $attr = 'frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen';
          if (empty($mysize))
            $attr.= ' width="500px" height="281px"';
          else
            $attr.= ' '.$mysize;
        $mediainfo = 'Vimeo movie';
        $objclass = ' vimeo';
      } else if (($macro == 'niconico' || $macro == 'nicovideo') && preg_match("@((?:sm|nm)?\d+)$@i", $media[$i], $m) ||
          preg_match("@(?:https?://(?:(?:www|dic)\.)?(?:nicovideo|nicozon|nico)\.(?:jp|net|ms)/(?:(?:v|watch)/)?)((?:sm|nm)?\d+)$@i",
          $media[$i], $m)) {

        $custom = '<script type="text/javascript" src="http://ext.nicovideo.jp/thumb_watch/'.$m[1];
        $size = '';
        $qprefix = '?';
        if ($mywidth > 0) {
          $size.= '?w='.intval($mywidth);
          $qprefix = '&amp;';
        }
        if ($myheight > 0)
          $size.= $qprefix.'h='.intval($myheight);
        $custom.= $size;
        $custom.= '"></script>';

        $mediaurl = 'https://nico.ms/'.$m[1];

        $attr = 'frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen';
        $mediainfo = 'Niconico';
        $objclass = ' niconico';
      } else if (preg_match("/(mp4|webm)$/",$media[$i], $m)) {
        $classid = '';
        $mediatype = 'audio';
        $mimetypes = array("mp4"=>"video/mpeg", "webm"=>"video/webm");
        $type = 'type="'.$mimetypes[$m[1]].'"';
        $attr = $mysize.'autoplay="'.$play.'" style="display:inherit"';
        $mediainfo = strtoupper($m[1]).' movie';
      } else if (preg_match("/(wav|mp3|ogg|flac)$/",$media[$i], $m)) {
        $classid = '';
        $mediatype = 'audio';
        $mimetypes = array("wav"=>"application/x-wav", "mp3"=>"audio/mpeg", "ogg"=>"audio/ogg", "flac"=>"audio/flac");
        $type = 'type="'.$mimetypes[$m[1]].'"';
        $attr = ' autoplay="'.$play.'" controls';
        $mediainfo = strtoupper($m[1]).' sound';
      }
      $autoplay=0; $play='false';

      if ($text_mode) {
        $out.= '<a href="'.$mediaurl.'">'.$mediaurl.'</a>';
      } else
      if ($iframe) {
        $out.=<<<IFRAME
<div class='externalObject$objclass$align'><div>
<iframe class='external' src="$iframe" $attr></iframe>
<div><a href='$mediaurl' id="medialink-$media_id" onclick='javascript:openExternal(this, "inline-block"); return false;'><span>[$mediainfo]</span></a></div></div></div>
IFRAME;
      } else if (isset($custom[0])) {
        $out.= <<<OBJECT
<div class='externalObject$objclass'><div>
$custom
<div><a href='$mediaurl' id="medialink-$media_id" onclick='javascript:openExternal(this, "inline-block"); return false;'><span>[$mediainfo]</span></a></div></div></div>
OBJECT;
      } else {
        $myurl=$url[$i];
        $out.=<<<HTML5
<div class='externalObject$objclass'><div>
<$mediatype class='external' $attr>
<source $type src="$myurl" />
Download <a href="$myurl">[$mediainfo]</a>
</$mediatype>
<div><a href='$myurl' id="medialink-$media_id"><span>[$mediainfo]</span></a></div></div></div>
HTML5;
      }
    }

  $js = <<<JS
<script type='text/javascript'>
/*<![CDATA[*/
function openExternal(obj, display) {
  var el;
  (el = obj.parentNode.parentNode.firstElementChild) && (el.style.display = display);
}
/*]]>*/
</script>
JS;
  $formatter->register_javascripts($js);

  return $out;
}

// vim:et:sts=2:
