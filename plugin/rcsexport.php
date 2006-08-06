<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rcsexport plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id$

function do_rcsexport($formatter,$options) {
    global $DBInfo;
    if (!$DBInfo->version_class) {
        $msg= _("Version info is not available in this wiki");
        return "<h2>$msg</h2>";
    }

    getModule('Version',$DBInfo->version_class);
    $class='Version_'.$DBInfo->version_class;
    $version=new $class ($DBInfo);
    header('Content-type:text/plain');
    if (method_exists($version,'export'))
        print chunk_split(base64_encode($version->export($options['page'])));
    else
        print 'Not supported';
}

// vim:et:sts=4:
?>
