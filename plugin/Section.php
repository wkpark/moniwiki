<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Section styling macro for the MoniWiki
//
// Author: Won-Kyu Park <wkpark at gmail.com>
// Date: 2015/11/29
// Name: SectionPlugin
// Description: Styling Section plugin. internally used by $use_folding option
// License: GPLv2
//
// Usage: [[Section(close|open|on|off)]]
//

function macro_Section($formatter, $value = '') {
    // reset the internal section_style variable.
    $formatter->section_style = array();

    $class = array('closed');

    // check for markup preview
    if (!empty($formatter->wikimarkup) && $formatter->wikimarkup == 2)
        $class[] = 'preview';

    if (in_array($value, array('on', 'off'))) {
        if ($value == 'on') {
          $formatter->section_style['heading'] = 'closed';
          $formatter->section_style['section'] = implode('-', $class);
        } else {
          $formatter->section_style = null;
        }
    } else if (in_array($value, array('+', 'closed', 'close'))) {
        $formatter->section_style['heading'] = 'closed';
        $formatter->section_style['section'] = implode('-', $class);
    } else if (in_array($value, array('-', 'open', 'opened'))) {
        $formatter->section_style['heading'] = 'opened';
        $formatter->section_style['section'] = '';
    }
    return ' ';
}

// vim:et:sts=4:sw=4:
