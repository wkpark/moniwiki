<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Usage: {{{#!hello Name
// Hello World
// }}}
// $Id$

function processor_hello($formatter,$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if ($line)
    list($tag,$args)=explode(' ',$line,2);

  $lines=explode("\n",$value);
  foreach ($lines as $line)
    $out.="[<b>$args</b>]:$line<br />\n";

  return $out;
}

// vim:et:ts=2:
?>
