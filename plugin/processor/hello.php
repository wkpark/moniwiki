<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Usage: {{{#!hello Name
// Hello World
// }}}
// $Id$
// vim:et:ts=2:

function processor_hello($formatter,$value="") {

  $lines=explode("\n",$value);
  $tag=substr($lines[0],0,7);
  if ($tag=='#!hello') {
    # get parameters
    $args=substr($lines[0],8);
    unset($lines[0]);
  }

  foreach ($lines as $line)
    $out.="[<b>$args</b>]:$line<br />\n";

  return $out;
}

?>
