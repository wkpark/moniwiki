<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Echo plugin for the MoniWiki
//
// Usage: [[Echo(variable)]]
//
// $Id$
// vim:et:ts=2:

function macro_Echo($formatter,$value) {
  if ($_SERVER[$value]) return $_SERVER[$value];
  if ($_ENV[$value]) return $_ENV[$value];
  return '';
}

?>
