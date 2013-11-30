<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Include macro for the MoniWiki
//
// $Id: Include.php,v 1.3 2010/07/09 14:37:00 wkpark Exp $

function macro_Include($formatter,$value="") {
  global $DBInfo;
  static $included=array();

#  $savelinks=$formatter->pagelinks; # don't update pagelinks with Included files

  preg_match("/([^'\",]+)(?:\s*,\s*)?(\"[^\"]*\"|'[^']*')?(?:\s*,\s*)?([0-9]+)?$/",$value,$match);
  $title = '';
  if ($match) {
    $value=trim($match[1]);
    if (isset($match[2])) {
      if ($match[3])
        $level = $match[3];
      else
        $level = 3;
      $title=str_repeat("=", $level)." ".substr($match[2],1,-1)." ".str_repeat("=", $level)."\n";
    }
  }

  if ($value and !in_array($value, $included) and $DBInfo->hasPage($value)) {
    $ipage=$DBInfo->getPage($value);
    $if=new Formatter($ipage);
    $ibody=$ipage->_get_raw_body();
    $opt['nosisters']=1;

    $if->get_javascripts(); // trash default javascripts

    ob_start();
    $if->send_page($title.$ibody,$opt);
    $out= ob_get_contents();
    ob_end_clean();
#    $formatter->pagelinks=$savelinks;
    return $out;
  } else {
    return $formatter->link_repl($value);
  }
}

// vim:et:sts=2:sw=2:
