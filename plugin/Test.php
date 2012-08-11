<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Your name <foobar@foo.bar>
// Date: 2006-01-01
// Name: Hello world
// Description: Hello world Plugin
// URL: to_plugin url/interwiki name etc.
// Version: $Revision: 1.5 $
// License: GPL
//
// Usage: [[Test]]
//
// $Id: Test.php,v 1.5 2006/08/17 08:02:21 wkpark Exp $

function macro_Test($formatter,$value) {
    return "HelloWorld !\n";
}

function do_test($formatter,$options) {
    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);
    $ret= macro_Test($formatter,$options['value']);
    $formatter->send_page($ret);
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
