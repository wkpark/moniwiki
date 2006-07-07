<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample aclinfo plugin for the MoniWiki
//
// Usage: ?action=aclinfo
//
// $Id$

function do_aclinfo($formatter,$options) {
    global $DBInfo;
    if ($DBInfo->security_class=='acl') {
        $options['aclinfo']=1;
        list($allowed,$denied)=$DBInfo->security->acl_check('aclinfo',$options);
    } else {
        $options['msg']=_("ACL is not enabled on this Wiki");
        do_invalid($formatter,$options);
        return;
    }
    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);
    print '<h2>'._("Your ACL Info").'</h2>';
    if (in_array($options['id'],$DBInfo->owners)) {
        print '<h3>'._("You are wiki owner")."</h3>\n";
    } else if (in_array($options['id'],$DBInfo->wikimasters)) {
        print '<h3>'._("You are wiki master")."</h3>\n";
    } else {
        print '<h3>'._("allowed actions")."</h3>\n";
        print '<pre>';
        print_r($allowed);
        print '</pre>';
        print '<h3>'._("denied actions")."</h3>\n";
        print '<pre>';
        print_r($denied);
        print '</pre>';
    }
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
