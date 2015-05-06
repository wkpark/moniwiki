<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a xss filter plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2015-05-06
// Name: a XSS filter plugin
// Description: a XSS filter plugin
// URL: MoniWiki:XssFilter
// Version: $Revision: 1.1 $
// License: GPLv2
//

function filter_xss($formatter, $value, $params) {
    include_once(dirname(__FILE__).'/../../lib/xss.php');
    return _xss_filter($value);
}
// vim:et:sts=4:sw=4:
