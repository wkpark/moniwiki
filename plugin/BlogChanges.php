<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// rss_blog action plugin for the MoniWiki
//
// $Id$

class Blog_cache {
  function get_blogs() {
    global $DBInfo;

    $handle = opendir($DBInfo->cache_dir."/blog");

    while ($file = readdir($handle)) {
      if (is_dir($DBInfo->cache_dir."/blog/".$file)) continue;
      $blogs[] = $file;
    }
    closedir($handle);
    return $blogs;
  }

  function get_all() {
    global $DBInfo;
    $all=Blog_cache::get_blogs();
    $lines=array();
    foreach ($all as $blog) {
      $name=$DBInfo->cache_dir."/blog/".$blog;
      $pagename=$DBInfo->keyToPagename($blog);
      $items=file($name);
      foreach ($items as $line) $lines[]=$pagename." ".rtrim($line);
    }
    return $lines;
  }
}

function BlogCompare($a,$b) {
  # third field is a date
  if ($a[2] > $b[2]) return -1;
  if ($a[2] < $b[2]) return 1;
  return 0;
}

function macro_BlogChanges($formatter,$value) {
  global $DBInfo;

  if ($value=='all') {
    $lines=Blog_cache::get_all();
    $logs=array();
    foreach ($lines as $line) $logs[]=explode(" ",$line,4);
    usort($logs,'BlogCompare');
  } else {
    $raw_body=$formatter->page->get_raw_body();
    $temp= explode("\n",$raw_body);

    $logs=array();
    foreach ($temp as $line) {
      if (preg_match("/^{{{#!blog (.*)$/",$line,$match)) {
        $logs[]=explode(" ",$options['page']." ".$match[1],4);
      }
    }
  }
    
  $time_current= time();
  $items="";

  if (!$lines) return "";

  foreach ($logs as $log) {
    list($page, $user,$date,$title)= $log;
    $url=qualifiedUrl($formatter->prefix."/".$page);

    if (!$title) continue;

    #$tag=_rawurlencode(normalize($title));
    $tag=md5($user." ".$date." ".$title);

    $date[10]=' ';
    $time=strtotime($date." GMT");
    $date= date("m-d [h:i a]",$time);

    #$items.="<li><a href='$url#$tag'>$title</a> <span class='blog-user'>@ $date by $user</span></li>\n";
    $items.="<li><a href='$url#$tag'>$title</a> <span class='blog-user'>@ $date </span></li>\n";
  }
  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));
  return "<ul>".$items."</ul>";
}
?>
