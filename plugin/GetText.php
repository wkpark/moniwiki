<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a GetText macro plugin for the MoniWiki
// vim:et:ts=2:
//
// Usage: [[GetText(string)]]
//
// $Id$

function macro_GetText($formatter,$value) {
  return _($value);
}

?>
