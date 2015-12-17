<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple ExternalImage plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark at kldp.org>
// Date: 2015-10-05
// Name: External Image Plugin
// Description: External Image Plugin
// URL: MoniWiki:ExternalImage
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[ExternalImage(url)]]
//

function macro_ExternalImage($formatter, $value, $params = array()) {
    // Wikimedia Commons cases
    // http://upload.wikimedia.org/wikipedia/commons/5/55/APS_underwater_rifle_REMOV.jpg
    // http://upload.wikimedia.org/wikipedia/commons/e/e6/Aps_protei_ida71_00.jpg
    // http://upload.wikimedia.org/wikipedia/commons/thumb/a/aa/3-Tastenmaus_Microsoft.jpg/250px-3-Tastenmaus_Microsoft.jpg

    if (preg_match('@^https?://upload\.wikimedia\.org/wikipedia/(?:(en|commons)/)?(thumb/)?./../([^/]+\.(?:gif|jpe?g|png|svg))(?(2)/(\d+px)-\3)@', $value, $m)) {
        //$args = '';
        //if (!empty($m[4]))
        //    $args.= ',width='.$m[4];
        return $formatter->macro_repl('WikimediaCommons', $value, $params);
    } else if (preg_match('@^https?://(?:[^.]+)\.(?:wikimedia|wikipedia)\.org/wiki/(?:Image|File):([^/]+\.(?:gif|jpe?g|png|svg))@', $value, $m)) {
        return $formatter->macro_repl('WikimediaCommons', $value, $params);
    } else if (preg_match('@^https?://([^.]+)\.wikia\.com/wiki/(?:Image|File):(.*\.(?:gif|jpe?g|png|svg))@', $value, $m)) {
        return $formatter->macro_repl('WikimediaCommons', $value, $params);
    } else if (preg_match('@^https?://.*\.wikia\..*/([^/]+)/images/./../([^/]+\.(?:gif|jpe?g|png|svg))@', $value, $m)) {
        return $formatter->macro_repl('WikimediaCommons', $value, $params);
    }

    return false;
}

// vim:et:sts=4:sw=4:
