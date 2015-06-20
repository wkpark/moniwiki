<?php
// Copyright 2003-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a GetText macro plugin for the MoniWiki
//
// Usage: [[GetText(string)]]
//

function macro_GetText($formatter, $value, $params = array()) {
    if (!empty($formatter->lang))
        $lang = ' lang="'.substr($formatter->lang, 0, 2).'"';
    return '<span class="i18n"'.$lang.' title="'.str_replace('"', '&#34;', $value).'">'._($value).'</span>';
}

// vim:et:sts=4:sw=4:
