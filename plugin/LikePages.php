<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a LikePages plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id: LikePages.php,v 1.11 2010/09/28 04:07:55 wkpark Exp $

function do_LikePages($formatter,$options) {

  $opts['metawiki']=!empty($options['metawiki']) ? $options['metawiki'] : '';
  $out= macro_LikePages($formatter,$options['page'],$opts);
  
  $title = $opts['msg'];
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print $opts['extra'];
  print $out;
  print $opts['extra'];
  $formatter->send_footer("",$options);
}

function macro_LikePages($formatter="", $value, &$opts) {
  global $DBInfo;

  $pname=_preg_escape($value);

  $metawiki=!empty($opts['metawiki']) ? $opts['metawiki'] : '';

  if (strlen($pname) < 3) {
    $opts['msg'] = _("Use more specific text");
    return '';
  }
  $opts['extra'] = '';

  $s_re="^[A-Z][A-Za-z0-9]+";
  $e_re="[A-Z][A-Za-z0-9]+$";

  $count=preg_match("/(".$s_re.")/",$pname,$match);
  $s_len = 0;
  if ($count) {
    $start=trim($match[1]);
    $s_len=strlen($start);
  }
  $count=preg_match("/(".$e_re.")/",$pname,$match);
  if ($count) {
    $end=$match[1];
    $e_len=strlen($end);
  }

  // for non ASCII codeset
  if (empty($start) or empty($end)) {
    if (preg_match('/[^A-Za-z0-9-_]/', $pname)) {
      $myname = preg_replace('/[\x00-\x2F\x3A-\x40\x5B-\x60\x7B-\x7F]/', ' ', $pname);
      $words = preg_split('/\s+/', $myname);
      if (empty($start))
        $start = $words[0];
      if (isset($words[1])) {
        if (empty($end))
          $end = $words[count($words) - 1];
      }
    }

    // try to remove suffix
    // "위키에서 글쓰기" => start=위키에서|위키에|위키
    if (!empty($start) and preg_match('/[\x{AC00}-\x{D7AF}]/u', $start)) {
	    $ws = preg_split('//u', $start, -1,  PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
      $nw = array();
      $nw[] = $start;
      for ($i = 2;  count($ws) > 2 and $i > 0; $i--) {
        array_pop($ws);
        $nw[] = implode('', $ws);
      }
      $start = implode('|', $nw);
    }

    if (!empty($end) and preg_match('/[\x{AC00}-\x{D7AF}]/u', $end)) {
	    $ws = preg_split('//u', $end, -1,  PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
      $nw = array();
      $last = array_splice($ws, -2);
      $last = implode('', $last);
      $nw[] = $last;
      $ws = array_reverse($ws);
      foreach ($ws as $w) {
        $last = $w.$last;
        $nw[] = $last;
      }
      $end = implode('|', $nw);
    }
  }

  if (empty($start)) {
    preg_match("/^(.{2,4})/u",$value,$match);
    $s_len=strlen($match[1]);
    $start=trim(_preg_escape($match[1]));
  }

  if (empty($end)) {
    $end=substr($value,$s_len);
    preg_match("/(.{2,6})$/u",$end,$match);
    $end=isset($match[1]) ? $match[1] : '';
    $e_len=strlen($end);
    if ($e_len < 2) $end="";
    else $end=_preg_escape($end);
  }

  $starts=array();
  $ends=array();
  $likes=array();

  if (empty($metawiki)) {
    if (!$end) $needle=$start;
    else $needle="$start|$end";
    $indexer = $DBInfo->lazyLoad('titleindexer');
    $pages = $indexer->getLikePages($needle);

    // get aliases
    if (empty($DBInfo->alias)) $DBInfo->initAlias();
    $alias = $DBInfo->alias->getAllPages();

    $pages = array_merge($pages, $alias);
  } else {
    if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
    if (empty($DBInfo->metadb)) {
      $opts['msg'] = _("No metadb found");
      return '';
    }
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

  if (!empty($DBInfo->use_similar_text)) {
    $len = strlen($value);
    $ii = 0;
    foreach ($pages as $page) {
      $ii++;
      $match = similar_text($value, $page) / min($len, strlen($page));
      if ($match > 0.9 && empty($starts[$page]) && empty($ends[$page]))
        $likes[$page] = $match;
    }
  } else if ($start || $end) {
    if ($start and $end) $similar_re="$start|$end";
    else if ($start) $similar_re=$start;
    else $similar_re=$end;
    if (!empty($words))
      $similar_re.='|'.implode('|', $words);

    foreach ($pages as $page) {
      preg_match("/($similar_re)/i",$page,$matches);
      if ($matches && empty($starts[$page]) && empty($ends[$page]))
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
    foreach ($likes as $pagename => $i) {
      $pageurl=_rawurlencode($pagename);
      $pagetext=_html_escape(urldecode($pagename));
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
      $pagetext=_html_escape(urldecode($pagename));
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagetext,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol></td>\n";

    ksort($ends);

    $out.="<td width='50%' valign='top'><ol>\n";
    while (list($pagename,$i) = each($ends)) {
      $pageurl=_rawurlencode($pagename);
      $pagetext=_html_escape(urldecode($pagename));
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagetext,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n</td></tr></table>\n";
    $hits+=count($starts) + count($ends);
  }

  if (empty($hits)) {
    $out.="<h3>"._("No similar pages found")."</h3>";
  }

  $opts['msg'] = sprintf(_("Like \"%s\""),$value);

  while (empty($metawiki)) {
    if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
    if (empty($DBInfo->metadb) or empty($DBInfo->shared_metadb)) break;
    $opts['extra']=_("If you can't find this page, ");
    if (empty($hits) and empty($metawiki) and !empty($DBInfo->metadb))
      $opts['extra']=_("You are strongly recommened to find it in MetaWikis. ");
    $tag=$formatter->link_to("?action=LikePages&amp;metawiki=1",_("Search all MetaWikis"));
    $opts['extra'].="$tag ("._("Slow Slow").")<br />";
    break;
  }

  return $out;
}

?>
