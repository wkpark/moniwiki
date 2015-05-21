<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// rss_rc action plugin for the MoniWiki
//
// $Id: rss_rc.php,v 1.21 2010/09/11 13:39:22 wkpark Exp $

define('RSS_DEFAULT_DAYS',7);

function do_rss_rc($formatter,$options) {
  global $DBInfo;

  $days=!empty($DBInfo->rc_days) ? $DBInfo->rc_days:RSS_DEFAULT_DAYS;
  $options['quick']=1;
  if (!empty($options['c'])) $options['items']=$options['c'];
  $lines= $DBInfo->editlog_raw_lines($days,$options);

  if (!empty($DBInfo->rss_rc_options)) {
    $opts=$DBInfo->rss_rc_options;
    $opts=explode(',',$opts);
    foreach ($opts as $opt) {
      $options[$opt]=1; // FIXME
    }
  }

  // HTTP conditional get
  $mtime = $DBInfo->mtime();
  $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);

  // make etag based on some options and mtime.
  $check_opts = array('quick', 'items', 'oe', 'diff', 'raw', 'nomsg', 'summary');
  $check = array();
  foreach ($check_opts as $c) {
    if (isset($options[$c])) $check[$c] = $options[$c];
  }

  $etag = md5($mtime . $DBInfo->logo_img . serialize($check));

  $headers = array();
  $headers[] = 'Pragma: cache';
  $maxage = 60*60*24*7;
  $headers[] = 'Cache-Control: private, max-age='.$maxage;
  $headers[] = 'Last-Modified: '.$lastmod;
  $headers[] = 'ETag: "'.$etag.'"';
  $need = http_need_cond_request($mtime, $lastmod, $etag);
  if (!$need)
    $headers[] = 'HTTP/1.0 304 Not Modified';
  foreach ($headers as $h)
    header($h);
  if (!$need) {
    @ob_end_clean();
    return;
  }
    
  $time_current= time();
#  $secs_per_day= 60*60*24;
#  $days_to_show= 30;
#  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

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
    $log= _stripslashes($parts[5]);
    $act= rtrim($parts[6]);

#    if ($ed_time < $time_cutoff)
#      break;

    $url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name)));
    $diff_url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name),'?action=diff'));

    $extra="<br /><a href='$diff_url'>"._("show changes")."</a>\n";
    if (!$DBInfo->hasPage($page_name)) {
      $status='deleted';
      $html="<a href='$url'>$page_name</a> is deleted\n";
    } else {
      $status='updated';
      if (!empty($options['diffs'])) {
        $p=new WikiPage($page_name);
        $f=new Formatter($p);
        $options['raw']=1;
        $options['nomsg']=1;
        $html=$f->macro_repl('Diff','',$options);
        if (!$html) {
          ob_start();
          $f->send_page('',array('fixpath'=>1));
          #$f->send_page('');
          $html=ob_get_contents();
          ob_end_clean();
          $extra='';
        }
    	$html=str_replace(']','&#93;',$html);
        $html="<![CDATA[".$html.$extra."]]>";
        #$html=strtr($html.$extra,array('&'=>'&amp;','<'=>'&lt;'));
      } else if (!empty($options['summary'])) {
        $p=new WikiPage($page_name);
        $f=new Formatter($p);
        $f->section_edit=0;
        $f->sister_on=0;
        $f->perma_icon='';

        $options['nomsg']=1;
        $b= $p->_get_raw_body();
        $chunks= preg_split('/\n#{4,}/',$b); # summary breaker is ####
        ob_start();
        if ($chunks) $f->send_page($chunks[0],array('fixpath'=>1));
        else $f->send_page('',array('fixpath'=>1));
        #$f->send_page('');
        $html=ob_get_contents();
        ob_end_clean();
        $chunks= preg_split('/<!-- break -->/',$html); # <!-- break -->
        if ($chunks[0]) $html=$chunks[0];

    	$html=str_replace(']','&#93;',$html);
        $html="<![CDATA[".$html."]]>";
      } else {
    	$html=str_replace('&','&amp;',$log);
      }
    }
    $zone = "+00:00";
    $date = gmdate("Y-m-d\TH:i:s",$ed_time).$zone;
    #$datetag = gmdate("YmdHis",$ed_time);

    $channel.="<rdf:li rdf:resource=\"$url\"></rdf:li>\n";

    $valid_page_name=preg_replace('/&(?!#?\w+;)/', '&amp;', _html_escape($page_name));
    $items.="<item rdf:about=\"$url\">\n";
    $items.="  <title>$valid_page_name</title>\n";
    $items.="  <link>$url</link>\n";
    $items.="  <description>$html</description>\n";
    $items.="  <dc:date>$date</dc:date>\n";
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
  if (!empty($options['oe']) and (strtolower($options['oe']) != $DBInfo->charset)) {
    $charset=$options['oe'];
    if (function_exists('iconv')) {
      $out=$head.$channel.$items.$form;
      $new=iconv($DBInfo->charset,$charset,$out);
      if (!$new) $charset=$DBInfo->charset;
    }
  } else $charset=$DBInfo->charset;

  $head=<<<HEAD
<?xml version="1.0" encoding="$charset"?>
<?xml-stylesheet href="$DBInfo->url_prefix/css/_feed.css" type="text/css"?>
<rdf:RDF xmlns="http://purl.org/rss/1.0/"
	xmlns:wiki="http://purl.org/rss/1.0/modules/wiki/"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:dc="http://purl.org/dc/elements/1.1/">
<!--
    Add "diffs=1" to add change diffs to the description of each items.
    Add "summary=1" to add summary to the description of each items.
    Add "oe=utf-8" to convert the charset of this rss to UTF-8.
-->\n
HEAD;
  header("Content-Type: text/xml");
  if ($new) print $head.$new;
  else print $head.$channel.$items.$form;
  print "</rdf:RDF>\n";
}

// vim:et:sts=2:sw=2
?>
