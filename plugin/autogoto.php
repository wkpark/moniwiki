<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// AutoGoto plugin
//
// Usage: set $auto_search='AutoGoto'; in the config.php
//
// $Id$

function do_AutoGoto($formatter,$options) {
    global $DBInfo;

    $npage=str_replace(' ','',$formatter->page->name);
    if ($DBInfo->hasPage($npage)) {
        $options['value']=$npage;
        do_goto($formatter,$options);
        return true;
    }
    $options['value']=$formatter->page->name;
    $options['check']=1;
    if (do_titlesearch($formatter,$options))
        return true;
    $options['value']=$formatter->page->name;
    # do not call AutoGoto recursively
    $options['redirect']=1;
    do_goto($formatter,$options);
    return true;
}

// vim:et:sts=4:
?>
