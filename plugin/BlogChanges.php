<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// rss_blog action plugin for the MoniWiki
//
// $Id$

class Blog_cache {
  function get_blogs($date) {
    global $DBInfo;

    $blogs=array();
    $handle = @opendir($DBInfo->cache_dir."/blog");
    if (!$handle) return array();

    while ($file = readdir($handle)) {
      if (is_dir($DBInfo->cache_dir."/blog/".$file)) continue;
      if (preg_match("/^$date/",$file))
        $blogs[] = $file;
    }
    closedir($handle);
    return $blogs;
  }

  function get_all($date) {
    global $DBInfo;
    $all=Blog_cache::get_blogs($date);
    $lines=array();
    foreach ($all as $stamped_blog) {
      $fname=$DBInfo->cache_dir."/blog/".$stamped_blog;

      #strip datestamp
      list($datestamp,$blog)=explode('_2e',$stamped_blog,2);
      $pagename=$DBInfo->keyToPagename($blog);
      $items=file($fname);
      foreach ($items as $line) {
        list($author,$datestamp,$dummy)=explode(' ',$line);
        $datestamp[10]=' ';
        $timestamp= strtotime($datestamp." GMT");
        $datestamp= date("Ymd",$timestamp);
        if (preg_match("/^$date/",$datestamp))
          $lines[]=$pagename." ".rtrim($line);
      }
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

function do_BlogChanges($formatter,$options='') {
  if (!$options['date']) $options['date']=date('Ym');
  $changes=macro_BlogChanges($formatter,$options['mode'],$options);
  $formatter->send_header('',$options);
  $formatter->send_title('','',$options);
  print '<div id="wikiContent">';
  print $changes;
  print '</div>';
  $formatter->send_footer('',$options);
  return;
}

function macro_BlogChanges($formatter,$value,$options='') {
  global $DBInfo;

  $opts=explode(",",$value);

  if ($options['date']) {
    $date=$options['date'];
    # check error and set default value
    if (!preg_match('/^\d+$/',$date)) $date=date('Ym');
  } else $date=date('Ym'); # default: show BlogChages monthly

  if (strlen($date)==6) {
    $pre_month=intval(substr($date,4))-1;
    $pre_year=substr($date,0,4);
    if ($pre_month == 0) {
      $pre_month=12;
      $pre_year=intval(substr($date,0,4))-1;
    }
    $pre_date=$pre_year.sprintf('%02d',$pre_month);
  }
  
  if (in_array('all',$opts)) {
    $lines=Blog_cache::get_all($date);
    $logs=array();
    foreach ($lines as $line) $logs[]=explode(" ",$line,4);
    #uniq($logs);
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

  #if (!$logs) return "";

  foreach ($logs as $log) {
    list($page, $user,$date,$title)= $log;
    $url=qualifiedUrl($formatter->link_url(_urlencode($page)));

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

  # make pnut
  $pnut=$formatter->link_to("?action=blogchanges&amp;mode=$value&amp;date=$pre_date",'&laquo; '._("Previous"));
  return $bra.$items.$cat.$pnut;
}
?>
