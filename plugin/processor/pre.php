<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a preserve whitespace processor plugin for the MoniWiki
//   by Anonymous Doner :)
//
// Usage: {{{#!pre
// blah blah
//   blah
//     blah
// }}}
//
// $Id: pre.php,v 1.3 2010/04/19 11:26:47 wkpark Exp $

function processor_pre($formatter,$value) {
    if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

    /*
    if ($line)
    list($tag,$args)=explode(' ',$line,2);
    */

    #$pre=preg_replace($formatter->baserule,$formatter->baserepl,$value);
    #$pre=
    #    preg_replace("/(".$wordrule.")/e","\$formatter->link_repl('\\1')",$value);
    #$pre = htmlspecialchars($value);

    $pre = str_replace(
        array('&','<', '>', "\t", "\n", '  '),
        array('&amp;','&lt;', '&gt;',
            '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
            "<br />\n",
            ' &nbsp;'),
        $value);

    #$pre=preg_replace("/(\s\s+)/e","str_repeat('&nbsp;',strlen('\\1'))",$pre);

    $out = "<div class='preWhiteSpace'>$pre</div>";

    return $out;
}

// vim:et:sts=4
?>
