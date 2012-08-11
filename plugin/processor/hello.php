<?php
// This is a sample plugin for MoniWiki
//
// Copyright 200X Your Name <foobar at foo.bar>
// All rights reserved.
// Distributable under GPL/LGPL/BSD etc.
// a sample plugin for the MoniWiki
//
// Author: Your name <foobar@foo.bar>
// Date: 200X-01-01
// Name: Processor name
// Description: this is a sample processor for the MoniWiki
// URL: to_plugin url/interwiki name etc.
// Version: $Revision: 1.7 $
// License: GPL
//
// Usage: {{{#!hello Name
// Hello World
// }}}
// $Id: hello.php,v 1.7 2008/12/22 11:03:22 wkpark Exp $

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

// vim:et:sts=4:sw=4:
?>
