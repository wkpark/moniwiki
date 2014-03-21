<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a PageHits macro plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id: PageHits.php,v 1.3 2010/08/23 09:15:23 wkpark Exp $

function macro_PageHits($formatter="",$value) {
  global $DBInfo;

  if (!$DBInfo->use_counter) return "[[PageHits is not activated. set \$use_counter=1; in the config.php]]";

  $pages = $DBInfo->getPageLists();
  sort($pages);
  $hits= array();
  foreach ($pages as $page) {
    $hits[$page]=$DBInfo->counter->pageCounter($page);
  }

  if (!empty($value) and ($value=='reverse' or $value[0]=='r')) asort($hits);
  else arsort($hits);
  $out = '';
  while(list($name,$hit)=each($hits)) {
    if (!$hit) $hit=0;
    $name=$formatter->link_tag(_rawurlencode($name),"",_html_escape($name));
    $out.="<li>$name . . . . [$hit]</li>\n";
  }
  return "<ol>\n".$out."</ol>\n";
}

?>
