<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Usage: {{{#!hello Name
// Hello World
// }}}
// $Id$

function processor_randomquote($formatter,$value="",$options=array()) {
    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    if ($line)
        list($tag,$args)=explode(' ',$line,2);

    return $formatter->macro_repl('RandomQuote','',array('body'=>$value));
}

// vim:et:ts=4:
?>
