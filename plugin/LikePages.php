<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a LikePages plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function do_LikePages($formatter,$options) {

  $opts['metawiki']=$options['metawiki'];
  $out= macro_LikePages($formatter,$options['page'],$opts);
  
  $title = $opts['msg'];
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print $opts['extra'];
  print $out;
  print $opts['extra'];
  $formatter->send_footer("",$options);
}

function macro_LikePages($formatter="",$args="",&$opts) {
  global $DBInfo;

  $pname=_preg_escape($args);

  $metawiki=$opts['metawiki'];

  if (strlen($pname) < 3) {
    $opts['msg'] = _('Use more specific text');
    return '';
  }

  $s_re="^[A-Z][a-z0-9]+";
  $e_re="[A-Z][a-z0-9]+$";

  $count=preg_match("/(".$s_re.")/",$pname,$match);
  if ($count) {
    $start=$match[1];
    $s_len=strlen($start);
  }
  $count=preg_match("/(".$e_re.")/",$pname,$match);
  if ($count) {
    $end=$match[1];
    $e_len=strlen($end);
  }

  if (!$start && !$end) {
    preg_match("/^(.{2,4})/",$args,$match);
    $s_len=strlen($match[1]);
    $start=_preg_escape($match[1]);
  }

  if (!$end) {
    $end=substr($args,$s_len);
    preg_match("/(.{2,6})$/",$end,$match);
    $end=$match[1];
    $e_len=strlen($end);
    if ($e_len < 2) $end="";
    else $end=_preg_escape($end);
  }

  $starts=array();
  $ends=array();
  $likes=array();

  if (!$metawiki) {
    $pages = $DBInfo->getPageLists();
  } else {
    if (!$end) $needle=$start;
    else $needle="$start|$end";
    $pages = $DBInfo->metadb->getLikePages($needle);
  }
  
  if ($start) {
    foreach ($pages as $page) {
      preg_match("/^$start/",$page,$matches);
      if ($matches)
        $starts[$page]=1;
    }
  }

  if ($end) {
    foreach ($pages as $page) {
      preg_match("/$end$/",$page,$matches);
      if ($matches)
        $ends[$page]=1;
    }
  }

  if ($start || $end) {
    if (!$end) $similar_re=$start;
    else $similar_re="$start|$end";

    foreach ($pages as $page) {
      preg_match("/($similar_re)/i",$page,$matches);
      if ($matches && !$starts[$page] && !$ends[$page])
        $likes[$page]=1;
    }
  }

  $idx=1;
  $hits=0;
  $out="";
  if ($likes) {
    ksort($likes);

    $out.="<h3>"._("These pages share a similar word...")."</h3>";
    $out.="<ol>\n";
    while (list($pagename,$i) = each($likes)) {
      $pageurl=_rawurlencode($pagename);
      $pagetext=htmlspecialchars(urldecode($pagename));
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagetext,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n";
    $hits=count($likes);
  }
  if ($starts || $ends) {
    ksort($starts);

    $out.="<h3>"._("These pages share an initial or final title word...")."</h3>";
    $out.="<table border='0' width='100%'><tr><td width='50%' valign='top'>\n<ol>\n";
    while (list($pagename,$i) = each($starts)) {
      $pageurl=_rawurlencode($pagename);
      $pagetext=htmlspecialchars(urldecode($pagename));
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagetext,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol></td>\n";

    ksort($ends);

    $out.="<td width='50%' valign='top'><ol>\n";
    while (list($pagename,$i) = each($ends)) {
      $pageurl=_rawurlencode($pagename);
      $pagetext=htmlspecialchars(urldecode($pagename));
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagetext,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n</td></tr></table>\n";
    $opts['extra']=_("If you can't find this page, ");
    $hits+=count($starts) + count($ends);
  }

  if (!$hits) {
    $out.="<h3>"._("No similar pages found")."</h3>";
    $opts['extra']=_("You are strongly recommened to find it in MetaWikis. ");
  }

  $opts['msg'] = sprintf(_("Like \"%s\""),$args);

  $tag=$formatter->link_to("?action=LikePages&amp;metawiki=1",_("Search all MetaWikis"));
  $opts['extra'].="$tag (Slow Slow)<br />";

  return $out;
}

?>
