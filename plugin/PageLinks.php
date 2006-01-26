<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id$

function macro_PageLinks($formatter,$options="") {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $out="<ul>\n";
  $cache=new Cache_text("pagelinks");
  foreach ($pages as $page) {
    $out.="<li>".$formatter->link_tag($page,'',htmlspecialchars($page)).": ";
    $links=implode(' ',unserialize($cache->fetch($page)));
    $links=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$links);
    $out.=$links."</li>\n";
  }
  $out.="</ul>\n";
  return $out;
}

// vim:et:sts=4:
?>
