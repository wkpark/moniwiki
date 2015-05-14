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

  if (($p=strpos($match[1],','))!==false) {
    $my=explode(',',$match[1]);
    for ($i=0,$sz=count($my);$i<$sz;$i++) {
      if (strpos($my[$i],'=')) {
        list($key,$val)=explode('=',$my[$i]);
        $val = trim($val, '"\'');
        if ($key == 'width' and $val > 1) {
          $width = intval($val);
        } else if ($key == 'height' and $val > 1) {
          $height = intval($val);
        }
      } else { // multiple files
        $media[]=$my[$i];
      }
    }
  } else {
    $media[]=$match[1];
  }
  # set embeded object size
  $mywidth = !empty($width) ? min($width, $max_width) : null;
  $myheight = !empty($height) ? min($height, $max_height) : null;

  $width=!empty($width) ? min($width,$max_width):$default_width;
  $height=!empty($height) ? min($height,$max_height):$default_height;

  $url=array();
  $my_check=1;
  for ($i=0,$sz=count($media);$i<$sz;$i++) {
    if (!preg_match("/^(http|ftp|mms|rtsp):\/\//",$media[$i])) {
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

  #
  $use_flashplayer_ok=0;
  if ($DBInfo->use_jwmediaplayer) {
    $use_flashplayer_ok=1;
    for ($i=0,$sz=count($media);$i<$sz;$i++) { // check type of all files
      if (!preg_match("/(flv|mp3|mp4|swf)$/i",$media[$i])) {
        $use_flashplayer_ok=0;
        break;
      }
    }
  }

  if ($use_flashplayer_ok) {
    # set embed flash size
    if (($sz=count($media)) == 1 and preg_match("/(ogg|wav|mp3)$/i",$media[0])) {
      // only one and a sound file
      $height=20; // override the hegiht of the JW MediaPlayer
    }

    $swfobject_num = !empty($GLOBALS['swfobject_num']) ? $GLOBALS['swfobject_num']:0;
    $swfobject_script = '';
    if (!$swfobject_num) {
      $swfobject_script="<script type=\"text/javascript\" src=\"$DBInfo->url_prefix/local/js/swfobject.js\"></script>\n";
      $num=1;
    } else {
      $num=++$swfobject_num;
    }
    $GLOBALS['swfobject_num']=$num;

    if (!$DBInfo->jwmediaplayer_prefix) {
      $_swf_prefix=qualifiedUrl("$DBInfo->url_prefix/local/JWPlayers"); // FIXME
    } else{
      $_swf_prefix=$DBInfo->jwmediaplayer_prefix;
    }

    $addparam = '';
    if ($sz > 1) {
      $md5sum=md5(implode(':',$media));
      if ($DBInfo->cache_public_dir) {
        $fc=new Cache_text('jwmediaplayer', array('dir'=>$DBInfo->cache_public_dir));
        $fname = $fc->getKey($md5sum, false);
        $basename= $DBInfo->cache_public_dir.'/'.$fname;
        $urlbase=
          $DBInfo->cache_public_url ? $DBInfo->cache_public_url.'/'.$fname:
          $DBInfo->url_prefix.'/'.$basename;
        $playfile=$basename.'.xml';
      } else {
        $cache_dir= $DBInfo->upload_dir."/VisualTour";
        $cache_url= $DBInfo->upload_url ? $DBInfo->upload_url.'/VisualTour':
          $DBInfo->url_prefix.'/'.$cache_dir;
        $basename= $cache_dir.'/'.$md5sum;
        $urlbase= $cache_url.'/'.$md5sum;
        $playfile= $basename.'.xml';
      }
      $playlist=$urlbase.'.xml';

      
      $list=array();
      for ($i=0;$i<$sz;$i++) {
        if (!preg_match("/^(http|ftp):\/\//",$url[$i])) {
          $url=qualifiedUrl($url);
        }

        $ext=substr($media[$i],-3,3); // XXX

        $list[]='<title>'.$media[$i].'</title>'."\n".
                '<location>'.$url[$i].'</location>'."\n";
      }

      $tracks="<track>\n".implode("</track>\n<track>\n",$list)."</track>\n";
      // UTF-8 FIXME
      $xml=<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<playlist version="1" xmlns="http://xspf.org/ns/0/">
  <title>XSPF Playlist</title>
  <info>XSPF Playlist</info>
  <trackList>
$tracks
  </trackList>
</playlist>
XML;
      # check cache dir exists or not and make it
      if (!is_dir(dirname($playfile))) {
        $om=umask(000);
        _mkdir_p(dirname($playfile),0777);
        umask($om);
      }

      if ($formatter->refresh or !file_exists($playfile)) {
        $fp=fopen($playfile,"w");
        fwrite($fp,$xml);
        fclose($fp);
      }
      $displayheight=$height;
      $height+=$sz*40; // XXX
      $addparam="displayheight: '$displayheight'";
      $filelist=qualifiedUrl($playlist);
    } else {
      $filelist=$url[0];
    }

    $jw_script=<<<EOS
<p id="mediaplayer$num"></p>
<script type="text/javascript">
    (function() {
        var params = {
          allowfullscreen: "true"
        };

        var flashvars = {
          width: "$width",
          height: "$height",
          // image: "preview.jpg",
          $addparam
          file: "$filelist"
        };

        swfobject.embedSWF("$_swf_prefix/mediaplayer.swf","mediaplayer$num","$width","$height","0.0.9",
        "expressInstall.swf",flashvars,params);
    })();
</script>
EOS;

    return <<<EOS
      $swfobject_script$jw_script
EOS;
  } else {
    $out='';
    $mysize = '';
    if (!empty($mywidth)) $mysize.= 'width="'.$mywidth.'px" ';
    if (!empty($myheight)) $mysize.= ' height="'.$myheight.'px" ';

    for ($i=0,$sz=count($media);$i<$sz;$i++) {
      $mediainfo = 'External object';
      $classid = '';
      $objclass = '';
      $iframe = '';
      $custom = '';
      $object_prefered = false;
      // http://code.google.com/p/google-code-project-hosting-gadgets/source/browse/trunk/video/video.js
      if ($macro == 'youtube' && preg_match("@^([a-zA-Z0-9_-]+)$@", $media[$i], $m) ||
          preg_match("@https?://(?:[a-z-]+[.])?(?:youtube(?:[.][a-z-]+)+|youtu\.be)/(?:watch[?].*v=|v/|embed/)?([a-z0-9_-]+)$@i",$media[$i],$m)) {

        if ($object_prefered) {
        $movie = "http://www.youtube.com/v/".$m[1];
        $type = 'type="application/x-shockwave-flash"';
        $attr = $mysize.'allowfullscreen="true" allowScriptAccess="always"';
        $attr.= ' data="'.$movie.'?version=3'.'"';
        $url[$i] = $movie;
        $params = "<param name='movie' value='$movie?version=3'>\n".
          "<param name='allowScriptAccess' value='always'>\n".
          "<param name='allowFullScreen' value='true'>\n";
        } else {
          $iframe = 'https://www.youtube.com/embed/'.$m[1];
          $attr = 'frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen';
          if (empty($mysize))
            $attr.= ' width="500px" height="281px"';
          else
            $attr.= ' '.$mysize;
        }
        $mediainfo = 'Youtube movie';
        $objclass = ' youtube';
      } else if (preg_match("@https?://tvpot\.daum\.net\/v\/(.*)$@i", $media[$i], $m)) {
        $classid = "classid='clsid:d27cdb6e-ae6d-11cf-96b8-444553540000'";
        $movie = "http://videofarm.daum.net/controller/player/VodPlayer.swf";
        $type = 'type="application/x-shockwave-flash"';
        $attr = 'allowfullscreen="true" allowScriptAccess="always" flashvars="vid='.$m[1].'&playLoc=undefined"';
        if (empty($mysize))
          $attr.= ' width="500px" height="281px"';

        $url[$i] = $movie;
        $params = "<param name='movie' value='$movie'>\n".
          "<param name='flashvars' value='vid=".$m[1]."&playLoc=undefined'>\n".
          "<param name='allowScriptAccess' value='always'>\n".
          "<param name='allowFullScreen' value='true'>\n";
        $mediainfo = 'Daum movie';
        $objclass = ' daum';
      } else if ($macro == 'vimeo' && preg_match("@^(\d+)$@", $media[$i], $m) || preg_match("@https?://vimeo\.com\/(.*)$@i", $media[$i], $m)) {
        if ($object_prefered) {
          $movie = "https://secure-a.vimeocdn.com/p/flash/moogaloop/5.2.55/moogaloop.swf?v=1.0.0";
          $type = 'type="application/x-shockwave-flash"';
          $attr = 'allowfullscreen="true" allowScriptAccess="always" flashvars="clip_id='.$m[1].'"';
          if (empty($mysize))
            $attr.= ' width="500px" height="281px"';

          $url[$i] = $movie;
          $params = "<param name='movie' value='$movie'>\n".
            "<param name='flashvars' value='clip_id=".$m[1]."'>\n".
            "<param name='allowScriptAccess' value='always'>\n".
            "<param name='allowFullScreen' value='true'>\n";
        } else {
          $iframe = 'http://player.vimeo.com/video/'.$m[1].'?portrait=0&color=333';
          $attr = 'frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen';
          if (empty($mysize))
            $attr.= ' width="500px" height="281px"';
          else
            $attr.= ' '.$mysize;
        }
        $mediainfo = 'Vimeo movie';
        $objclass = ' vimeo';
      } else if (($macro == 'niconico' || $macro == 'nicovideo') && preg_match("@((?:sm|nm)?\d+)$@i", $media[$i], $m) ||
          preg_match("@(?:https?://(?:www|dic)\.(?:nicovideo|nicozon)\.(?:jp|net)/(?:v|watch)/)?((?:sm|nm)?\d+)$@i",
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

        $attr = 'frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen';
        $mediainfo = 'Niconico';
        $objclass = ' niconico';
      } else if (preg_match("/(wmv|mpeg4|mp4|avi|asf)$/",$media[$i], $m)) {
        $classid="classid='clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95'";
        $type='type="application/x-mplayer2"';
        $attr = $mysize.'autoplay="'.$play.'"';
        $params="<param name='FileName' value='".$url[$i]."' />\n".
          "<param name='AutoStart' value='False' />\n".
          "<param name='ShowControls' value='True' />";
        $mediainfo = strtoupper($m[1]).' movie';
      } else if (preg_match("/(wav|mp3|ogg)$/",$media[$i], $m)) {
        $classid="classid='clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B'";
        $type='';
        $attr='codebase="http://www.apple.com/qtactivex/qtplugin.cab" height="30"';
        $attr.=' autoplay="'.$play.'"';
        $params="<param name='src' value='".$url[$i]."'>\n".
          "<param name='AutoStart' value='$play' />";
        $mediainfo = strtoupper($m[1]).' sound';
      } else if (preg_match("/swf$/",$media[$i])) {
        $type='type="application/x-shockwave-flash"';
        $classid="classid='clsid:D27CDB6E-AE6D-11cf-96B8-444553540000'";
        $attr='codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0"';
        $attr.=' autoplay="'.$play.'"';
        $params="<param name='movie' value='".$url[$i]."' />\n".
          "<param name='AutoStart' value='$play' />";
      } else if (preg_match("/\.xap/",$media[$i])) {
        $type='type="application/x-silverlight-2"';
        $attr = $mysize.'data="data:application/x-silverlight,"';
        $params="<param name='source' value='".$url[$i]."' />\n";
      }
      $autoplay=0; $play='false';

      if ($iframe) {
        $out.=<<<IFRAME
<div class='externalObject$objclass'><div>
<iframe src="$iframe" $attr></iframe>
<div><a alt='$myurl' onclick='javascript:openExternal(this, "inline-block"); return false;'><span>[$mediainfo]</span></a></div></div></div>
IFRAME;
      } else if (isset($custom[0])) {
        $out.= <<<OBJECT
<div class='externalObject$objclass'><div>
$custom
<div><a alt='$myurl' onclick='javascript:openExternal(this, "inline-block"); return false;'><span>[$mediainfo]</span></a></div></div></div>
OBJECT;
      } else {
        $myurl=$url[$i];
        $out.=<<<OBJECT
<div class='externalObject$objclass'><div>
<object class='external' $classid $type $attr>
$params
<param name="AutoRewind" value="True">
<embed $type src="$myurl" $attr></embed>
</object>
<div><a alt='$myurl' onclick='javascript:openExternal(this, "inline-block"); return false;'><span>[$mediainfo]</span></a></div></div></div>
OBJECT;
      }
    }
  }

  if (empty($GLOBALS['js_macro_play'])) {
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
    $GLOBALS['js_macro_play'] = 1;
  }

  return $out;
}

// vim:et:sts=2:
?>
