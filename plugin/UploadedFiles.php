<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadedFiles plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function do_uploadedfiles($formatter,$options) {
  $list=macro_UploadedFiles($formatter,$options['page'],$options);

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  print $list;
  $args['editable']=0;
  $formatter->send_footer($args,$options);
  return;
}

function macro_UploadedFiles($formatter,$value="",$options="") {
   global $DBInfo;

   $download='download';
   $needle="//";
   if ($options['download']) $download=$options['download'];
   if ($options['needle']) $needle=$options['needle'];

   if (!in_array('UploadFile',$formatter->actions))
     $formatter->actions[]='UploadFile';

   if ($value and $value!='UploadFile') {
      $key=$DBInfo->pageToKeyname($value);
      if ($options['download'] or $key != $value)
        $prefix=$formatter->link_url(_rawurlencode($value),"?action=$download&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   } else {
      $value=$formatter->page->urlname;
      $key=$DBInfo->pageToKeyname($formatter->page->name);
      if ($options['download'] or $key != $formatter->page->name)
        $prefix=$formatter->link_url($formatter->page->urlname,"?action=$download&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   }
   if ($value!='UploadFile' and file_exists($dir))
      $handle= opendir($dir);
   else {
      $key='';
      $value='UploadFile';
      $dir=$DBInfo->upload_dir;
      $handle= opendir($dir);
   }

   $upfiles=array();
   $dirs=array();

   while ($file= readdir($handle)) {
      if ($file[0]=='.') continue;
      if (!$options['nodir'] and is_dir($dir."/".$file)) {
        if ($value =='UploadFile')
          $dirs[]= $DBInfo->keyToPagename($file);
      } else if (preg_match($needle,$file))
        $upfiles[]= $file;
   }
   closedir($handle);
   if (!$upfiles and !$dirs) return "<h3>No files uploaded</h3>";
   sort($upfiles); sort($dirs);

   $link=$formatter->link_url($formatter->page->urlname);
   $out="<form method='post' action='$link'>";
   $out.="<input type='hidden' name='action' value='DeleteFile' />\n";
   if ($key)
     $out.="<input type='hidden' name='value' value='$value' />\n";
   $out.="<table border='0' cellpadding='2'>\n";
   $out.="<tr><th colspan='2'>File name</th><th>Size</th><th>Date</th></tr>\n";
   $idx=1;
   foreach ($dirs as $file) {
      $link=$formatter->link_url($file,"?action=uploadedfiles",$file);
      $date=date("Y-m-d",filemtime($dir."/".$DBInfo->pageToKeyname($file)));
      $out.="<tr><td class='wiki'><input type='checkbox' name='files[$idx]' value='$file' /></td><td class='wiki'><a href='$link'>$file/</a></td><td align='right' class='wiki'>&nbsp;</td><td class='wiki'>$date</td></tr>\n";
      $idx++;
   }

   if (!$options['nodir'] and !$dirs) {
      $link=$formatter->link_tag('UploadFile',"?action=uploadedfiles&amp;value=top","..");
      $date=date("Y-m-d",filemtime($dir."/.."));
      $out.="<tr><td class='wiki'>&nbsp;</td><td class='wiki'>$link</td><td align='right' class='wiki'>&nbsp;</td><td class='wiki'>$date</td></tr>\n";
   }

   if (!$prefix) $prefix=$DBInfo->url_prefix."/".$dir."/";

   $unit=array('Bytes','KB','MB','GB','TB');

   $down_mode=substr($prefix,strlen($prefix)-1) === '=';
   foreach ($upfiles as $file) {
      if ($down_mode)
        $link=str_replace("value=","value=".rawurlencode($file),$prefix);
      else
        $link=$prefix.rawurlencode($file);
      $size=filesize($dir.'/'.$file);

      $i=0;
      for (;$i<4;$i++) {
         if ($size <= 1024) {
            $size= round($size,2).' '.$unit[$i];
            break;
         }
         $size=$size/1024;
      }
      $size=round($size,2).' '.$unit[$i];

      $date=date('Y-m-d',filemtime($dir.'/'.$file));
      $out.="<tr><td class='wiki'><input type='checkbox' name='files[$idx]' value='$file' /></td><td class='wiki'><a href='$link'>$file</a></td><td align='right' class='wiki'>$size</td><td class='wiki'>$date</td></tr>\n";
      $idx++;
   }
   $idx--;
   $out.="<tr><th colspan='2'>Total $idx files</th><td></td><td></td></tr>\n";
   $out.="</table>\n";
   if ($DBInfo->security->is_protected("deletefile",$options))
     $out.=_("Password").": <input type='password' name='passwd' size='10' />\n";
   $out.="<input type='submit' value='"._("Delete selected files")."' /></form>\n";

   if (!$value and !in_array('UploadFile',$formatter->actions))
     $formatter->actions[]='UploadFile';
   return $out;
}

?>
