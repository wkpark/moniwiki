<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a notitle action plugin
//
// $Id: notitle.php,v 1.1 2005/04/03 09:15:02 wkpark Exp $

function do_notitle($formatter,$options) {
    global $DBInfo;

    $formatter->sister_on=0;
    #$options['css_url']=$DBInfo->url_prefix."/css/print.css";
    $formatter->send_header("",$options);
    #print "<div id='printHeader'>";
    #print "<h2>$options[page]</h2>";
    #print "</div>";
    kbd_handler();
    print "<div id='wikiContent'>";
    $formatter->external_on=1;
    $formatter->send_page();
    print "</div></div>";
    print "</body></html>";
    return;
}

// vim:et:sts=4:
?>
