<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a WantedPages macro plugin for the MoniWiki
//
// $Id$

function macro_WantedPages($formatter,$value='') {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $pagelinks=$formatter->pagelinks; // save

  $cache=new Cache_text("pagelinks");

  foreach ($pages as $page) {
    $p= new WikiPage($page);
    $f= new Formatter($p);
    $links=$f->get_pagelinks();
    if ($links) {
      $lns=&$links;
      foreach($lns as $link) {
        if (!$link or $DBInfo->hasPage($link)) continue;
        if ($link and !$wants[$link])
          $wants[$link]="[\"$page\"]";
        else $wants[$link].=" [\"$page\"]";
      }
    }
  }
  if (!count($wants)) return '';

  asort($wants);
  $out="<ul>\n";
  while (list($name,$owns) = each($wants)) {
    $owns=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$owns);
    $out.="<li>".$formatter->link_repl($name,htmlspecialchars($name)). ": $owns</li>";
  }
  $out.="</ul>\n";
  $formatter->pagelinks = $pagelinks; // restore

  return $out;
}

// vim:et:sts=2:
?>
