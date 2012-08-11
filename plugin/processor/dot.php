<?php
// Copyright 2003-2004 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a dot plugin for the MoniWiki
//
// $Id: dot.php,v 1.6 2006/07/13 14:58:55 wkpark Exp $
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
  if ($formatter->refresh or !file_exists($webdot_dir."/$md5sum.dot")) {
    $fp=fopen($webdot_dir."/$md5sum.dot","w");
    fwrite($fp,$dot);
    fclose($fp);

    $cmd="$dotcmd -Tpng $webdot_dir/$md5sum.dot -o $webdot_dir/$md5sum.png";

    $formatter->errlog('Dot');
    $fp=popen($cmd.$formatter->LOG,'r');
    pclose($fp);
    $err=$formatter->get_errlog();
    $formatter->errlog('Dot');
    $cmd="$dotcmd -Timap $webdot_dir/$md5sum.dot -o $webdot_dir/$md5sum.map";
    $fp=popen($cmd.$formatter->LOG,'r');
    pclose($fp);
    $err.=$formatter->get_errlog();
    if ($err)
        $err ="<pre class='errlog'>$err</pre>\n";
  }

  return $err."<a href='$DBInfo->url_prefix/$webdot_dir/$md5sum.map'><img border='0' src='$DBInfo->url_prefix/$webdot_dir/$md5sum.png' ismap /></a>\n";
}

?>
