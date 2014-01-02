<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Draw plugin with the Clip applet for the MoniWiki
//
// Usage: [[Clip(hello)]]
//
// $Id: Clip.php,v 1.5 2010/07/17 16:17:56 wkpark Exp $

function macro_Clip($formatter,$value) {
  global $DBInfo;
  $keyname=$DBInfo->_getPageKey($formatter->page->name);
  $_dir=str_replace("./",'',$DBInfo->upload_dir.'/'.$keyname);

  // support hashed upload dir
  if (!is_dir($_dir) and !empty($DBInfo->use_hashed_upload_dir)) {
    $prefix = get_hashed_prefix($keyname);
    $_dir = str_replace('./','',$DBInfo->upload_dir.'/'.$prefix.$keyname);
  }
  $name=_rawurlencode($value);

  $enable_edit=0;

  umask(000);
  if (!file_exists($_dir))
    _mkdir_p($_dir, 0777);

  $pngname=$name.'.png';
  $now=time();

  $url=$formatter->link_url($formatter->page->name,"?action=clip&amp;value=$name&amp;now=$now");

  if (!file_exists($_dir."/$pngname"))
    return "<a href='$url'>"._("Paste a new picture")."</a>";
  $edit='';
  $end_tag='';
  if ($enable_edit) {
    $edit="<a href='$url'>";
    $end_tag='</a>';
  }

  return "$edit<img src='$DBInfo->url_prefix/$_dir/$pngname' border='0' alt='image' />$end_tag\n";
}

function do_Clip($formatter,$options) {
  global $DBInfo;

  $enable_replace=1;

  $keyname=$DBInfo->_getPageKey($options['page']);
  $_dir=str_replace("./",'',$DBInfo->upload_dir.'/'.$keyname);

  // support hashed upload dir
  if (!is_dir($_dir) and !empty($DBInfo->use_hashed_upload_dir)) {
    $prefix = get_hashed_prefix($keyname);
    $_dir = str_replace('./','',$DBInfo->upload_dir.'/'.$prefix.$keyname);
  }
  $pagename=_urlencode($options['page']);

  $name=$options['value'];

  if (!$name) {
    $title=_("Fatal error !");
    $formatter->send_header("Status: 406 Not Acceptable",$options);
    $formatter->send_title($title,"",$options);
    print "<h2>"._("No filename given")."</h2>";
    $formatter->send_footer("",$options);
    
    return;
  }

  $pngname=_rawurlencode($name);

  //$imgpath="$_dir/$pngname";
  $imgpath="$pngname";
  $imgparam = '';
  if (file_exists($_dir.'/'.$imgpath.'.png')) {
    $url=qualifiedUrl($DBInfo->url_prefix.'/'.$_dir.'/'.$imgpath.'.png');
    $imgparam="<param name='image' value='$url' />";
  }

  $png_url="$imgpath.png";

  $formatter->send_header("",$options);
  $formatter->send_title(_("Clipboard"),"",$options);
  $prefix=$formatter->prefix;
  $now=time();

  $url_exit= $formatter->link_url($pagename,"?ts=$now");
  $url_save= $formatter->link_url($pagename,"?action=draw");
  $url_help= $formatter->link_url("ClipMacro");

  $pubpath=$DBInfo->url_prefix."/applets/ClipPlugin";
  print "<h2>"._("Cut & Paste a Clipboard Image")."</h2>\n";
  print <<<APPLET
<applet code="clip"
 archive="clip.jar" codebase="$pubpath"
 width='200' height='200' align="center">
        <param name="pngpath"  value="$png_url" />
        <param name="savepath" value="$url_save" />
        <param name="viewpath" value="$url_exit" />
        <param name="compress" value="5" />
$imgparam
<b>NOTE:</b> You need a Java enabled browser to edit the drawing example.
</applet><br />
APPLET;

  $formatter->send_footer("",$options);
  return;
}

// vim:et:sts=2:
?>
