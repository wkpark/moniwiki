<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Icon macro plugin for the MoniWiki
//
// $Id: Icon.php,v 1.7 2006/08/12 07:31:54 wkpark Exp $
function macro_Icon($formatter,$value='',$extra='') {
  global $DBInfo;

  if (strpos($value,'-')) {
    $dir=strtok($value,'-');
    #print $dir;
    $realdir=basename($DBInfo->imgs_dir);
    $img=strtok('');
    if (is_dir($realdir.'/'.$dir)) $value=$dir.'/'.$img;
  } else if (isset($formatter->icon[$value])) {
    return $formatter->icon[$value];
  } else if ($value == 'deleted') {
    return $formatter->icon['del'];
  } else if (! preg_match('/\.(gif|png|jpg|jpeg)$/',$value)) {
    $value=$DBInfo->iconset.'/'.$value.'.png';
  }

  $out=$formatter->imgs_dir."/$value";
  $out="<img src='$out' alt='icon' style='vertical-align:middle;border:0' />";
  return $out;
}

// vim:et:sts=2:
?>
