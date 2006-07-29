<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// download action plugin for the MoniWiki
//
// $Id$
//
function do_download($formatter,$options) {
  global $DBInfo;

  if (!$options['value']) {
    if (!function_exists('do_uploadedfiles'))
      include_once dirname(__FILE__).'/UploadedFiles.php';
    do_uploadedfiles($formatter,$options);
    return; 
  }
  $value=&$options['value'];
  if (($p=strpos($value,':')) !== false or ($p=strpos($value,'/')) !== false) {
    $subpage=substr($value,0,$p);
    $file=substr($value,$p+1);
    $value=$subpage.'/'.$file; # normalize page arg
    if ($subpage and $DBInfo->hasPage($subpage)) {
      $pagename=&$subpage;
      $key=$DBInfo->pageToKeyname($subpage);
    } else {
      $pagename='';
      $key='';
    }
  } else {
    $pagename=&$formatter->page->name;
    $key=$DBInfo->pageToKeyname($formatter->page->name);
  }

  #if (!$key) {
  #  // FIXME
  #  return;
  #}
  $dir=$DBInfo->upload_dir."/$key";

  if (file_exists($dir))
    $handle= opendir($dir);
  else {
    $dir=$DBInfo->upload_dir;
    $handle= opendir($dir);
  }
  $acceptable_dirs=array('thumbnails');
  $file=explode('/',$options['value']);
  $subdir='';
  if (count($file) > 1)
    $subdir=in_array($file[count($file)-2],$acceptable_dirs) ?
      $file[count($file)-2].'/':'';

  $file=$subdir.$file[count($file)-1];

  if (!file_exists("$dir/$file")) 
    return;

  $lines = @file($DBInfo->data_dir.'/mime.types');
  if ($lines) {
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
  } else
    $mime=array();
  if (preg_match("/\.(.{1,4})$/",$file,$match))
    $mimetype=strtolower($mime[$match[1]]);
  if (!$mimetype) $mimetype="application/x-unknown";

  header("Content-Type: $mimetype\r\n");
  header("Content-Disposition: inline; filename=\"$file\"" );
  #header("Content-Disposition: attachment; filename=\"$file\"" );
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
