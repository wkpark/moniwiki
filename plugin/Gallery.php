<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Gallery plugin for the MoniWiki
//
// Usage: [[Gallery]]
//
// $Id$
// vim:et:ts=2:

function macro_Gallery($formatter,$value) {
   global $DBInfo;

   # add some actions at the bottom of the page
   if (!$value and !in_array('UploadFile',$formatter->actions)) {
     $formatter->actions[]='UploadFile';
     $formatter->actions[]='UploadedFiles';
   }

   if ($value) {
      $key=$DBInfo->pageToKeyname($value);
      if ($key != $value)
        $prefix=$formatter->link_url($value,"?action=download&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   } else {
      $value=$formatter->page->name;
      $key=$DBInfo->pageToKeyname($formatter->page->name);
      if ($key != $formatter->page->name)
        $prefix=$formatter->link_url($formatter->page->name,"?action=download&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   }

   if (!file_exists($dir)) {
     umask(000);
     mkdir($dir,0777);
   }

   $handle= opendir($dir);

   $upfiles=array();

   while ($file= readdir($handle)) {
      if (is_dir($dir."/".$file)) {
        if ($file=='.' or $file=='..') continue;
        $dirs[]= $DBInfo->keyToPagename($file);
        continue;
      }
      $mtime=filemtime($dir."/".$file);
      $upfiles[$file]= $mtime;
   }
   closedir($handle);

   if (!$upfiles) return "<h3>No files uploaded</h3>";
   asort($upfiles);

   $out.="<table border='0' cellpadding='2'>\n<tr>\n";
   $idx=1;

   if (!$prefix) $prefix=$DBInfo->url_prefix."/".$dir."/";

   $col=3;
   $width=150;

   while (list($file,$mtime) = each ($upfiles)) {
      $link=$prefix.rawurlencode($file);
      $size=filesize($dir."/".$file);
      $date=date("Y-m-d",$mtime);
      if (preg_match("/\.(jpg|jpeg|gif|png)$/i",$file))
        $object="<img src='$link' width='$width' alt='$file'>";
      else
        $object=$file;
      $out.="<td align='center' class='wiki'><a href='$link'>$object</a><br />".
            "@ $date ($size bytes)</td>\n";
      if ($idx % $col == 0) $out.="</tr>\n<tr>\n";
      $idx++;
   }
   $idx--;
   $out.="</tr></table>\n";

   return $out;
}

function do_test($formatter,$options) {
  $formatter->send_header();
  $formatter->send_title();
  $ret= macro_Test($formatter,$options[value]);
  $formatter->send_page($ret);
  $formatter->send_footer("",$options);
  return;
}

?>
