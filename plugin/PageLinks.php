<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id: PageLinks.php,v 1.3 2010/09/07 12:11:49 wkpark Exp $

function macro_PageLinks($formatter,$options="") {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();
  $pagelinks=$formatter->pagelinks; // save
  $save=$formatter->sister_on;
  $formatter->sister_on=0;

  $out="<ul>\n";
  $cache=new Cache_text("pagelinks");
  foreach ($pages as $page) {
    $lnks=$cache->fetch($page);
    if ($lnks !== false) {
        $out.="<li>".$formatter->link_tag($page,'',_html_escape($page)).": ";
        $links=implode(' ',$lnks);
        $links = preg_replace_callback("/(".$formatter->wordrule.")/",
            array(&$formatter, 'link_repl'), $links);
        $out.=$links."</li>\n";
    }
  }
  $out.="</ul>\n";
  $formatter->pagelinks = $pagelinks; // restore
  $formatter->sister_on=$save;
  return $out;
}

// vim:et:sts=4:
?>
