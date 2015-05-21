<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a atom action plugin for the MoniWiki
//
// Date: 2006-08-15
// Name: Atom feeder
// Description: Atom Plugin
// URL: MoniWiki:AtomPlugin
// Version: $Revision: 1.3 $
// License: GPL
//
// $Id: atom.php,v 1.3 2010/07/09 11:03:27 wkpark Exp $
// $orig Id: rss_rc.php,v 1.12 2005/09/13 09:10:52 wkpark Exp $

function do_atom($formatter,$options) {
  global $DBInfo;
  global $_release;
  define('ATOM_DEFAULT_DAYS',7);

  $days=$DBInfo->rc_days ? $DBInfo->rc_days:ATOM_DEFAULT_DAYS;
  $options['quick']=1;
  if ($options['c']) $options['items']=$options['c'];
  $lines= $DBInfo->editlog_raw_lines($days,$options);
    
  $time_current= time();
#  $secs_per_day= 60*60*24;
#  $days_to_show= 30;
#  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  $URL=qualifiedURL($formatter->prefix);
  $img_url=qualifiedURL($DBInfo->logo_img);

  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));
  $surl=qualifiedUrl($formatter->link_url($options['page'].'?action=atom'));
  $channel=<<<CHANNEL
  <title>$DBInfo->sitename</title>
  <link href="$url"></link>
  <link rel="self" type="application/atom+xml" href="$surl" />
  <subtitle>RecentChanges at $DBInfo->sitename</subtitle>
  <generator version="$_release">MoniWiki Atom feeder</generator>\n
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
    $user_uri='';
    if ($DBInfo->hasPage($user)) {
      $user_uri= $formatter->link_url(_rawurlencode($user),"",$user);
      $user_uri='<uri>'.$user_uri.'</uri>';
    }
    $log= _stripslashes($parts[5]);
    $act= rtrim($parts[6]);

    $url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name)));
    $diff_url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name),'?action=diff'));

    $extra="<br /><a href='$diff_url'>"._("show changes")."</a>\n";
    $content='';
    if (!$DBInfo->hasPage($page_name)) {
      $status='deleted';
      $content="<content type='html'><a href='$url'>$page_name</a> is deleted</content>\n";
    } else {
      $status='updated';
      if ($options['diffs']) {
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
        $content="  <content type='xhtml'><div xmlns='http://www.w3.org/1999/xhtml'>$html</content>\n";
      } else if ($log) {
        $html=str_replace('&','&amp;',$log);
        $content="<content type='text'>".$html."</content>\n";
      } else {
        $content="<content type='text'>updated</content>\n";
      }
    }
    $zone = '+00:00';
    $date = gmdate("Y-m-d\TH:i:s",$ed_time).$zone;
    if (!isset($updated)) $updated=$date;
    #$datetag = gmdate("YmdHis",$ed_time);

    $valid_page_name=str_replace('&','&amp;',$page_name);
    $items.="<entry>\n";
    $items.="  <title>$valid_page_name</title>\n";
    $items.="  <link href='$url'></link>\n";
    $items.='  '.$content;
    $items.="  <author><name>$user</name>$user_uri</author>\n";
    $items.="  <updated>$date</updated>\n";
    $items.="  <contributor><name>$user</name>$user_uri</contributor>\n";
    $items.="</entry>\n";
  }
  $updated="  <updated>$updated</updated>\n";

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
<!--<?xml-stylesheet href="$DBInfo->url_prefix/css/_feed.css" type="text/css"?>-->
<feed xmlns="http://www.w3.org/2005/Atom">
<!--
    Add "diffs=1" to add change diffs to the description of each items.
    Add "oe=utf-8" to convert the charset of this rss to UTF-8.
-->\n
HEAD;
  header("Content-Type: application/xml");
  if ($new) print $head.$new;
  else print $head.$channel.$updated.$items.$form;
  print "</feed>\n";
}
?>
