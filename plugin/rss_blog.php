<?php
// Copyright 2003 by Jang,Dong-Su <jdongsu at pyunji.net>
//                   Won-kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rss_blog action plugin for the MoniWiki
//
// $Id: rss_blog.php,v 1.23 2006/10/31 01:54:17 pyrasis Exp $

include_once("plugin/BlogChanges.php");

function do_rss_blog($formatter,$options) {
  global $DBInfo;

#  if (!$options['date'] or !preg_match('/^\d+$/',$date)) $date=date('Ym');
#  else $date=$options['date'];
  $date=$options['date'];

  $category_pages=array();
  if ($options['category'] and $DBInfo->blog_category) {
    $categories=Blog_cache::get_categories();
    if ($categories[$options['category']])
      $category_pages=$categories[$options['category']];
    $title=$options['category'];
  } else
    $title=_("Blog Changes");

  if ($options['all'] or $options['category']) {
    # check error and set default value
    $blog_rss=new Cache_text('blogrss');

#    $blog_mtime=filemtime($DBInfo->cache_dir."/blog");
#    if ($blog_rss->exists($date'.xml') and ($blog_rss->mtime($date.'.xml') > $blog_mtime)) {
#      print $blog_rss->fetch($date.'.xml');
#      return;
#    }

    $blogs=Blog_cache::get_rc_blogs($date,$category_pages);
    $logs=Blog_cache::get_summary($blogs,$date);
    $rss_name=$DBInfo->sitename.': '.$title;
  } else {
    $blogs=array($DBInfo->pageToKeyname($formatter->page->name));
    $logs=Blog_cache::get_summary($blogs,$date);
    $rss_name=$formatter->page->name;
  }
  usort($logs,'BlogCompare');
    
  /* generate <rss> ... </rss> */
  $rss = generate_rss($formatter, $rss_name, $logs);

  /* output encoding */
  if ($options['oe'] and (strtolower($options['oe']) != $DBInfo->charset)) {
    $charset=$options['oe'];
    if (function_exists('iconv')) {
      $out=iconv($DBInfo->charset,$charset,$rss);
      if (!$out) {
        $out= &$rss;
        $charset=$DBInfo->charset;
      }
    }
  } else $charset=$DBInfo->charset;


  /* emit output rss as text/xml */
  header("Content-Type: text/xml");

  print <<<XML
<?xml version="1.0" encoding="$charset"?>
<!--
    Add "oe=utf-8" to convert the charset of this rss to UTF-8.
-->
$rss
XML;
}

function generate_rss($formatter, $rss_name, $logs)
{
  global $DBInfo;

  $channel = generate_channel($formatter, $rss_name, $logs);
  $textInput = generate_textInput($formatter);

  return <<<RSS
<rss version="2.0"
     xmlns:admin="http://webns.net/mvcb/"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"

xmlns:creativeCommons="http://backend.userland.com/creativeCommonsRssModule"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
     xmlns:html="http://www.w3.org/1999/html">
$channel
$textInput
</rss>

RSS;
}

function generate_channel($formatter, $rss_name, $logs)
{
  global $DBInfo;

  $url=qualifiedUrl($formatter->link_url("BlogChanges"));
  $desc=sprintf(_("BlogChanges at %s"),$DBInfo->sitename);
  $image = generate_image($formatter);
  $items = generate_items($formatter, $logs);

  return <<<CHANNEL
<channel>
<title>$rss_name</title>
<link>$url</link>
<description>$desc</description>
$image
$items
</channel>

CHANNEL;
}

function generate_items($formatter, $logs)
{
  if (!$logs) return "";

  $items = "";
  foreach ($logs as $log) {
    $items .= generate_item($formatter, $log);
  }
  return $items;
}

function generate_item($formatter, $log)
{
  global $DBInfo;

  list($page,$user,$date,$title,$summary)= $log;

  if (!$title) return "";

  $url=qualifiedUrl($formatter->link_url(_urlencode($page)));

  /* perma link */
  $tag=md5($user.' '.$date.' '.$title);

  /* RFC 822 date format for RSS 2.0 */
  $date[10]=' ';
  $pubDate=gmdate('D, j M Y H:i:s T',strtotime(substr($date,0,19).' GMT'));

  /* description */
  if ($summary) {
      $p=new WikiPage($page);
      $f=new Formatter($p);
      $summary=str_replace('\}}}','}}}',$summary);
      ob_start();
      $f->send_page($summary,array('fixpath'=>1, 'nojavascript'=>1));
      $description='<description><![CDATA['.ob_get_contents().']]></description>';
      ob_end_clean();
  }

  /* convert special characters into HTML entities */
  $title = _html_escape($title);

  return <<<ITEM
<item>
  <title>$title</title>
  <link>$url#$tag</link>
  <guid isPermaLink="true">$url#$tag</guid>
  $description
  <pubDate>$pubDate</pubDate>
  <author>$user</author>
  <category domain="$url">$page</category>
  <comments><![CDATA[$url?action=blog&value=$tag#BlogComment]]></comments>
</item>

ITEM;
}

function generate_image($formatter)
{
  global $DBInfo;

  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));
  $img_url=qualifiedURL($DBInfo->logo_img);

  return <<<IMAGE
<image>
  <title>$DBInfo->sitename</title>
  <link>$url</link>
  <url>$img_url</url>
</image>

IMAGE;
}

function generate_textInput($formatter)
{
  $url=qualifiedUrl($formatter->link_url("FindPage"));

  return <<<FORM
<textInput>
  <title>Search</title>
  <link>$url</link>
  <name>goto</name>
</textInput>

FORM;
}
?>
