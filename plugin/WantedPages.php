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
  $save=$formatter->sister_on;
  $formatter->sister_on=0;

  $cache=new Cache_text("pagelinks");

  foreach ($pages as $page) {
    $dum='';
    $p= new WikiPage($page);
    $f= new Formatter($p);
    $pi=$f->get_instructions($dum);
    if ($pi['#format']!='') continue;
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
    $nowns=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$owns);
    $out.="<li>".$formatter->link_repl($name,htmlspecialchars($name)). ": $nowns</li>";
  }
  $out.="</ul>\n";
  $formatter->sister_on=$save;
  $formatter->pagelinks=$pagelinks; // save

  return $out;
}

// vim:et:sts=2:
?>
