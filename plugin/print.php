<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a print action plugin for the MoniWiki
//
// $Id: print.php,v 1.9 2010/10/05 22:28:54 wkpark Exp $

function do_print($formatter,$options) {
  global $DBInfo;
  $options['css_url']=$DBInfo->url_prefix."/css/print.css";

  $formatter->nonexists='always';

  $dum = false;
  $formatter->pi = $formatter->page->get_instructions($dum);
  $title = $formatter->pi['#title'];

  $formatter->send_header("",$options);
  kbd_handler();
  print "<div id='printHeader'>";
  print "<h2>$title</h2>";
  print "</div>";
  print "<div id='wikiContent'>";
  $formatter->external_on=1;
  $formatter->send_page('',array('fixpath'=>1));
  print "</div></div>";
  print "<div id='printFooter'>";
  print sprintf(_("Retrieved from %s"),
    qualifiedUrl($formatter->link_url($formatter->page->name))).'<br/>';
  if ($mtime=$formatter->page->mtime()) {
    $lastedit=date("Y-m-d",$mtime);
    $lasttime=date("H:i:s",$mtime);
    print sprintf(_("last modified %s %s"),$lastedit,$lasttime);
  }
  print "</div></body></html>";
  return;
}

// vim:et:sts=2:
?>
