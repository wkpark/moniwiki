<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Icon macro plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$
function macro_Icon($formatter,$value='',$extra='') {
  global $DBInfo;

  $out=$DBInfo->imgs_dir."/$value";
  $out="<img src='$out' border='0' alt='icon' align='middle' />";
  return $out;
}

?>
