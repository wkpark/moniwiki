<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Icon macro plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$
function macro_Icon($formatter,$value='',$extra='') {
  global $DBInfo;

  $realdir=basename($DBInfo->imgs_dir);
  $dir=strtok($value,'-');
  $img=strtok('');
  if (is_dir($realdir.'/'.$dir)) $value=$dir.'/'.$img;

  $out=$DBInfo->imgs_dir."/$value";
  $out="<img src='$out' border='0' alt='icon' align='middle' />";
  return $out;
}

?>
