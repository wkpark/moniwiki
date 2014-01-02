<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Draw plugin with the JHotDraw for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-01-01
// Name: Draw Plugin
// Description: Draw gif drawing using the TWiki Draw plugin 
// URL: MoniWiki:DrawPlugin
// Version: $Revision: 1.8 $
// License: GPL
// Usage: [[Draw(hello)]] without a gif extention.
//
// $Id: Draw.php,v 1.8 2010/07/17 16:17:56 wkpark Exp $

function macro_Draw($formatter,$value) {
  global $DBInfo;
  $keyname=$DBInfo->_getPageKey($formatter->page->name);
  $_dir=str_replace("./",'',$DBInfo->upload_dir.'/'.$keyname);
  $name=_rawurlencode($value);

  // support hashed upload dir
  if (!is_dir($_dir) and !empty($DBInfo->use_hashed_upload_dir)) {
    $prefix = get_hashed_prefix($keyname);
    $_dir = str_replace('./','',$DBInfo->upload_dir.'/'.$prefix.$keyname);
  }

  $enable_edit=1;

  umask(000);
  if (!file_exists($_dir))
    _mkdir_p($_dir, 0777);

  $gifname='Draw_'.$name.".gif";
  $mapname='Draw_'.$name.".map";
  $now=time();

  $url=$formatter->link_url($formatter->page->name,"?action=draw&amp;value=$name&amp;now=$now");

  if (!file_exists($_dir."/$gifname"))
    return "<a href='$url'>"._("Draw new picture")."</a>";
  $editable='';
  if ($enable_edit)
    $editable="<a href='$url'>Edit</a>";

  $maptag='';
  $map='';
  if (file_exists($_dir."/$mapname")) {
    $maptag=" usemap='#$name'";
    $map=file($_dir."/$mapname");
    $map=implode("",$map);
    $map=preg_replace('/HREF="%TWIKIDRAW%"/','nohref',$map);
    $map=preg_replace("/%MAPNAME%/",$name,$map);
  }

  return "$map<img src='$DBInfo->upload_dir_url/$keyname/$gifname' border='0' alt='hotdraw' $maptag /></a>\n".$editable;
}

function do_post_Draw($formatter,$options=array()) {
  global $DBInfo;

  $enable_replace=1;

  $keyname=$DBInfo->_getPageKey($options['page']);
  $_dir=str_replace("./",'',$DBInfo->upload_dir.'/'.$keyname);
  $pagename=$options['page'];

  // support hashed upload dir
  if (!is_dir($_dir) and !empty($DBInfo->use_hashed_upload_dir)) {
    $prefix = get_hashed_prefix($keyname);
    $_dir = str_replace('./','',$DBInfo->upload_dir.'/'.$prefix.$keyname);
  }

  umask(000);
  if (!file_exists($_dir))
    _mkdir_p($_dir, 0777);

  $name=$options['value'];

  if (!empty($_FILES['filepath'])) {
    $upfile=$_FILES['filepath']['tmp_name'];
    $temp=explode("/",$_FILES['filepath']['name']);
    $upfilename= $temp[count($temp)-1];
    preg_match("/(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$fname);
    # do not change the extention of the file.
    $file_path= $newfile_path = $_dir."/".$upfilename;

    # is file already exists ?
    $dummy=0;
    while (file_exists($newfile_path)) {
      $dummy=$dummy+1;
      $ufname=$fname[1]."_".$dummy; // rename file
      $upfilename=$ufname.".$fname[2]";
      $newfile_path= $_dir."/".$upfilename;
    }
    if ($enable_replace) {
      if ($file_path != $newfile_path)
        $test=@copy($file_path, $newfile_path);
      $test=@copy($upfile, $file_path);
    } else
      $test=@copy($upfile, $newfile_path);
    if (!$test) {
      $title=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);
      $formatter->send_header("Status: 406 Not Acceptable",$options);
      $formatter->send_title($title,"",$options);
      return;
    }
    if ($fname[2] == 'map') {
      # fix map file.
      $map=file($newfile_path);
      $map=implode('',$map);
      # remove useless areas
      $map=preg_replace('/HREF="%TWIKIDRAW%"/','nohref',$map);
      $fp=fopen($newfile_path,'w');
      if ($fp) {
        fwrite($fp,$map);
        fclose($fp);
      }
    }
    chmod($newfile_path,0644);
    if ($fname[2] == 'draw') {
      $comment=sprintf("Drawing '%s' uploaded",$upfilename);
      $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];
      $DBInfo->addLogEntry($keyname, $REMOTE_ADDR,$comment,"ATTDRW");
    }
    return;
  }

  if (!$name) {
    $title=_("Fatal error !");
    $formatter->send_header("Status: 406 Not Acceptable",$options);
    $formatter->send_title($title,"",$options);
    print "<h2>"._("No filename given")."</h2>";
    $formatter->send_footer("",$options);
    
    return;
  }

  $gifname=_rawurlencode($name);
  if (empty($_GET['mode']) or $_GET['mode'] != 'attach') {
    $gifname='Draw_'.$gifname;
  }

  $imgpath="$_dir/$gifname";
  $ufname = $gifname;

  $dummy=0;
  while (file_exists($imgpath)) {
     $dummy=$dummy+1;
     $ufname=$gifname."_".$dummy; // rename file
     $imgpath= "$_dir/$ufname";
  }

  $draw_url="$DBInfo->upload_dir_url/$keyname/$ufname.draw";
  $gif_url="$DBInfo->upload_dir_url/$keyname/$ufname.gif";

  $formatter->send_header("",$options);
  $formatter->send_title(_("Edit drawing"),"",$options);
  $prefix=$formatter->prefix;
  $now=time();

  $url_exit= $formatter->link_url($options['page'],"?ts=$now");
  $url_save= $formatter->link_url($options['page'],"?action=draw");
  $url_help= $formatter->link_url("HotDraw");

  $pubpath=$DBInfo->url_prefix."/applets/TWikiDrawPlugin";
  print "<h2>"._("Edit new drawing")."</h2>\n";
  print <<<APPLET
<applet code="CH.ifa.draw.twiki.TWikiDraw.class"
 archive="twikidraw.jar" codebase="$pubpath"
 width='500' height='40' align="center">
        <param name="drawpath" value="$draw_url">
        <param name="gifpath"  value="$gif_url">
        <param name="savepath" value="$url_save">
        <param name="viewpath" value="$url_exit">
        <param name="helppath" value="$url_help">
<b>NOTE:</b> You need a Java enabled browser to edit the drawing example.
</applet><br />
APPLET;

  $formatter->send_footer("",$options);
  return;
}

// vim:et:sts=2:
?>
