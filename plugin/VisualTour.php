<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a VisualTour plugin for the MoniWiki
//
// $Id$
// vim:et:ts=2:

function do_VisualTour($formatter,$options) {
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

  if ($options['w'] and $options['w'] < 6) $w=$options['w'];
  else $w=2;
  if ($options['d'] and $options['d'] < 6) $d=$options['d'];
  else $d=3;

  $url=qualifiedUrl($formatter->link_url($formatter->page->urlname,"?action=dot&w=$w&d=$d"));

  $fp=fopen($url,"r");
  $dot="";
  while ($data= fread($fp, 4096)) $dot.=$data;
  fclose($fp);

  $md5sum=$DBInfo->pageToKeyname($options['page']).".".md5($dot);
  if (!file_exists($webdot_dir."/$md5sum.dot")) {
    $fp=fopen($webdot_dir."/$md5sum.dot","w");
    fwrite($fp,$dot);
    fclose($fp);
  }{
    $cmd="$dotcmd -Tpng $webdot_dir/$md5sum.dot -o $webdot_dir/$md5sum.png";
    exec($cmd,$log);
    $cmd="$dotcmd -Timap $webdot_dir/$md5sum.dot -o $webdot_dir/$md5sum.map";
    exec($cmd,$log);
  }

  print "<h2 style='font-family:Tahoma,Sans-serif;'>VisualTour</h2>";

  print $formatter->link_to("?action=visualtour",_("Normal"));
  print "|";
  print $formatter->link_to("?action=visualtour&amp;w=".($w+1)."&amp;d=$d",_("Wider"));
  print "|";
  print $formatter->link_to("?action=visualtour&amp;w=$w&amp;d=".($d+1),_("Deeper"));
  print "<br />";

  print "<a href='$DBInfo->url_prefix/$webdot_dir/$md5sum.map'><img src='$DBInfo->url_prefix/$webdot_dir/$md5sum.png' alt='VisualTour' ismap></a>\n";

  return;
}

?>
