<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Your name <foobar@foo.bar>
// Date: 2006-01-01
// Name: Hello world
// Description: Hello world Plugin
// URL: to_plugin url/interwiki name etc.
// Version: $Revision: 1.1 $
// License: GPL
//
// Usage: [[Test]]
//
// $Id: rawtext.php,v 1.1 2007/01/09 04:29:02 wkpark Exp $

function do_rawtext($formatter,$options) {
    $COLS_MSIE= 80;
    $COLS_OTHER= 85;

    $cols= preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);

    $body=$formatter->page->_get_raw_body();
    $raw_body = str_replace(array("&","<"),array("&amp;","&lt;"),$body);

    $rows=30;

    print <<<EOF
<form>
<textarea rows='$rows' cols='$cols' class='wiki'>$raw_body</textarea>
</form>
EOF;
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
