<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample Page break macro for the MoniWiki
//
// Usage: [[PGBR]]
//  and add a CSS like following in your print.css:
// div.pagebreak {page-break-before: always}
//
// $Id: PGBR.php,v 1.1 2004/09/23 16:50:19 wkpark Exp $

function macro_PGBR($formatter,$value) {
  return "<div class='pagebreak'><br /></div>\n";
}
// vim:et:sts=2:
?>
