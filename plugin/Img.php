<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple Img macro plugin for the MoniWiki
// vim:et:ts=2:
//
// Usage: [[Img(http://blah.net/blah.png,100,50)]]
//
// $Id$

function macro_Img($formatter,$value) {
  preg_match("/(^[^,]+)(\s*,\s*)?(\d*,\d*)?$/",$value,$match);
  if ($match) {
    $image=$match[1];
    if ($match[3]) {
      $attr='';
      list($x,$y)=explode(',',$match[3]);
      if ($x) $attr = "width='$x' ";
      if ($y) $attr.= "height='$y' ";
    }
    return "<img src='$image' $attr alt='$image' />\n";
  }
  return '';
}

?>
