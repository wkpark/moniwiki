<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Echo plugin for the MoniWiki
//
// Usage: [[Echo(variable)]]
//
// $Id: Echo.php,v 1.2 2010/04/26 07:20:01 wkpark Exp $
// vim:et:ts=2:

function macro_Echo($formatter,$value) {
  if (!empty($_SERVER[$value])) return $_SERVER[$value];
  if (!empty($_ENV[$value])) return $_ENV[$value];
  return '';
}

?>
