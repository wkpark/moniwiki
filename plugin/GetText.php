<?php
// Copyright 2003-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a GetText macro plugin for the MoniWiki
//
// Usage: [[GetText(string)]]
//

function macro_GetText($formatter, $value, $params = array()) {
    // make GetText as a dynamic macro.
    if ($formatter->_macrocache and empty($options['call']))
        return $formatter->macro_cache_repl('GetText', $value);
    return _($value);
}

// vim:et:sts=4:sw=4:
