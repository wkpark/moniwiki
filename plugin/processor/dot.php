<?php
// Copyright 2003-2004 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a dot plugin for the MoniWiki
//
// $Id$
// vim:et:ts=2:

function processor_dot($formatter,$value) {
  global $DBInfo;

  putenv('GDFONTPATH='.getcwd().'/data');
  $dotcmd="dot";
  #$dotcmd="twopi";
  #$dotcmd="neato";
  $webdot_dir=$DBInfo->upload_dir."/Dot";

  if (!file_exists($webdot_dir)) {
    umask(000);
    mkdir($webdot_dir,0777);
  }

  $dot=$value;

  $md5sum=md5($dot);
  if (!file_exists($webdot_dir."/$md5sum.dot")) {
    $fp=fopen($webdot_dir."/$md5sum.dot","w");
    fwrite($fp,$dot);
    fclose($fp);
  }{
    $cmd="$dotcmd -Tpng $webdot_dir/$md5sum.dot -o $webdot_dir/$md5sum.png";
    $fp=popen($cmd,'w');
    fclose($fp);
    $cmd="$dotcmd -Timap $webdot_dir/$md5sum.dot -o $webdot_dir/$md5sum.map";
    popen($cmd,'w');
    fclose($fp);
  }

  return "<a href='$DBInfo->url_prefix/$webdot_dir/$md5sum.map'><img border='0' src='$DBInfo->url_prefix/$webdot_dir/$md5sum.png' ismap /></a>\n";
}

?>
