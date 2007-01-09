<?php
// Copyright 2007 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2007-01-09
// Name: WHTML Processor
// Description: HTML with WikiLinks Processor
// URL: MoniWiki:WikiHtmlProcessor
// Version: $Revision$
// License: GPL
//
// Usage: {{{#!whtml
// <h1>Hello world ! Hello [MoniWiki]</h1>
// Hello World
// }}}
// $Id$

function processor_whtml($formatter,$value='',$options=array()) {
    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    if ($line)
        list($tag,$args)=explode(' ',$line,2);

    $formatter->set_wordrule();
    $smiley_rule='/(?<=\s|^|>)('.$DBInfo->smiley_rule.')(?=\s|$|<)/e';
    $smiley_repl="\$formatter->smiley_repl('\\1')";

    $save=$formatter; // do not disturb $formatter
    $formatter->nonexists='always';

    $chunks=preg_split('/(<[^>]+>)/',$value,-1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i=0,$sz=count($chunks); $i<$sz; $i++) {
        if ($chunks[$i][0] != '<') {
            $out=$chunks[$i];
            $out=preg_replace($smiley_rule,$smiley_repl,$out);
            $out=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$out);
            $chunks[$i]=$out;
        }
    }

    $formatter=$save;

    return implode('',$chunks);
}

// vim:et:sts=4:sw=4:
?>
