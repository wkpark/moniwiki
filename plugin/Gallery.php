<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Gallery plugin for the MoniWiki
//
// Usage: [[Gallery]]
//
// $Id$
// vim:et:ts=2:

function get_pagelist($formatter,$pages,$action,$curpage=1,$listcount=10,$bra="[",$cat="]",$sep="|",$prev="&#171;",$next="&#187;",$first="",$last="",$ellip="...") {

  if ($curpage >=0)
    if ($curpage > $pages)
      $curpage=$pages;
  if ($curpage <= 0)
    $curpage=1;

  $startpage=intval(($curpage-1) / $listcount)*$listcount +1;

  $pnut="";
  if ($startpage > 1) {
    $prevref=$startpage-1;
    if (!$first) {
      $prev_l=$formatter->link_tag('',$action.$prevref,$prev);
      $prev_1=$formatter->link_tag('',$action."1","1");
      $pnut="$prev_l".$bra.$prev_1.$cat.$ellip.$bar;
    }
  } else {
    $pnut=$prev.$bra."";
  }

  for ($i=$startpage;$i < ($startpage + $listcount) && $i <=$pages; $i++) {
    if ($i != $startpage)
      $pnut.=$sep;
    if ($i != $curpage) {
      $link=$formatter->link_tag('',$action.$i,$i);
      $pnut.=$link;
    } else
      $pnut.="<b>$i</b>";
  }

  if ($i <= $pages) {
    if (!$last) {
      $next_l=$formatter->link_tag('',$action.$pages,$pages);
      $next_i=$formatter->link_tag('',$action.$i,$next);

      $pnut.=$cat.$ellip.$bra.$next_l.$cat.$next_i;
    }
  } else {
    $pnut.="".$cat.$next;
  }
  return $pnut;
}

function macro_Gallery($formatter,$value,$options='') {
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

  $upfiles=array();
  $comments=array();
  if (file_exists($dir."/list.txt")) {
    $cache=file($dir."/list.txt");
    foreach ($cache as $line) {
      list($name,$mtime,$comment)=explode("\t",rtrim($line),3);
      $upfiles[$name]=$mtime;
      $comments[$name]=$comment;
    }
  }

  if (filemtime($dir) > filemtime($dir."/list.txt")) {
    unset($upfiles);

    $handle= opendir($dir);
    $cache='';
    $cr='';
    while ($file= readdir($handle)) {
      if ($file[0]=='.' or $file=='list.txt' or is_dir($dir."/$file")) continue;
      $mtime=filemtime($dir."/".$file);
      $cache.=$cr.$file."\t".$mtime;
      $upfiles[$file]= $mtime;
      if ($comments[$file] != '') $cache.="\t".$comments[$file];
      $cr="\n";
    }
    closedir($handle);
    $fp=@fopen($dir."/list.txt",'w');
    if ($fp) {
      fwrite($fp,$cache);
      fclose($fp);
    }
  }

  if (!$upfiles) return "<h3>No files uploaded</h3>";
  asort($upfiles);

  $out.="<table border='0' cellpadding='2'>\n<tr>\n";
  $idx=1;

  if (!$prefix) $prefix=$DBInfo->url_prefix."/".$dir."/";

  $col=3;
  $width=150;
  $perpage=$col*4;

  $pages= intval(sizeof($upfiles) / $perpage);
  if (sizeof($upfiles) % $perpage)
    $pages++;

  if ($options['p'] > 1) {
    $slice_index=$perpage*(intval($options['p'] - 1));
    $upfiles=array_slice($upfiles,$slice_index);
  }

  if ($pages > 1)
    $pnut=get_pagelist($formatter,$pages,"?action=gallery&p=",$options['p'],$perpage);

  if (!file_exists($dir."/thumbnails")) mkdir($dir."/thumbnails");

  while (list($file,$mtime) = each ($upfiles)) {
    $size=filesize($dir."/".$file);
    $link=$prefix.rawurlencode($file);
    $date=date("Y-m-d",$mtime);
    if (preg_match("/\.(jpg|jpeg|gif|png)$/i",$file)) {
      if ($DBInfo->use_covert_thumbs and !file_exists($dir."/thumbnails/".$file)) {
        system("convert -scale ".$width." ".$dir."/".$file." ".$dir."/thumbnails/".$file);
      }
      if (file_exists($dir."/thumbnails/".$file)) {
        $thumb=$prefix."thumbnails/".rawurlencode($file);
        $object="<img src='$thumb' alt='$file' />";
      } else {
        $object="<img src='$link' width='$width' alt='$file' />";
      }
    }
    else
      $object=$file;
    $comment='';
    if ($comments[$file] != '') $comment="<br/>".$comments[$file];
    $out.="<td align='center' valign='top' class='wiki'><a href='$link'>$object</a><br />".
          "@ $date ($size bytes)$comment</td>\n";
    if ($idx % $col == 0) $out.="</tr>\n<tr>\n";
    $idx++;
    if ($idx > $perpage) break;
  }
  $idx--;
  $out.="</tr></table>\n";

  return $pnut.$out.$pnut;
}

function do_gallery($formatter,$options='') {
  $ret=macro_Gallery($formatter,'',$options);
  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  print $ret;

  $formatter->send_footer("",$options);

}

?>
