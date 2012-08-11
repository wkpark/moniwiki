<?php
// Copyright 2004 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a MailTo plugin for the MoniWiki
//
// Usage: [[MailTo(Hello I DOT HATE hello DOT SPAM org)]]
//
// $Id: MailTo.php,v 1.2 2004/10/02 02:44:36 wkpark Exp $

function macro_MailTo($formatter,$value) {
  $new=preg_replace(
    array("/(?<=\s)DOT(?=\s)/","/(?<=\s)AT(?=\s)/",
          "/(?<=\s)DASH(?=\s)/","/(?<=\s)[A-Z]+(?=\s)/"),
    array(".","@","-",""),
    $value);

  $new=preg_replace(array("/\s/","/(?<=\s)[A-Z]+\s/"),"",$new);

  return $formatter->link_repl('mailto:'.$new);
}

// vim:et:sts=2:
?>
