<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// anchor macro plugin for the MoniWiki
//
// Usage: [[Anchor]]
//
// $Id: Anchor.php,v 1.2 2006/08/12 07:31:54 wkpark Exp $

function macro_Anchor($formatter,$value) {
  static $id=1;
  if (!$value) {
    $tag="anchor-$id"; $id++;
    $text="";
  } else {
    $tag=strtok($value," ");
    $text=($tok=strtok("")) ? "<a href='#$tag'>".$tok."</a>":"";
  }
  return "<a id='$tag'></a>$text";
}

// vim:et:ts=2:
?>
