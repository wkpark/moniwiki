<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BlogChanges action plugin for the MoniWiki
//
// $Id$

class Blog_cache {
  function get_blogs() {
    global $DBInfo;

    $blogs=array();
    $handle = @opendir($DBInfo->cache_dir."/blog");
    if (!$handle) return array();

    while ($file = readdir($handle)) {
      if (is_dir($DBInfo->cache_dir."/blog/".$file)) continue;
      $blogs[] = $file;
    }
    closedir($handle);
    return $blogs;
  }

  function get_simple($blogs,$date) {
    global $DBInfo;
    $rule="/^($date\d*)".'_2e('.join('|',$blogs).')$/';
    $logs=array();
    $handle = @opendir($DBInfo->cache_dir."/blogchanges");
    if (!$handle) return array();

    while ($file = readdir($handle)) {
      $fname=$DBInfo->cache_dir."/blogchanges/".$file;
      if (is_dir($fname)) continue;
      if (preg_match($rule,$file,$match)) {
        $datestamp=$match[1];
        $blog=$match[2];
        $pagename=$DBInfo->keyToPagename($blog);

        $items=file($fname);
        foreach ($items as $line) {
          list($author,$datestamp,$dummy)=explode(' ',$line);
          $datestamp[10]=' ';
          $timestamp= strtotime($datestamp." GMT");
          $datestamp= date("Ymd",$timestamp);
          $logs[]=explode(' ',$pagename." ".rtrim($line),4);
        }
      }
    }
    return $logs;
  }

  function get_rc_blogs($date) {
    global $DBInfo;
    $all=Blog_cache::get_blogs();
    $rule="/^($date\d*)".'_2e('.join('|',$all).')$/';
    $blogs=array();
    $handle = @opendir($DBInfo->cache_dir."/blogchanges");
    if (!$handle) return array();

    while ($file = readdir($handle)) {
      $fname=$DBInfo->cache_dir."/blogchanges/".$file;
      if (is_dir($fname)) continue;
      if (preg_match($rule,$file,$match))
        $blogs[]=$match[2];
    }
    return array_unique($blogs);
  }

  function get_summary($blogs,$date) {
    global $DBInfo;

    if (!$blogs) return array();

    $check=strlen($date);
    if (($check < 4) or !preg_match('/^\d+/',$date)) $date=date('Y\-m');
    else {
      if ($check==6) $date=substr($date,0,4).'\-'.substr($date,4);
      else if ($check==8) $date=substr($date,0,4).'\-'.substr($date,4,2).'\-'.substr($date,6);
      else if ($check!=4) $date=date('Y\-m');
    }

    $entries=array();
    $logs=array();

    foreach ($blogs as $blog) {
      $pagename=$DBInfo->keyToPagename($blog);
      $page=$DBInfo->getPage($pagename);

      $raw=$page->get_raw_body();
      $temp= explode("\n",$raw);

      foreach ($temp as $line) {
        if (!$state) {
          if (preg_match("/^({{{)?#!blog\s([^ ]+\s($date"."[^ ]+)\s.*)$/",$line,$match)) {
            $entry=explode(" ",$pagename." ".$match[2],4);
            if ($match[1]) $endtag='}}}';
            $state=1;
          }
          continue;
        }
        if (preg_match("/^$endtag$/",$line)) {
          $state=0;
          $temp=explode("----\n",$summary,2);
          $entry[]=$temp[0];
          $entries[]=$entry;
          $summary='';
          continue;
        }
        $summary.=$line."\n";
      }
    }
    return $entries;
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

  $options['date']=$_GET['date'];

  # check error and set default value
  # default: show BlogChages monthly
  if (!$options['date'] or !preg_match('/^\d+$/',$options['date'])) $date=date('Ym');
  else $date=$options['date'];

  $year=substr($date,0,4);
  $month=substr($date,4,2);
  $day=substr($date,6,2);

  if (strlen($date)==8) {
    $pre_date= date('Ymd',mktime(0,0,0,$month,intval($day) - 1,$year));
  } else if (strlen($date)==6) {
    $pre_date= date('Ym',mktime(0,0,0,intval($month) - 1,1,$year));
  }

  if (in_array('all',$opts)) {
    if (in_array('summary',$opts))
      $blogs=Blog_cache::get_rc_blogs($date);
    else
      $blogs=Blog_cache::get_blogs();
  } else
    $blogs=array($DBInfo->pageToKeyname($formatter->page->name));

  if (in_array('summary',$opts))
    $logs=Blog_cache::get_summary($blogs,$date);
  else
    $logs=Blog_cache::get_simple($blogs,$date);
  usort($logs,'BlogCompare');

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

  if (in_array('summary',$opts)) {
    $template.='</span><div class=\"blog-summary\">$summary</div>$sep\n";';
  }
  else
    $template.='</span>$sep\n";';
    
  $time_current= time();
  $items="";

  foreach ($logs as $log) {
    list($page, $user,$date,$title,$summary)= $log;
    $tag=md5($user." ".$date." ".$title);

    $url=qualifiedUrl($formatter->link_url(_urlencode($page)));
    if (!$opts['nouser'] and $user and $DBInfo->hasPage($user))
      $user=$formatter->link_tag(_rawurlencode($user),"",$user);

    if (!$title) continue;

    $date[10]=' ';
    $time=strtotime($date." GMT");
    $date= date("m-d [h:i a]",$time);
    if ($summary) {
      $p=new WikiPage($page);
      $f=new Formatter($p);
      $summary=str_replace('\}}}','}}}',$summary);
      ob_start();
      $f->send_page($summary);
      $summary=ob_get_contents();
      ob_end_clean();
    }

    eval($template);
    $items.=$out;
  }
  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));

  # make pnut
  $pnut="<div class='blog-action'>".$formatter->link_to("?date=$pre_date",'&laquo; '._("Previous"))."</div>";
  #$pnut=$formatter->link_to("?action=blogchanges&amp;mode=$value&amp;date=$pre_date",'&laquo; '._("Previous"));
  return $bra.$items.$cat.$pnut;
}
?>
