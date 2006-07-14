<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a VisualTour plugin for the MoniWiki
//
// $Id$

function macro_VisualTour($formatter,$value,$options=array()) {
  global $DBInfo;

  putenv('GDFONTPATH='.getcwd().'/data');
  $dotcmd="dot";
  #$dotcmd="twopi";
  #$dotcmd="neato";
  $webdot_dir=$DBInfo->upload_dir."/VisualTour";

  if (!file_exists($webdot_dir)) {
    umask(000);
    mkdir($webdot_dir,0777);
  }

  if (!$formatter->page->exists())
    return "";

  $args=explode(',',$value);
  $extra='';
  foreach ($args as $arg) {
    $arg=trim($arg);
    if (($p=strpos($arg,'='))===false) {
      if ($arg == 'show') $extra.='&t=show';
      else if (is_int($arg)) $w=$arg;
      else if ($DBInfo->hasPage($arg)) $pgname=$arg;
    } else {
      $k=strtok($arg,'=');
      $v=strtok('');
      if ($k == 'width' or $k =='w') $w=(int)$v;
      else if ($k == 'depth' or $k =='d') $d=(int)$v;
      else if ($k == 'arena' or $k =='a') $extra.='&arena='.$v;
    }
  }

  if ($options['w'] and $options['w'] < 6) $w=$options['w'];
  else $w=$w?$w:2;
  if ($options['d'] and $options['d'] < 6) $d=$options['d'];
  else $d=$d?$d:3;

  if ($options['f']) $extra.="&f=".$options['f'];
  if ($options['arena']) $extra.="&arena=".$options['arena'];

  if ($pgname)
    $urlname=_urlencode($pgname);
  else {
    $urlname=$formatter->page->urlname;
    $pgname=$formatter->page->name;
  }

  $url=qualifiedUrl($formatter->link_url($urlname,"?action=dot&w=$w&d=$d$extra"));

  $fp=fopen($url,"r");
  $dot="";
  while ($data= fread($fp, 4096)) $dot.=$data;
  fclose($fp);

  $md5sum=$DBInfo->pageToKeyname($pgname).".".md5($dot);
  if ($formatter->refresh or !file_exists($webdot_dir."/$md5sum.dot")) {
    $fp=fopen($webdot_dir."/$md5sum.dot","w");
    fwrite($fp,$dot);
    fclose($fp);

    $cmd="$dotcmd -Tpng $webdot_dir/$md5sum.dot -o $webdot_dir/$md5sum.png";
    $formatter->errlog('Dot');
    $fp=popen($cmd.$formatter->LOG,'r');
    pclose($fp);
    $err=$formatter->get_errlog();
    $cmd="$dotcmd -Timap $webdot_dir/$md5sum.dot -o $webdot_dir/$md5sum.map";
    $formatter->errlog('Dot');
    $fp=popen($cmd.$formatter->LOG,'r');
    pclose($fp);
    $err.=$formatter->get_errlog();
    if ($err)
        $err ="<pre class='errlog'>$err</pre>\n";

  }

  return $err."<span class='VisualTour'><a href='$DBInfo->url_prefix/$webdot_dir/$md5sum.map'><img src='$DBInfo->url_prefix/$webdot_dir/$md5sum.png' alt='VisualTour' ismap></a></span>\n";
}

function do_VisualTour($formatter,$options) {
  $formatter->send_header();
  $selfurl=$formatter->link_to();
  if ($options['w'] and $options['w'] < 6) $w=$options['w'];
  else $w=2;
  if ($options['d'] and $options['d'] < 6) $d=$options['d'];
  else $d=3;

  print "<h2 style='font-family:Tahoma,Sans-serif;'>VisualTour on $selfurl</h2>";

  print $formatter->link_to("?action=visualtour",_("Normal"));
  print "|";
  print $formatter->link_to("?action=visualtour&amp;w=".($w+1)."&amp;d=$d",_("Wider"));
  print "|";
  print $formatter->link_to("?action=visualtour&amp;w=$w&amp;d=".($d+1),_("Deeper"));
  print "|";
  print $formatter->link_to("?action=visualtour&amp;refresh=1&amp;w=$w&amp;d=".$d,_("Refresh"));
  print "<br />";

  print macro_VisualTour($formatter,'',$options);

  return;
}

// vim:et:sts=2:
?>
