<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-22
// Name: User Info plugin
// Description: show user list in this wiki
// URL: MoniWiki:UserInfoPlugin
// Version: $Revision$
// License: GPL
//
// Usage: [[UserInfo]], ?action=userinfo
//
// $Id$

function macro_UserInfo($formatter,$value,$options=array()) {
    global $DBInfo;
    if (!$options['id'])
        return sprintf(_("You are not allowed to use the \"%s\" macro."),"UserInfo");

    $udb=&$DBInfo->udb;
    $user=&$DBInfo->user;
    $users=$udb->getUserList();
    $title=sprintf(_("Total %d users"),sizeof($users));

    $list='';
    if (in_array($user->id,$DBInfo->owners)) {
        foreach ($users as $u) {
            $list.='<li><input type="checkbox" name="uid[]" value="'.$u.'"/>'.
                $u."</li>\n";
        }
        $formhead="<form method='POST' action=''>";
        $formtail="<input type='hidden' name='action' value='useradmin' />".
            "<input type='submit' value='Delete Users' />".
            "</form>";
    } else {
        if (!empty($DBInfo->use_userinfo)) {
        foreach ($users as $u) {
            $list.='<li>'.$u."</li>\n";
        }
        } else {
            $list.='<li>'._("User infomation is restricted by wikimaster")."</li>\n";
        }
    }
    return "<h2>".$title."</h2>\n".$formhead."<ul>\n".$list."</ul>\n".$formtail;
}

function do_post_userinfo($formatter,$options) {
    global $DBInfo;

    $user=&$DBInfo->user;

    $formatter->send_header('',$options);
    if (is_array($DBInfo->owners) and in_array($user->id,$DBInfo->owners)) {
        if ($_POST and $options['uid'] and is_array($options['uid'])) {
            $udb=&$DBInfo->udb;
            $users=$udb->getUserList();

            foreach ($options['uid'] as $uid) {
                $uid=_stripslashes($uid);
                if (in_array($uid,$users)) {
                    $udb->delUser($uid);
                }
            }
            $options['msg']= sprintf(_("You are not allowed to \"%s\" !"),"userinfo");
        }
        $list= macro_UserInfo($formatter,'',$options);
    } else {
        $options['msg']= sprintf(_("You are not allowed to \"%s\" !"),"userinfo");
        $list='';
    }

    $formatter->send_title('','',$options);
    print $list;
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
