<?php
// Copyright 2003-2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// Usage: [[OeKaki(hello)]]
//
// $Id: OeKaki.php,v 1.8 2008/03/29 06:36:21 wkpark Exp $
// vim:et:ts=2:

function macro_OeKaki($formatter,$value) {
  global $DBInfo;
  $oekaki_dir=$DBInfo->upload_dir.'/OeKaki';

  $size="";
  preg_match("/^(\d{3})x(\d{3}),?\s*(.+)$/",$value,$match);
  if ($match) {
    $sizex=$match[1]; $sizey=$match[2];
    $value=$match[3];
    if ($sizex < 100 or $sizex > 600) $sizex=300;
    if ($sizey < 100 or $sizey > 600) $sizey=300;
    $size="&amp;size=".$sizex."x".$sizey;
  }

  $name=_rawurlencode($value);

  umask(000);
  if (!file_exists($oekaki_dir))
    mkdir($oekaki_dir, 0777);

  $pngname='OeKaki_'.$name.".png";
  $now=time();

  $url=$formatter->link_url(_rawurlencode($formatter->page->name),"?action=OeKaki&amp;value=$name$size&amp;now=$now");

  if (!file_exists($oekaki_dir."/$pngname"))
    return "<a href='$url'>"._("Draw a new picture")."</a>";

  return "<a href='$url'><img src='$DBInfo->url_prefix/$oekaki_dir/$pngname' alt='oekaki'></a>";
}

function do_OeKaki($formatter,$options) {
  global $DBInfo;

  $oekaki_dir=$DBInfo->upload_dir.'/OeKaki';
  $pagename=$options['page'];

  $name=$options['value'];
#  $fp=fopen('php://stderr','w');
#  fputs($fp,"name=$name\n");
#  fputs($fp,"page=$options[page]\n");
#  if ($_SERVER['REQUEST_METHOD']=='POST') {
#    $len = $_SERVER['CONTENT_LENGTH'];
#    fputs($fp,"len=$len\n");
#    fputs($fp,"method=POST\n");
#  }
  if (!$name) $name=time(); # XXX

  $pngname='OeKaki_'._rawurlencode($name);

  $imgurl="$DBInfo->url_prefix/$oekaki_dir/$pngname.png";
  $imgpath="$oekaki_dir/$pngname.png";

  $dummy=0;

  $backup_imgpath=$imgpath;
  while (file_exists($backup_imgpath)) {
     $dummy=$dummy+1;
     $ufname=$pngname."_".$dummy; // rename file
     $upfilename=$ufname.".png";
     $backup_imgpath= "$oekaki_dir/$upfilename";
  }

  if ($GLOBALS['HTTP_RAW_POST_DATA']) {
    if ($formatter->page->exists())
      $body=$formatter->page->get_raw_body();
    else $body='';
    if (!preg_match("/\n?\[\[OeKaki\($name\)\]\]\n?/i",$body)) {
      if (preg_match("/\n##Draw\n/i",$body))
       $body=preg_replace("/\n##Draw\n/","\n##Draw\n[[OeKaki($name)]]\n",$body);
      else
       $body.="[[OeKaki($name)]]\n";

      $log="Oekaki drawing added";

      $formatter->page->write($body);
      # XXX Oekaki post does not hav any information about user id.
      $DBInfo->savePage($formatter->page,$log,$options);
    }

    if ($backup_imgpath != $imgpath) copy($imgpath,$backup_imgpath);
    $raw=$GLOBALS['HTTP_RAW_POST_DATA'];
    $p=strpos($raw,"\r");
    if ($p < 0) {
      header("Content-type: text/plain");
      print "error\n\n";
    } else {
      $img=fopen($imgpath,'w');
      if (is_resource($img)) {
        fwrite($img,substr($raw,$p+2));
        fclose($img);
      }
    }
    header("Content-type: text/plain");
    print "ok\n\n";
    print $imgpath;
    return;
  }

  if ($options['size'] and preg_match("/(\d{3})x(\d{3})/",$options['size'],$match)) {
    $sizex=$match[1]; $sizey=$match[2];
    if ($sizex < 100 or $sizex > 600) $sizex=300;
    if ($sizey < 100 or $sizey > 600) $sizey=300;
  } else {
    $sizex=300;$sizey=300;
  }

  $extra="<param name='image_canvas' value='$imgurl'>";
  
  $formatter->send_header("",$options);
  $formatter->send_title(_("Create new picture"),"",$options);
  $prefix=$formatter->prefix;
  $now=time();
  $urlpgname=_rawurlencode($options['page']);
  $imgname=_rawurlencode($name);
  $url_exit= $formatter->link_url($urlpgname,"?ts=$now");
  $url_save= $formatter->link_url($urlpgname,"----OeKaki----$imgname");

  $pubpath=$DBInfo->url_prefix."/applets/OekakiPlugin";
  print "<h2>"._("Edit Image")."</h2>\n";
  print <<<APPLET
<applet code="pbbs.PaintBBS.class" archive="PaintBBS.jar"
 codebase="$pubpath"
 name="$pngname.png"
 width="400" height="400" align="center">

<param name="image_width" value="$sizex">
<param name="image_height" value="$sizey">
<param name="image_bkcolor" value="#ffffff">
$extra
<param name="image_jpeg" value="true">
<param name="image_size" value="60">
<param name="compress_level" value="15">

<param name="undo" value="60">
<param name="undo_in_mg" value="15">

<param name="color_text"value="#708090">
<param name="color_bk" value="#A0A0BB">
<param name="color_bk2" value="#A0A0BB">
<param name="color_icon" value="#eeeeee">

<param name="color_bar" value="#8f93a1">
<param name="color_bar_hl" value="#ffffff">
<param name="color_bar_frame_hl" value="#eeeeee">
<param name="color_bar_frame_shadow" value="#aaaaaa">

<param name="bar_size" value="15">

<param name="url_save" value="$url_save">
<param name="url_exit" value="$url_exit">

<param name="tool_advance" value="true">
<param name="send_advance" value="true">

<param name="send_header" value="">
<param name="send_header_image_type" value="false">

<param name="poo" value="true">

<param name="thumbnail_width" value="100%%">
<param name="thumbnail_height" value="100%%">

<param name="security_click" value="0">
<param name="security_timer" value="0">
<param name="security_url" value="">
<param name="security_post" value="false">
<b>NOTE:</b> You need a Java enabled browser to edit the drawing example.
</applet><br>
APPLET;

  $formatter->send_footer("",$options);
  return;
}

?>
