<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a print action plugin for the MoniWiki
//
// $Id$
// vim:et:ts=2:

function do_print($formatter,$options) {
  global $DBInfo;
  $options['css_url']=$DBInfo->url_prefix."/css/print.css";
  $formatter->send_header("",$options);
  print "<h2>$options[page]</h2>";
  $formatter->send_page();
  return;
}

?>
