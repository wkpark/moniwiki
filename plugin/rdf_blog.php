<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rdf_blog action plugin for the MoniWiki
//
// from Id: rss_blog.php,v 1.11 2003/10/17 03:51:26 wkpark Exp
// $Id: rdf_blog.php,v 1.2 2005/10/09 05:52:52 iolo Exp $

include_once("plugin/BlogChanges.php");

function do_rdf_blog($formatter,$options) {
  global $DBInfo;

#  if (!$options['date'] or !preg_match('/^\d+$/',$date)) $date=date('Ym');
#  else $date=$options['date'];
  $date=$options['date'];

  if ($options['all']) {
    # check error and set default value
    $blog_rss=new Cache_text('blogrss');

#    $blog_mtime=filemtime($DBInfo->cache_dir."/blog");
#    if ($blog_rss->exists($date'.xml') and ($blog_rss->mtime($date.'.xml') > $blog_mtime)) {
#      print $blog_rss->fetch($date.'.xml');
#      return;
#    }

    $blogs=Blog_cache::get_rc_blogs($date);
    $logs=Blog_cache::get_summary($blogs,$date);
    $rss_name=$DBInfo->sitename.': '._("Blog Changes");
  } else {
    $blogs=array($DBInfo->pageToKeyname($formatter->page->name));
    $logs=Blog_cache::get_summary($blogs,$date);
    $rss_name=$formatter->page->name;
  }
  usort($logs,'BlogCompare');
    
  $time_current= time();

  $URL=qualifiedURL($formatter->prefix);
  $img_url=qualifiedURL($DBInfo->logo_img);

  $url=qualifiedUrl($formatter->link_url("BlogChanges"));
  $desc=sprintf(_("BlogChanges at %s"),$DBInfo->sitename);
  $channel=<<<CHANNEL
<channel rdf:about="$URL">
  <title>$rss_name</title>
  <link>$url</link>
  <description>$desc</description>
  <image rdf:resource="$img_url"/>
  <items>
  <rdf:Seq>
CHANNEL;
  $items="";

#          print('<description>'."[$data] :".$chg["action"]." ".$chg["pageName"].$comment.'</description>'."\n");
#          print('</rdf:li>'."\n");
#        }

  $ratchet_day= FALSE;
  if (!$logs) $logs=array();

  foreach ($logs as $log) {
    #print_r($log);
    list($page, $user,$date,$title,$summary)= $log;
    $url=qualifiedUrl($formatter->link_url(_urlencode($page)));

    if (!$title) continue;
    #$tag=md5("#!blog ".$line);
    $tag=md5($user." ".$date." ".$title);
    #$tag=_rawurlencode(normalize($title));

    $channel.="    <rdf:li rdf:resource=\"$url#$tag\"/>\n";
    $items.="     <item rdf:about=\"$url#$tag\">\n";
    $items.="     <title>$title</title>\n";
    $items.="     <link>$url#$tag</link>\n";
    if ($summary) {
      $p=new WikiPage($page);
      $f=new Formatter($p);
      ob_start();
      #$f->send_page($summary);
      $f->send_page($summary,array('fixpath'=>1));
      #$summary=_html_escape(ob_get_contents());
      $summary='<![CDATA['.ob_get_contents().']]>';
      ob_end_clean();
      $items.="     <description>$summary</description>\n";
    }
    $items.="     <dc:date>$date+00:00</dc:date>\n";
    $items.="     <dc:contributor>\n<rdf:Description>\n"
          ."<rdf:value>$user</rdf:value>\n"
          ."</rdf:Description>\n</dc:contributor>\n";
    $items.="     </item>\n";

  }
  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));
  $channel.= <<<FOOT
    </rdf:Seq>
  </items>
</channel>
<image rdf:about="$img_url">
<title>$DBInfo->sitename</title>
<link>$url</link>
<url>$img_url</url>
</image>
FOOT;

  $url=qualifiedUrl($formatter->link_url("FindPage"));
  $form=<<<FORM
<textinput>
<title>Search</title>
<link>$url</link>
<name>goto</name>
</textinput>
FORM;

  $new="";
  if ($options['oe'] and (strtolower($options['oe']) != $DBInfo->charset)) {
    $charset=$options['oe'];
    if (function_exists('iconv')) {
      $out=$head.$channel.$items.$form;
      $new=iconv($DBInfo->charset,$charset,$out);
      if (!$new) $charset=$DBInfo->charset;
    }
  } else $charset=$DBInfo->charset;

  $head=<<<HEAD
<?xml version="1.0" encoding="$charset"?>
<rdf:RDF xmlns:wiki="http://purl.org/rss/1.0/modules/wiki/"
         xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:xlink="http://www.w3.org/1999/xlink"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns="http://purl.org/rss/1.0/">\n
<!--
    Add "oe=utf-8" to convert the charset of this rss to UTF-8.
-->
HEAD;

  header("Content-Type: text/xml");
  if ($new) print $head.$new;
  else print $head.$channel.$items.$form;

  #print $head;
  #print $channel;
  #print $items;
  #print $form;
  print "</rdf:RDF>";
}
?>
