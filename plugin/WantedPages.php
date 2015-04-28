<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a WantedPages macro plugin for the MoniWiki
//
// $Id: WantedPages.php,v 1.7 2010/10/05 22:28:54 wkpark Exp $

function macro_WantedPages($formatter,$value='') {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $pagelinks=$formatter->pagelinks; // save
  $save=$formatter->sister_on;
  $formatter->sister_on=0;

  $cache=new Cache_text("pagelinks");

  foreach ($pages as $page) {
    $dum='';
    $p= new WikiPage($page);
    $f= new Formatter($p);
    $pi=$f->page->get_instructions($dum);
    if (!in_array($pi['#format'], array('wiki', 'monimarkup'))) continue;
    $links=$f->get_pagelinks();
    if ($links) {
      $lns=&$links;
      foreach($lns as $link) {
        if (!$link or $DBInfo->hasPage($link)) continue;
        if ($link and !$wants[$link])
          $wants[$link]="[\"$page\"]";
        else $wants[$link].=" [\"".$page."\"]";
      }
    }
  }
  $formatter->pagelinks=$pagelinks; // save
  $formatter->sister_on=$save;
  if (!count($wants)) return '';
  $pagelinks=$formatter->pagelinks; // save
  $formatter->sister_on=0;

  asort($wants);
  $out="<ul>\n";
  while (list($name,$owns) = each($wants)) {
    $owns=str_replace('<','&lt;',$owns);
    $nowns = preg_replace_callback("/(".$formatter->wordrule.")/",
      array(&$formatter, 'link_repl'), $owns);
    $out.="<li>".$formatter->link_repl($name,_html_escape($name)). ": $nowns</li>";
  }
  $out.="</ul>\n";
  $formatter->sister_on=$save;
  $formatter->pagelinks=$pagelinks; // save

  return $out;
}

// vim:et:sts=2:
?>
