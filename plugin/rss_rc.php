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
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    if (!$DBInfo->hasPage($page_name))
      $status='deleted';
    else
      $status='updated';
    #$zone = date("O");
    #$zone = $zone[0].$zone[1].$zone[2].":".$zone[3].$zone[4];
    #$date = date("Y-m-d\TH:i:s",$ed_time).$zone;

    $zone = "+00:00";
    $date = gmdate("Y-m-d\TH:i:s",$ed_time).$zone;

    $url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name)));
    $channel.="    <rdf:li rdf:resource=\"$url\"/>\n";

    $items.="     <item rdf:about=\"$url\">\n";
    $items.="     <title>$page_name</title>\n";
    $items.="     <link>$url</link>\n";
    $items.="     <dc:date>$date</dc:date>\n";
    $items.="     <dc:contributor>\n<rdf:Description>\n"
          ."<rdf:value>$user</rdf:value>\n"
          ."</rdf:Description>\n</dc:contributor>\n";
    $items.="     <wiki:status>$status</wiki:status>\n";
    $items.="     </item>\n";

#    $out.= "&nbsp;&nbsp;".$formatter->link_tag("$page_name");
#    if (! empty($DBInfo->changed_time_fmt))
#       $out.= date($DBInfo->changed_time_fmt, $ed_time);
#
#    if ($DBInfo->show_hosts) {
#      $out.= ' . . . . '; # traditional style
#      #$logs[$page_name].= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ';
#      if ($user)
#        $out.= $user;
#      else
#        $out.= $addr;
#    }
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
<!--<?xml-stylesheet type="text/xsl" href="/wiki/css/rss.xsl"?>-->
<rdf:RDF xmlns:wiki="http://purl.org/rss/1.0/modules/wiki/"
         xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:xlink="http://www.w3.org/1999/xlink"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns="http://purl.org/rss/1.0/">\n
HEAD;
  header("Content-Type: text/xml");
  if ($new) print $head.$new;
  else print $head.$channel.$items.$form;
  print "</rdf:RDF>";
}
?>
