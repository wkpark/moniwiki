<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a python colorizer plugin for the MoniWiki
//
// Usage: {{{#!python Name
// print 'Hello world'
// }}}
// $Id: python.php,v 1.6 2008/12/17 03:40:01 wkpark Exp $

function processor_python($formatter,$value="") {
  //
  // for pre 1.1.3 version
  //
  #if ($value[0]=='#' and $value[1]=='!')
  #  $value="#!vim ".substr($value,2);
  #else
  #  $value="#!vim python\n".$value;
  # get parameters
  return $formatter->processor_repl('vim',$value);
}

?>
