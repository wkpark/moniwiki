<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// download action plugin for the MoniWiki
//
// $Id$
//
function do_download($formatter,$options) {
  global $DBInfo;

  if (!$options[value]) {
    do_uploadedfiles($formatter,$options);
    exit; 
  }
  $key=$DBInfo->pageToKeyname($formatter->page->name);
  if (!$key) {
    // FIXME
    exit;
  }
  $dir=$DBInfo->upload_dir."/$key";

  if (file_exists($dir))
    $handle= opendir($dir);
  else {
    $dir=$DBInfo->upload_dir;
    $handle= opendir($dir);
  }
  $file=explode("/",$options[value]);
  $file=$file[count($file)-1];

  if (!file_exists("$dir/$file")) {
    exit;
  }

  $lines = file('data/mime.types');
  foreach($lines as $line) {
    rtrim($line);
    if (preg_match('/^\#/', $line))
      continue;
    $elms = preg_split('/\s+/', $line);
    $type = array_shift($elms);
    foreach ($elms as $elm) {
     $mime[$elm] = $type;
    }
  }
  if (preg_match("/\.(.{1,4})$/",$file,$match))
    $mimetype=strtolower($mime[$match[1]]);
  if (!$mimetype) $mimetype="application/x-unknown";

  header("Content-Type: $mimetype\r\n");
  header("Content-Disposition: inline; filename=$file" );
  #header("Content-Disposition: attachment; filename=$file" );
  header("Content-Description: MoniWiki PHP Downloader" );
  Header("Pragma: no-cache");
  Header("Expires: 0");

  $fp=readfile("$dir/$file");
  return;
}

function macro_download($formatter,$value) {
  return $formatter->link_to("?action=download&amp;value=$value",$value);
}
?>
