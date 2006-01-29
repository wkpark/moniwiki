<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a markup plugin for the MoniWiki
//
// $Id$

function do_markup($formatter,$options) {
    $formatter->preview=1;
    $formatter->sister_on=0;
    $formatter->nomacro=1;
    if ($options['value']) {
        $formatter->send_page(_stripslashes($options['value']),$options);
    }
    return;
}

// vim:et:sts=4:
?>
