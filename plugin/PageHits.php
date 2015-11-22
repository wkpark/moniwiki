<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a PageHits macro plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2013-08-15
// Modified: 2015-11-22
// Name: PageHits plugin
// Description: PageHits plugin
// URL: MoniWiki:PageHitsPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=PageHits [[PageHits]]
//
// $Id: PageHits.php,v 1.3 2010/08/23 09:15:23 wkpark Exp $

function macro_PageHits($formatter, $value = '', $params = array()) {
  global $DBInfo, $Config;

  if (empty($Config['use_counter']))
    return "[[PageHits is not activated. set \$use_counter=1; in the config.php]]";

  $perpage = !empty($Config['counter_per_page']) ?
    intval($Config['counter_per_page']) : 200;

  if (!empty($params['p'])) {
    $p = intval($params['p']);
  } else {
    $p = 0;
  }
  if ($p < 0) $p = 0;

  $hits = $DBInfo->counter->getPageHits($perpage, $p);

  if (!empty($value) and ($value=='reverse' or $value[0]=='r')) asort($hits);
  else arsort($hits);
  $out = '';
  while(list($name,$hit)=each($hits)) {
    if (!$hit) $hit=0;
    $name=$formatter->link_tag(_rawurlencode($name),"",_html_escape($name));
    $out.="<li>$name . . . . [$hit]</li>\n";
  }
  $start = $perpage * $p;
  if ($start > 0) $start = ' start="'.$start.'"';
  else $start = '';
  $out = "<ol$start>\n".$out."</ol>\n";

  $prev = '';
  $next = '';

  if ($p > 0)
    $prev = $formatter->link_tag($formatter->page->urlname,
      '?action=pagehits&amp;p='.($p - 1), _("&#171; Prev"));

  $p++;
  if (count($hits) >= 0) {
    $next = $formatter->link_tag($formatter->page->urlname,
      '?action=pagehits&amp;p='.$p, _("Next &#187;"));
  }
  return $out.$prev.' '.$next;
}

function do_pagehits($formatter, $params = array()) {
    $params['.title'] = _("Page Hits");
    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    echo macro_PageHits($formatter, '', $params);
    $args = false;
    $formatter->send_footer($args, $params);
}

// vim:et:sts=4:sw=4:
