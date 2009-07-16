<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a latex2png plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-01
// Name: Latex To PNG plugin
// Description: convert latex syntax to PNGs
// URL: MoniWiki:Latex2PngPlugin
// Version: $Revision$
// License: GPL
//
// Usage: ?action=latex2png&value=$\alpha$
//
// $Id$

function macro_latex2png($formatter,$value,$params=array()) {
    $png= $formatter->processor_repl('latex',$value,array('raw'=>1));
    return $png;
}

function do_latex2png($formatter,$options) {
    $opts=array('raw'=>1);
    if (isset($options['dpi']) and $options['dpi'] > 120 and $options['dpi'] < 600)
        $opts['dpi']=$options['dpi'];
    $my= $formatter->processor_repl('latex',$options['value'],$opts);
    if (!is_array($my))
        $png = $my;
    else
        $png = $my[0];

    if (file_exists($png)) {
        Header("Content-type: image/png");
        readfile($png);
    } else {
        Header("Content-type: image/png");
        readfile($png);
    }
}

// vim:et:sts=4:
?>
