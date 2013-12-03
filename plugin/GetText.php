<?php
// Copyright 2003-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a GetText macro plugin for the MoniWiki
//
// Usage: [[GetText(string)]]
//

function macro_GetText($formatter, $value, $params = array()) {
    return '<span class="i18n" title="'.str_replace('"', '&#34;', $value).'">'._($value).'</span>';
}

// vim:et:sts=4:sw=4:
