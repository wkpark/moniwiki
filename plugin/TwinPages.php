<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a TwinPages action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function do_twinpages($formatter,$options) {
  global $DBInfo;

  $formatter->send_header("",$options);
  $formatter->send_title(sprintf(_("TwinPages of %s"),$options['value']),"",$options);

  $twins=$DBInfo->metadb->getTwinPages($options['value'],1);
  if ($twins) {
    $twins=join("\n",$twins);
    $formatter->send_page(_("See TwinPages: ").$twins);
  } else 
    $formatter->send_page(_("No TwinPages found."));
  $formatter->send_footer("",$options);
  return;
}

?>
