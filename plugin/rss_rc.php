<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// rss_rc action plugin for the MoniWiki
//
// $Id$

function do_rss_rc($formatter,$options) {
  global $DBInfo;

  $lines= $DBInfo->editlog_raw_lines(2000,1);
    
  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  $URL=qualifiedURL($formatter->prefix);
  $img_url=qualifiedURL($DBInfo->logo_img);

  $url=qualifiedUrl($formatter->link_url("RecentChanges"));
  $channel=<<<CHANNEL
<channel rdf:about="$URL">
  <title>$DBInfo->sitename</title>
  <link>$url</link>
  <description>RecentChanges at $DBInfo->sitename</description>
  <image rdf:resource="$img_url"></image>
  <items>
  <rdf:Seq>\n
CHANNEL;
  $items="";

  $ratchet_day= FALSE;
  if (!$lines) $lines=array();
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $log= stripslashes($parts[5]);
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    $url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name)));
    $diff_url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name),'?action=diff'));

    $extra="<br /><a href='$diff_url'>"._("show changes")."</a>\n";
    if (!$DBInfo->hasPage($page_name)) {
      $status='deleted';
      $html="<a href='$url'>$page_name</a> is deleted\n";
    } else {
      $status='updated';
      if ($options['diffs']) {
        $p=new WikiPage($page_name);
        $f=new Formatter($p);
        $options['raw']=1;
        $options['nomsg']=1;
        $html=$f->get_diff('','','',$options);
        if (!$html) {
          ob_start();
          $f->send_page('',array('fixpath'=>1));
          #$f->send_page('');
          $html=ob_get_contents();
          ob_end_clean();
          $extra='';
        }
        $html="<![CDATA[".$html.$extra."]]>";
        #$html=strtr($html.$extra,array('&'=>'&amp;','<'=>'&lt;'));
      } else {
        if ($log) $html=$log;
      }
    }
    $zone = "+00:00";
    $date = gmdate("Y-m-d\TH:i:s",$ed_time).$zone;
    $datetag = gmdate("YmdHis",$ed_time);

    $channel.="<rdf:li rdf:resource=\"$url\"></rdf:li>\n";

    $items.="<item rdf:about=\"$url#$datetag\">\n";
    $items.="  <title>$page_name</title>\n";
    $items.="  <link>$url</link>\n";
    $items.="  <dc:date>$date</dc:date>\n";
    $items.="  <description>$html</description>\n";
    $items.="<dc:creator>$user</dc:creator>\n";
    $items.="<dc:contributor>$user</dc:contributor>\n";
#    $items.="     <dc:contributor>\n     <rdf:Description>\n"
#          ."     <rdf:value>$user</rdf:value>\n"
#          ."     </rdf:Description>\n     </dc:contributor>\n";
    $items.="     <wiki:status>$status</wiki:status>\n";
    $items.="     <wiki:diff>$diff_url</wiki:diff>\n";
    $items.="</item>\n";
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
</image>\n
FOOT;

  $url=qualifiedUrl($formatter->link_url("FindPage"));
  $form=<<<FORM
<textinput>
<title>Search</title>
<link>$url</link>
<name>goto</name>
</textinput>\n
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
<rdf:RDF xmlns="http://purl.org/rss/1.0/"
	xmlns:wiki="http://purl.org/rss/1.0/modules/wiki/"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:dc="http://purl.org/dc/elements/1.1/">\n
<!--
    Add "diffs=1" to add change diffs to the description of each items.
    Add "oe=utf-8" to convert the charset of this rss to UTF-8.
-->
HEAD;
  header("Content-Type: text/xml");
  if ($new) print $head.$new;
  else print $head.$channel.$items.$form;
  print "</rdf:RDF>\n";
}
?>
