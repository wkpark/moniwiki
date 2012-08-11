<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// FixMoin plugin
//
// Usage: set $auto_search='FixMoin'; in the config.php
//
// $Id: fixmoin.php,v 1.3 2005/09/01 03:45:23 wkpark Exp $

function do_FixMoin($formatter,$options) {
    global $DBInfo;

    $pagename=rawurldecode(preg_replace('/_([0-9a-f]{2})/i','%\\1',$formatter->page->name));
    $npage=str_replace(' ','',$pagename);
    if (!$DBInfo->hasPage($npage)) {
        if (strtolower($DBInfo->charset)=='utf-8') {
            # is it EUC-KR ?
            $new=iconv('EUC-KR',$DBInfo->charset,$npage);
            if ($new) $npage=$new;
        }
    }
    if (!$npage or !$DBInfo->hasPage($npage)) {
        $options['redirect']=1;
        $options['value']=$formatter->page->name;
        do_goto($formatter,$options);
        return true;
    }
    $options['redirect']=1;
    $options['value']=$npage;
    do_goto($formatter,$options);
    return true;
}

// vim:et:sts=4:
?>
