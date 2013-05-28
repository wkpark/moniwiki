<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rcsexport plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id: rcsexport.php,v 1.2 2008/12/25 09:14:14 wkpark Exp $

function do_rcsexport($formatter,$options) {
    global $DBInfo;
    if (!$DBInfo->version_class) {
        $msg= _("Version info is not available in this wiki");
        return "<h2>$msg</h2>";
    }

    $version = $DBInfo->lazyLoad('version', $DBInfo);
    header('Content-type:text/plain');
    if (method_exists($version,'export')) {
        echo '#title '.$formatter->page->name."\n";
        echo '#charset '.strtoupper($DBInfo->charset)."\n";
        echo '#encrypt base64'."\n";
        echo chunk_split(base64_encode($version->export($options['page'])));
    } else {
        echo 'Not supported';
    }
}

// vim:et:sts=4:
?>
