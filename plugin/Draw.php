<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Draw plugin with the JHotDraw for the MoniWiki
//
// Usage: [[Draw(hello)]]
//
// $Id$

function macro_Draw($formatter,$value) {
  global $DBInfo;
  $hotdraw_dir=str_replace("./",'',$DBInfo->upload_dir.'/Draw');
  $name=_rawurlencode($value);

  umask(000);
  if (!file_exists($hotdraw_dir))
    mkdir($hotdraw_dir, 0777);

  $gifname='Draw_'.$name.".gif";
  $now=time();

  $url=$formatter->link_url($formatter->page->name,"?action=draw&amp;value=$name&amp;now=$now");

  if (!file_exists($hotdraw_dir."/$gifname"))
    return "<a href='$url'>"._("Draw new picture")."</a>";

  return "<a href='$url'><img src='$DBInfo->url_prefix/$hotdraw_dir/$gifname' alt='hotdraw'></a>\n";
}

function do_post_Draw($formatter,$options) {
  global $DBInfo;

  $hotdraw_dir=str_replace("./",'',$DBInfo->upload_dir.'/Draw');
  $pagename=$options['page'];

  $name=$options['value'];

  if ($_FILES['filepath']) {
    $upfile=$_FILES['filepath']['tmp_name'];
    $temp=explode("/",$_FILES['filepath']['name']);
    $file_path=$hotdraw_dir."/".$temp[count($temp)-1];

    $test=@copy($upfile, $file_path);
    if (!$test) {
      $title=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);
      $formatter->send_header("Status: 406 Not Acceptable",$options);
      $formatter->send_title($title,"",$options);
      return;
    }
    chmod($file_path,0644);
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

  $gifname='Draw_'._rawurlencode($name);

  $imgpath="$hotdraw_dir/$gifname";

  $dummy=0;
  while (file_exists($imgpath)) {
     $dummy=$dummy+1;
     $ufname=$gifname."_".$dummy; // rename file
     $imgpath= "$hotdraw_dir/$ufname";
  }

  $draw_url="$DBInfo->url_prefix/$imgpath.draw";
  $gif_url="$DBInfo->url_prefix/$imgpath.gif";

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
