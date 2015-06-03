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
// Version: $Revision: 1.2 $
// License: GPL
//
// Usage: [[UserInfo]], ?action=userinfo
//
// $Id: userinfo.php,v 1.2 2008/12/22 08:33:13 wkpark Exp $

function macro_UserInfo($formatter,$value,$options=array()) {
    global $DBInfo;
    if ($options['id'] == 'Anonymous')
        return sprintf(_("You are not allowed to use the \"%s\" macro."),"UserInfo");

    $off = !empty($options['offset']) ? $options['offset'] : 0;
    $limit = !empty($options['limit']) ? $options['limit'] : 100;
    $q = !empty($options['q']) ? $options['q'] : '';
    if ($limit > 100) $limit = 100;
    if ($off > sizeof($users)) $off = 0;

    $params = array('offset' => $off, 'limit'=>$limit);
    $retval = array();
    $params['retval'] = &$retval;
    if (!empty($q))
        $params['q'] = $q;

    $udb=&$DBInfo->udb;
    $user=&$DBInfo->user;
    $users=$udb->getUserList($params);
    $title=sprintf(_("Total %d users"), $retval['count']);

    $list='';
    $cur = time();
    if (in_array($user->id,$DBInfo->owners)) {
        $sz = sizeof($users);
        $names = array_keys($users);
        for ($i = $off; $i < $off + $limit && $i < $sz; $i++) {
            $u = $names[$i];

            $mtime = $users[$u];
            $test = $cur - $mtime;
            if ($test > 60*60*24*365*2)
                $color = '#c0c0c0';
            else if ($test > 60*60*24*365)
                $color = 'blue';
            else if ($test > 60*60*24*30*6)
                $color = 'green';
            else if ($test > 60*60*24*30)
                $color = '#ff00ff';
            else
                $color = '#ff0000';

            $date = date("Y-m-d H:i:s", $mtime);
            $list.='<li><input type="checkbox" name="uid[]" value="'.$u.'"/>'.
                $u." (<span style='color:".$color."'>".$date."</span>)</li>\n";
        }
        $formhead="<form method='POST' action=''>";
        $formtail='';
        if ($DBInfo->security->is_protected('userinfo',$options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.="<input type='hidden' name='action' value='userinfo' />".
            "<input type='submit' value='Delete Users' />";

        $formtail.= "</form>";

        $formtail.= "<form method='GET'>".
            "<input type='hidden' name='action' value='userinfo' />".
            "<input type='text' name='q' value='' placeholder='Search' />";
        $formtail.= "</form>";
    } else {
        if (!empty($DBInfo->use_userinfo)) {
        foreach ($users as $u => $v) {
            $list.='<li>'.$u."</li>\n";
        }
        } else {
            $list.='<li>'._("User infomation is restricted by wikimaster")."</li>\n";
        }
    }
    return "<h2>".$title."</h2>\n".$formhead."<ul>\n".$list."</ul>\n".$formtail;
}

function do_userinfo($formatter,$options) {
    global $DBInfo;

    $user=&$DBInfo->user;

    $formatter->send_header('',$options);
    if (is_array($DBInfo->owners) and in_array($user->id,$DBInfo->owners)) {
        if (isset($_POST) and $options['uid'] and is_array($options['uid'])) {
            $udb=&$DBInfo->udb;
            $users=$udb->getUserList();

            $del=array();
            foreach ($options['uid'] as $uid) {
                $uid=_stripslashes($uid);
                if (in_array($uid,$users)) {
                    $udb->delUser($uid);
                    $del[]=$uid;
                    
                }
            }
            if (!empty($del)) {
                foreach ($del as $d) {
                    $k = array_search($d,$udb->users);
                    unset($udb->users[$k]);
                }
                $deleted = implode(',',$del);
                $options['msg']= sprintf(_("User \"%s\" are deleted !"),$deleted);
            }
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
