<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Author: Your name <foobar@foo.bar>
// Date: 2006-01-01
// Name: Hello world
// Description: Hello world Processor
// URL: to_plugin url/interwiki name etc.
// Version: $Revision$
// License: GPL
//
// Usage: {{{#!hello Name
// Hello World
// }}}
// $Id$

function processor_hello($formatter,$value="",$options=array()) {
    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    if ($line)
        list($tag,$args)=explode(' ',$line,2);

    $lines=explode("\n",$value);
    foreach ($lines as $line)
        $out.="[<b>$args</b>]:$line<br />\n";

    return $out;
}

// vim:et:sts=4:
?>
