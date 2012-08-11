<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Usage: {{{#!hello Name
// Hello World
// }}}
// $Id: randomquote.php,v 1.3 2010/08/23 09:15:23 wkpark Exp $

function processor_randomquote($formatter,$value="",$options=array()) {
    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    $args = '';
    if (!empty($line))
        list($tag,$args)=explode(' ',$line,2);

    return $formatter->macro_repl('RandomQuote',$args,array('body'=>$value));
}

// vim:et:ts=4:
?>
