<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
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

  function get_daterule() {
    $date=date('Y-m');
    list($year,$month)=explode('-',$date);
    $mon=intval($month);
    $y=$year;
    $daterule.='(?='.$y.$month;
    for ($i=1;$i<3;$i++) {
      if (--$mon <= 0) {
        $mon=12;
        $y--;
      }
      $daterule.='|'.$y.sprintf("%02d",$mon);
    }
    $daterule.=')';
    #print $daterule;
    # (200402|200401|200312)
    return $daterule;
  }

  function get_categories() {
    global $DBInfo;

    if (!$DBInfo->hasPage($DBInfo->blog_category)) return array();
    $categories=array();

    $page=$DBInfo->getPage($DBInfo->blog_category);

    $raw=$page->get_raw_body();
    $temp= explode("\n",$raw);

    foreach ($temp as $line) {
      $line=str_replace('/','_2f',$line);
      if (preg_match('/^ \* ([^ :]+)(?=\s|$)/',$line,$match)) {
        $category=$match[1];
        if (!$categories[$category]) $categories[$category]=array();
      } else if ($category and preg_match('/^\s\s+\* ([^ :]+)(?=\s|$)/',$line,$match)) {
        $categories[$category][]=$match[1];
      }
    }
    return $categories;
  }

  function get_simple($blogs,$options) {
    global $DBInfo;

    $daterule=$options['date'];
    if (!$daterule)
      $daterule=Blog_cache::get_daterule();

    $rule="/^($daterule\d*)".'_2e('.join('|',$blogs).')$/';
    $logs=array();

    $handle = @opendir($DBInfo->cache_dir."/blogchanges");
    if (!$handle) return array();

    while (($file = readdir($handle)) !== false) {
      $fname=$DBInfo->cache_dir."/blogchanges/".$file;
      if (is_dir($fname)) continue;
      $filelist[] = $file;
    }
    closedir($handle);

    rsort($filelist);

    while ((list($key, $file) = each ($filelist))) {
      #echo "<b>$file</b><br>";
      if (preg_match($rule,$file,$match)) {
        $fname=$DBInfo->cache_dir."/blogchanges/".$file;
        $datestamp=$match[1];
        $blog=$match[2];
        $pagename=$DBInfo->keyToPagename($blog);

        $items=file($fname);
        foreach ($items as $line) {
          list($author,$datestamp,$dummy)=explode(' ',$line);
          #$datestamp[10]=' ';
          #$timestamp= strtotime($datestamp." GMT");
          #$datestamp= date("Ym",$timestamp);
          $logs[]=explode(' ',$pagename." ".rtrim($line),4);
        }
      }
    }
    return $logs;
  }

  function get_rc_blogs($date,$pages=array()) {
    global $DBInfo;
    $blogs=array();
    $handle = @opendir($DBInfo->cache_dir."/blogchanges");
    if (!$handle) return array();

    if (!$date)
      $date=Blog_cache::get_daterule();

    if ($pages) $pagerule=implode('|',$pages);
    else $pagerule='.*';
    $rule="/^($date\d*)_2e($pagerule)$/";
    while ($file = readdir($handle)) {
      $fname=$DBInfo->cache_dir."/blogchanges/".$file;
      if (is_dir($fname)) continue;
      if (preg_match($rule,$file,$match))
        $blogs[]=$match[2];
    }

    return array_unique($blogs);
  }


  function get_summary($blogs,$options) {
    global $DBInfo;

    if (!$blogs) return array();
    $date=$options['date'];

    if ($date) {
      // make a date pattern to grep blog entries
      $check=strlen($date);
      if (($check < 4) or !preg_match('/^\d+/',$date)) $date=date('Y\-m');
      else {
        if ($check==6) $date=substr($date,0,4).'\-'.substr($date,4);
        else if ($check==8) $date=substr($date,0,4).'\-'.substr($date,4,2).'\-'.substr($date,6);
        else if ($check!=4) $date=date('Y\-m');
      }
      #print $date;
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
  if ($a[2] == $b[2]) return 0;
#    return strcmp($a[3],$b[3]);
  return ($a[2] > $b[2]) ? -1:1;
}

function do_BlogChanges($formatter,$options='') {
  if (!$options['date']) $options['date']=date('Ym');
  $options['action']=1;
  $options['summary']=1;
  $options['simple']=1;

  $changes=macro_BlogChanges($formatter,'all,'.$options['mode'],$options);
  $formatter->send_header('',$options);
  if ($options['category'])
    $formatter->send_title($options['category'],'',$options);
  else
    $formatter->send_title(_("BlogChanges"),'',$options);
  print '<div id="wikiContent">';
  print $changes;
  print '</div>';
  $formatter->send_footer('',$options);
  return;
}

function macro_BlogChanges($formatter,$value,$options=array()) {
  global $DBInfo;

  if (empty($options)) $options=array();
  if ($_GET['date'])
    $options['date']=$date=$_GET['date'];
  else
    $date=$options['date'];

  preg_match('/^(?(?=\')\'([^\']+)\'|\"([^\"]+)\")?,?(\d+)?(\s*,?\s*.*)?$/',
    $value,$match);

  $category_pages=array();
  if ($match[2] or $options['category']) {
    $options['category']=$options['category'] ? $options['category']:$match[2];
    if ($DBInfo->blog_category) {
      $categories=Blog_cache::get_categories();
      if ($categories[$options['category']])
        $category_pages=$categories[$options['category']];
    }
  }
  $opts=explode(',',$match[4]);
  $opts=array_merge($opts,array_keys($options));
  if ($match[3]) {
    $options['limit']=$limit=$match[3];
  } else {
    if ($date) $limit=30;
    else $limit=10;
  }

  # check error and set default value
  # default: show BlogChages monthly

  #print_r($category_pages);
  if (in_array('all',$opts) or $category_pages) {
    if (in_array('summary',$opts))
      $blogs=Blog_cache::get_rc_blogs($date,$category_pages);
    else
      $blogs=Blog_cache::get_blogs();
  } else
    $blogs=array($DBInfo->pageToKeyname($formatter->page->name));

  if (in_array('summary',$opts))
    $logs=Blog_cache::get_summary($blogs,$options);
  else
    $logs=Blog_cache::get_simple($blogs,$options);
  usort($logs,'BlogCompare');

  if (!$options['date'] or !preg_match('/^\d{4}-?\d{2}$/',$options['date']))
    $date=date('Ym');

  $year=substr($date,0,4);
  $month=substr($date,4,2);
  $day=substr($date,6,2);

  if (strlen($date)==8) {
    $pre_date= date('Ymd',mktime(0,0,0,$month,intval($day) - 1,$year));
  } else if (strlen($date)==6) {
    $pre_date= date('Ym',mktime(0,0,0,intval($month) - 1,1,$year));
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
  if (in_array('summary',$opts))
  $template='$out="$bullet<div class=\"blog-title\"><a name=\"$tag\"></a><a href=\"$url#$tag\">$title</a> <a class=\"puple\" href=\"#$tag\">'.addslashes($formatter->purple_icon).'</a></div><span class=\"blog-user\">';
  if (!in_array('nouser',$opts))
    $template.='by $user ';
  if (!in_array('nodate',$opts))
    $template.='@ $date ';

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
    $datetag='';

    $url=qualifiedUrl($formatter->link_url(_urlencode($page)));
    if (!$opts['nouser'] and $user and $DBInfo->hasPage($user))
      $user=$formatter->link_tag(_rawurlencode($user),"",$user);

    if (!$title) continue;

    $date[10]=' ';
    $time=strtotime($date." GMT");

    $date= date("m-d [h:i a]",$time);
    if ($summary) {
      $anchor= date('Ymd',$time);
      if ($date_anchor != $anchor) {
        $datetag= "<div class='blog-date'>".date('M d, Y',$time)." <a name='$anchor'></a><a class='purple' href='#$anchor'>$formatter->purple_icon</a></div>";
        $date_anchor= $anchor;
      }
      $p=new WikiPage($page);
      $f=new Formatter($p);
      $summary=str_replace('\}}}','}}}',$summary);
      ob_start();
      $f->send_page($summary);
      $summary=ob_get_contents();
      ob_end_clean();
    }

    eval($template);
    $items.=$datetag.$out;
    if ($limit-- < 0) break;
  }
  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));

  # make pnut
  $action="date=$pre_date";
  if ($options['action'])
    $action='action=blogchanges&amp;'.$action;
  if ($options['category'])
    $action.='&amp;category='.$options['category'];
  if ($options['mode'])
    $action.='&amp;mode='.$options['mode'];
  $pnut="<div class='blog-action'>".$formatter->link_to('?'.$action,'&laquo; '._("Previous"))."</div>";
  return $bra.$items.$cat.$pnut;
}
// vim:et:sts=2:
?>
