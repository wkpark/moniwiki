<?php
// Copyright 2004-2010 by Won-Kyu Park <wkpark at kldp.org>
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
  $max_width=600;
  $max_height=400;

  $default_width=320;
  $default_height=240;

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
        if ($key == 'width' and $val > 1) {
          $width=$val;
        } else if ($key == 'height' and $val > 1) {
          $height=$val;
        }
      } else { // multiple files
        $media[]=$my[$i];
      }
    }
  } else {
    $media[]=$match[1];
  }
  # set embeded object size
  $width=!empty($width) ? min($width,$max_width):$default_width;
  $height=!empty($height) ? min($height,$max_height):$default_height;

  $url=array();
  $my_check=1;
  for ($i=0,$sz=count($media);$i<$sz;$i++) {
    if (!preg_match("/^(http|ftp|mms|rtsp):\/\//",$media[$i])) {
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

    for ($i=0,$sz=count($media);$i<$sz;$i++) {
      $classid = '';
      if (preg_match("/(wmv|mpeg4|mp4|avi|asf)$/",$media[$i])) {
        $classid="classid='clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95'";
        $type='type="application/x-mplayer2"';
        $attr='width="320" height="280" autoplay="'.$play.'"';
        $params="<param name='FileName' value='".$url[$i]."' />\n".
          "<param name='AutoStart' value='False' />\n".
          "<param name='ShowControls' value='True' />";
      } else if (preg_match("/(wav|mp3|ogg)$/",$media[$i])) {
        $classid="classid='clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B'";
        $type='';
        $attr='codebase="http://www.apple.com/qtactivex/qtplugin.cab" height="30"';
        $attr.=' autoplay="'.$play.'"';
        $params="<param name='src' value='".$url[$i]."'>\n".
          "<param name='AutoStart' value='$play' />";
      } else if (preg_match("/swf$/",$media[$i])) {
        $type='type="application/x-shockwave-flash"';
        $classid="classid='clsid:D27CDB6E-AE6D-11cf-96B8-444553540000'";
        $attr='codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0"';
        $attr.=' autoplay="'.$play.'"';
        $params="<param name='movie' value='".$url[$i]."' />\n".
          "<param name='AutoStart' value='$play' />";
      } else if (preg_match("/\.xap/",$media[$i])) {
        $type='type="application/x-silverlight-2"';
        $attr='width="320" height="320" data="data:application/x-silverlight,"';
        $params="<param name='source' value='".$url[$i]."' />\n";
      }
      $autoplay=0; $play='false';

      $myurl=$url[$i];
      $out.=<<<OBJECT
<object $classid $type $attr>
$params
<param name="AutoRewind" value="True">
<embed $type src="$myurl" $attr></embed>
</object>
OBJECT;
    }
  }

  return $out;
}

// vim:et:sts=2:
?>
