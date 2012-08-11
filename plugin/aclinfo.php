<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample aclinfo plugin for the MoniWiki
//
// Usage: ?action=aclinfo
//
// $Id: aclinfo.php,v 1.2 2006/07/08 14:31:28 wkpark Exp $

function do_aclinfo($formatter,$options) {
    global $DBInfo;
    if ($DBInfo->security_class=='acl') {
        list($allowed,$denied,$protected)=$DBInfo->security->get_acl('aclinfo',$options);
    } else {
        $options['msg']=_("ACL is not enabled on this Wiki");
        do_invalid($formatter,$options);
        return;
    }
    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);
    print '<h2>'._("Your ACL Info").'</h2>';
    if (in_array($options['id'],$DBInfo->owners)) {
        print '<h4>'._("You are wiki owner")."</h4>\n";
    } else if (in_array($options['id'],$DBInfo->wikimasters)) {
        print '<h4>'._("You are wiki master")."</h4>\n";
    } else {
        print '<h4>'._("Allowed actions")."</h4>\n";
        print '<ul>';
        foreach ($allowed as $k=>$v)
            print '<li>'.$k.': ('.$v.')</li>';
        print '</ul>';
        print '<h4>'._("Denied actions")."</h4>\n";
        print '<ul>';
        foreach ($denied as $k=>$v)
            print '<li>'.$k.': ('.$v.')</li>';
        print '</ul>';
        print '</pre>';
        print '<h4>'._("Protected actions")."</h4>\n";
        print '<ul><li>';
        print implode('</li><li>',$protected);
        print '</li></ul>';
    }
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
