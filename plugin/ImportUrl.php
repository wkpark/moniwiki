<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id$
// vim:et:ts=2:

function do_ImportUrl($formatter,$options) {

  $value=$options['url'];
  $fp = fopen("$value","r");
  if (!$fp)
    do_invalid($formatter,$options);

  while ($data = fread($fp, 4096)) $html_data.=$data;
  fclose($fp);

  print $html_data;

  $out = strip_tags($html_data, '<a><b><i><u><h1><h2><h3><h4><h5><li><img>');
  print $out;

  #$formatter->send_header();
  #$formatter->send_title();
  #$ret= macro_Test($formatter,$options[value]);
  #$formatter->send_page($ret);
  #$formatter->send_footer("",$options);
  #return;
}

?>
