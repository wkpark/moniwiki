<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a python colorizer plugin for the MoniWiki
//
// Usage: {{{#!python Name
// print 'Hello world'
// }}}
// $Id$

if (!function_exists("processor_vim"))
  include("vim.php");

function processor_python($formatter,$value="") {

  $value="#!vim ".substr($value,2);
  # get parameters
  return processor_vim($formatter,$value);
}

?>
