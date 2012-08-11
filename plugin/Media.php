<?php
// Copyright 2004 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Media(my.mp3)]]
//
// $Id: Media.php,v 1.1 2005/03/15 05:33:47 wkpark Exp $

function macro_Media($formatter,$value) {
  return $formatter->macro_repl('Play',$value);
}

// vim:et:sts=2:
?>
