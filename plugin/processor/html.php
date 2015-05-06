<?php
// Copyright 2003-2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-04-10
// Name: Simple HTML Processor
// Description: HTML Processor plugin
// URL: MoniWiki:HTMLProcessor
// Version: $Revision: 1.1 $
// License: GPL
//
// Usage: {{{
// html code
// }}}
//
// $Id: html.php,v 1.1 2008/11/27 01:12:24 wkpark Exp $

function processor_html($formatter, $value = '') {
    global $Config;

    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    // check some iframes
    if (preg_match("/^\s*<(?:iframe|object)/", $value) and preg_match("@</(?:iframe|object)>\s*$@", $value)) {
        if (preg_match("@https?://(?:[a-z-]+[.])?youtube(?:[.][a-z-]+)+/(?:watch[?].*v=|v/|embed/)([a-z0-9_-]+)@i", $value, $m)) {
            $val = $value;
            // parse width,height
            if (preg_match_all("/(?:width|height)=(['\"])?\d+(?:px)?\\1/", $value, $match)) {
                if (!empty($match[0]))
                $arg = array();
                foreach ($match[0] as $v) $arg[] = $v;
                $args = implode(',', $arg);
                $val = $m[0];
                if (!empty($args)) $val.= ','.$args;
            }
            return $formatter->macro_repl('Play', $val);
        }
    }

    // XSS filtering
    if (!empty($Config['no_xss_filter']))
        return $value;

    return $formatter->filter_repl('xss', $value);
}

// vim:et:sts=4:sw=4:
