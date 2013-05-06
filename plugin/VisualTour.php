<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a VisualTour plugin for the MoniWiki
//
// $Id: VisualTour.php,v 1.11 2010/09/07 12:11:49 wkpark Exp $

function macro_VisualTour($formatter,$value,$options=array()) {
  global $DBInfo;

  putenv('GDFONTPATH='.getcwd().'/data');
  $dotcmd="dot";
  #$dotcmd="twopi";
  #$dotcmd="neato";
  $maptype = 'imap';
  $maptype = 'cmap';

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

  if (!empty($options['w']) and $options['w'] < 6) $w=$options['w'];
  else $w=!empty($w) ? $w:2;
  if (!empty($options['d']) and $options['d'] < 6) $d=$options['d'];
  else $d=!empty($d) ? $d:3;

  if (!empty($options['f'])) $extra.="&f=".$options['f'];
  if (!empty($options['arena'])) $extra.="&arena=".$options['arena'];

  if (isset($pgname[0]))
    $urlname=_urlencode($pgname);
  else {
    $urlname=$formatter->page->urlname;
    $pgname=$formatter->page->name;
  }

  $dot=$formatter->macro_repl('dot',$pgname,$options);

  if (!empty($DBInfo->cache_public_dir)) {
    $fc = new Cache_text('visualtour', array('dir'=>$DBInfo->cache_public_dir));
    $fname = $fc->getKey($dot);
    $basename= $DBInfo->cache_public_dir.'/'.$fname;
    $dotfile= $basename.'.dot';
    $pngfile= $basename.'.png';
    $mapfile= $basename.'.map';
    $urlbase=
      $DBInfo->cache_public_url ? $DBInfo->cache_public_url.'/'.$fname:
      $DBInfo->url_prefix.'/'.$basename;
    $png_url= $urlbase.'.png';
    $map_url= $urlbase.'.map';
  } else {
    $md5sum=md5($dot);
    $cache_dir= $DBInfo->upload_dir."/VisualTour";
    $cache_url= $DBInfo->upload_url ? $DBInfo->upload_url.'/VisualTour':
      $DBInfo->url_prefix.'/'.$cache_dir;
    $basename= $cache_dir.'/'.$md5sum;
    $pngfile= $basename.'.png';
    $mapfile= $basename.'.map';
    $dotfile= $basename.'.dot';
    $urlbase= $cache_url.'/'.$md5sum;
    $png_url= $urlbase.'.png';
    $map_url= $urlbase.'.map';
  }

  if (!is_dir(dirname($pngfile))) {
    $om=umask(000);
    _mkdir_p(dirname($pngfile),0777);
    umask($om);
  }

  $err = '';
  if ($formatter->refresh or !file_exists($dotfile)) {
    $fp=fopen($dotfile,"w");
    fwrite($fp,$dot);
    fclose($fp);

    $cmd="$dotcmd -Tpng $dotfile -o $pngfile";
    $formatter->errlog('Dot');
    $fp=popen($cmd.$formatter->LOG,'r');
    pclose($fp);
    $err=$formatter->get_errlog();
    $cmd="$dotcmd -T$maptype $dotfile -o $mapfile";
    $formatter->errlog('Dot');
    $fp=popen($cmd.$formatter->LOG,'r');
    pclose($fp);
    $err.=$formatter->get_errlog();
    if ($err)
        $err ="<pre class='errlog'>$err</pre>\n";

  }

  if ($maptype == 'imap') {
    $attr = ' ismap="ismap"';
    return $err."<span class='VisualTour'><a href='$map_url'><img src='$png_url' alt='VisualTour'$attr></a></span>\n";
  } else {
    $attr = ' usemap="#mainmap"';
    $fp = fopen($mapfile,'r');
    $map = '';
    if (is_resource($fp)) {
      while(!feof($fp)) $map.= fgets($fp,1024);
      fclose($fp);
      $map = '<map name="mainmap">'.$map.'</map>';
    }
    return $err."<span class='VisualTour'><img src='$png_url' alt='VisualTour'$attr>$map</span>\n";
  }
}

function do_VisualTour($formatter,$options) {
  $formatter->send_header();
  $selfurl=$formatter->link_to();
  if (!empty($options['w']) and $options['w'] < 6) $w=$options['w'];
  else $w=2;
  if (!empty($options['d']) and $options['d'] < 6) $d=$options['d'];
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
