<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a moniedit action plugin for the MoniWiki
//
// $Id$

function do_moniedit($formatter,$options) {
  header("Content-Type: application/x-moniedit");
  #header("application/x-bat");
  header("Pragma: no-cache");
  print $options['page']." ".qualifiedUrl($formatter->prefix);
  return;
}

?>
