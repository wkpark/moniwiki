<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// anchor macro plugin for the MoniWiki
//
// Usage: [[Anchor]]
//
// $Id$

function macro_Anchor($formatter,$value) {
  static $id=1;
  if (!$value) {
    $tag="anchor-$id"; $id++;
    $text="";
  } else {
    $tag=strtok($value," ");
    $text=($tok=strtok("")) ? "<a href='#$tag'>".$tok."</a>":"";
  }
  return "<a name='$tag' id='$tag'>$text</a>";
}

// vim:et:ts=2:
?>
