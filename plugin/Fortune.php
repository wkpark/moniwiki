<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Fortune plugin for the MoniWiki
//
// Usage: [[Fortune(science)]]
//
// $Id$
// vim:et:ts=2:

function macro_Fortune($formatter,$value) {
  $ret= exec(escapeshellcmd("/usr/games/fortune $value"),$log);
  $out= str_replace("_",'',join("\n",$log));
  return $out;
}

?>
