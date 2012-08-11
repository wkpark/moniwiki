<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a TwinPages action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id: TwinPages.php,v 1.4 2010/08/06 15:34:55 wkpark Exp $

function do_twinpages($formatter,$options) {
  global $DBInfo;

  $formatter->send_header("",$options);
  $formatter->send_title(sprintf(_("TwinPages of %s"),$options['value']),"",$options);

  if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
  $twins=$DBInfo->metadb->getTwinPages($options['value'],2);
  if ($twins) {
    if (sizeof($twins) > 7) $twins[0]="\n".$twins[0];
    $twins=join("\n",$twins);
    $formatter->send_page(_("See [TwinPages]: ").$twins);
  } else 
    $formatter->send_page(_("No TwinPages found."));
  $formatter->send_footer("",$options);
  return;
}

?>
