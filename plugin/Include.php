<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Include macro for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function macro_Include($formatter,$value="") {
  global $DBInfo;
  static $included=array();

#  $savelinks=$formatter->pagelinks; # don't update pagelinks with Included files

  preg_match("/([^'\",]+)(?:\s*,\s*)?(\"[^\"]*\"|'[^']*')?$/",$value,$match);
  if ($match) {
    $value=trim($match[1]);
    if ($match[2])
      $title="=== ".substr($match[2],1,-1)." ===\n";
  }

  if ($value and !in_array($value, $included) and $DBInfo->hasPage($value)) {
    $ipage=$DBInfo->getPage($value);
    $if=new Formatter($ipage);
    $ibody=$ipage->_get_raw_body();
    $opt['nosisters']=1;
    ob_start();
    $if->send_page($title.$ibody,$opt);
    $out= ob_get_contents();
    ob_end_clean();
#    $formatter->pagelinks=$savelinks;
    return $out;
  } else {
    return "[[Include($value)]]";
  }
}

?>
