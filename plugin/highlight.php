<?php
// Copyright 2003-2014 Won-Kyu Park <wkpark@gmail.com>
// All rights reserved. Distributable under GPLv2 see COPYING
// a hightlight plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Since: 2003/04/15
// Name: Highlight plugin
// Description: Highlight multi keywords search Plugin
// URL: MoniWiki:HighlightPlugin
// Version: $Revision: 1.1 $
// License: GPLv2
//
// Usage: ?action=highlight&value=expr
//

function do_highlight($formatter, $params = array()) {
    if (isset($params['value']))
        $expr = $params['value'];
    else if (isset($params['q']))
        $expr = $params['q'];

    $expr = _stripslashes($expr);

    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    flush();

    ob_start();
    $formatter->send_page();
    flush();
    $out = ob_get_contents();
    ob_end_clean();

    if (isset($expr[0])) {
        highlight_repl(null, true);
        $highlight = _preg_search_escape($expr);

        $test = validate_needle($highlight);
        if ($test === false) {
            // invalid regex. quote all regexp specials
            $highlight = preg_quote($expr);
        }

        $out = preg_replace_callback('/((<[^>]*>)|('.$highlight.'))/i',
                'highlight_repl', $out);
        echo $out;
    } else {
        echo $out;
    }

    $args['editable'] = 1;
    $formatter->send_footer($args, $params);
}

function highlight_repl($val, $reset = false) {
    if (is_array($val)) $val = $val[1]; // for callback
    if ($val[0] == '<') return $val;
    if ($reset) {
        $cid = 0;
        $colref = array();
        return;
    }

    static $colref = array(), $cid = 0;
    // coloring style
    $color = array(
            "style='background-color:#ffff99;'",
            "style='background-color:#99ffff;'",
            "style='background-color:#99ff99;'",
            "style='background-color:#ff9999;'",
            "style='background-color:#ff99ff;'",
            "style='background-color:#9999ff;'",
            "style='background-color:#999999;'",
            "style='background-color:#886800;'",
            "style='background-color:#004699;'",
            "style='background-color:#990099;'");

    $key = strtolower($val);
    if (!isset($colref[$key])) $colref[$key] = $cid++;

    return "<strong ".($color[$colref[$key] % 10]).">$val</strong>";
}

// vim:et:sts=4:sw=4:
