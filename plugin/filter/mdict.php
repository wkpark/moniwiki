<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a mdict postfilter plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2015-11-18
// Name: a mdict postfilter plugin
// Description: a mdict postfilter to fix anchor etc.
// URL: MoniWiki:MdictFilter
// Version: $Revision: 1.0 $
// License: GPLv2

function _fix_entry($m) {
    $decoded = _html_escape(urldecode($m[2]));
    return 'href="entry://'.$decoded.'"';
}

function postfilter_mdict($formatter, $value, $params = array()) {
    $chunks = preg_split('/(<[^>]+>)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

    for ($i = 0, $sz = count($chunks); $i < $sz; $i++) {
        if (preg_match('/^<(?:div|span)/i', $chunks[$i])) {
            // cleanup line-anchors
            $chunks[$i] = preg_replace('/\s*id=(["\'])a?line-[0-9]+\1/', '', $chunks[$i]);
        } else if (preg_match('/^<a\s*/i', $chunks[$i])) {
            // urldecode entries
            $chunks[$i] = preg_replace_callback('@href=(["\'])entry://(.*)\1@', '_fix_entry', $chunks[$i]);
        }
    }

    $out = implode('', $chunks);
    // cleanup empty line-anchors
    return str_replace("<span class='line-anchor'></span>", '', $out);
}

// vim:et:sts=4:sw=4:
