<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Icon macro plugin for the MoniWiki
//
// $Id$
function macro_Icon($formatter,$value='',$extra='') {
  global $DBInfo;

  if (strpos($value,'-')) {
    $dir=strtok($value,'-');
    #print $dir;
    $realdir=basename($DBInfo->imgs_dir);
    $img=strtok('');
    if (is_dir($realdir.'/'.$dir)) $value=$dir.'/'.$img;
  } else if (! preg_match('/\.(gif|png|jpg|jpeg)$/',$value)) {
    $value=$DBInfo->iconset.'/'.$value.'.png';
  }

  $out=$DBInfo->imgs_dir."/$value";
  $out="<img src='$out' border='0' alt='icon' align='middle' />";
  return $out;
}

// vim:et:sts=2:
?>
