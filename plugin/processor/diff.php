<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a diff colorizer plugin for the MoniWiki
//
// Usage: {{{#!diff
// - hello world
// + Hello world
// }}}
// $Id$

function processor_diff($formatter,$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  list($dummy, $type)=explode(' ',$line);
  if (in_array($type,array('fancy','simple'))) {
    $type=$type.'_diff';
  } else
    $type='fancy_diff';
  // add first two blank lines
  return call_user_func(array(&$formatter,$type),"\n\n".$value);
}

?>
