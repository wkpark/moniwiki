<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// rss_blog action plugin for the MoniWiki
//
// $Id$

class Blog_cache {
  function get_blogs() {
    global $DBInfo;

    $handle = @opendir($DBInfo->cache_dir."/blog");
    if (!$handle) return array();

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

  $opts=explode(",",$value);
  
  if (in_array('all',$opts)) {
    $lines=Blog_cache::get_all();
    $logs=array();
    foreach ($lines as $line) $logs[]=explode(" ",$line,4);
    usort($logs,'BlogCompare');
  } else {
    if ($value and $DBInfo->hasPage($value)) {
      $p=$DBInfo->getPage($value);
      $raw_body=$p->get_raw_body();
    } else
      $raw_body=$formatter->page->get_raw_body();
    $temp= explode("\n",$raw_body);

    $logs=array();
    foreach ($temp as $line) {
      if (preg_match("/^{{{#!blog (.*)$/",$line,$match)) {
        $logs[]=explode(" ",$options['page']." ".$match[1],4);
      }
    }
  }

  if (in_array('simple',$opts)) {
    $bra="";
    $sep="<br />";
    $bullet="";
    $cat="";
  } else {
    $bra="<ul class='blog-list'>";
    $bullet="<li class='blog-list'>";
    $sep="</li>\n";
    $cat="</ul>";
  }
  $template='$out="$bullet<a href=\"$url#$tag\">$title</a> <span class=\"blog-user\">';
  if (!in_array('nodate',$opts))
    $template.='@ $date ';
  if (!in_array('nouser',$opts))
    $template.='by $user';

  $template.='</span>$sep\n";';
    
  $time_current= time();
  $items="";

  if (!$logs) return "";

  foreach ($logs as $log) {
    list($page, $user,$date,$title)= $log;
    $url=qualifiedUrl($formatter->prefix."/".$page);

    if (!$opts['nouser'] and $user and $DBInfo->hasPage($user))
      $user=$formatter->link_tag(_rawurlencode($user),"",$user);

    if (!$title) continue;

    #$tag=_rawurlencode(normalize($title));
    $tag=md5($user." ".$date." ".$title);

    $date[10]=' ';
    $time=strtotime($date." GMT");
    $date= date("m-d [h:i a]",$time);

    #$items.="$bullet<a href='$url#$tag'>$title</a> <span class='blog-user'>@ $date </span>$sep\n";
    eval($template);
    $items.=$out;
  }
  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));
  return $bra.$items.$cat;
}
?>
