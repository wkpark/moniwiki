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
      #$lines=array_merge($lines,file($DBInfo->cache_dir."/blog/".$blog));
      $lines=array_merge($lines,file($DBInfo->cache_dir."/blog/".$blog));
    }
    return $lines;
  }
}

function do_rss_blog($formatter,$options) {
  global $DBInfo;

  if ($options['all']) {
    $lines=Blog_cache::get_all();
  } else {
    $raw_body=$formatter->page->get_raw_body();
    $temp= explode("\n",$raw_body);

    $lines=array();
    foreach ($temp as $line) {
      if (preg_match("/^{{{#!blog (.*)$/",$line,$match)) {
        $lines[$match[1]]=$options['page'];
      }
    }
  }
    
  $time_current= time();

  $URL=qualifiedURL($formatter->prefix);
  $img_url=qualifiedURL($DBInfo->logo_img);

  $head=<<<HEAD
<?xml version="1.0" encoding="$DBInfo->charset"?>
<!--<?xml-stylesheet type="text/xsl" href="/wiki/css/rss.xsl"?>-->
<rdf:RDF xmlns:wiki="http://purl.org/rss/1.0/modules/wiki/"
         xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:xlink="http://www.w3.org/1999/xlink"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns="http://purl.org/rss/1.0/">\n
HEAD;
  $url=qualifiedUrl($formatter->link_url("RecentChanges"));
  $channel=<<<CHANNEL
<channel rdf:about="$URL">
  <title>$DBInfo->sitename</title>
  <link>$url</link>
  <description>
    RecentChanges at $DBInfo->sitename
  </description>
  <image rdf:resource="$img_url"/>
  <items>
  <rdf:Seq>
CHANNEL;
  $items="";

#          print('<description>'."[$data] :".$chg["action"]." ".$chg["pageName"].$comment.'</description>'."\n");
#          print('</rdf:li>'."\n");
#        }

  $ratchet_day= FALSE;
  if (!$lines) $lines=array();

  foreach ($lines as $line=>$page) {
    $url=qualifiedUrl($formatter->prefix."/".$page);

    list($user,$date,$title)= explode(" ", $line,3);
    if (!$title) continue;
    #$tag=md5("#!blog ".$line);
    $tag=md5($line);
    #$tag=_rawurlencode(normalize($title));

    $channel.="    <rdf:li rdf:resource=\"$url#$tag\"/>\n";
    $items.="     <item rdf:about=\"$url#$tag\">\n";
    $items.="     <title>$title</title>\n";
    $items.="     <link>$url#$tag</link>\n";
    $items.="     <dc:date>$date</dc:date>\n";
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
  header("Content-Type: text/xml");
  print $head;
  print $channel;
  print $items;
  print $form;
  print "</rdf:RDF>";
}
?>
