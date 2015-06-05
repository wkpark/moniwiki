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

    $offset = $off = !empty($options['offset']) ? $options['offset'] : 0;
    $limit = !empty($options['limit']) ? $options['limit'] : 100;
    // page
    $pg = !empty($options['p']) ? $options['p'] : 1;
    $q = !empty($options['q']) ? trim($options['q']) : '';
    $type = !empty($options['type']) ? $options['type'] : '';

    $strs = array(''=>_("Total %d users found."),
        'wait'=>_("Total %d Suspended users found."),
        'del'=>_("Total %d Deleted users found."));
    if (!in_array($type, array('wait', 'del'))) {
        $type = '';
    }
    $title = $strs[$type];

    if ($limit > 100) $limit = 100;
    if ($pg > 1) $off+= ($pg - 1) * $limit;

    $params = array('offset' => $off, 'limit'=>$limit);
    $retval = array();
    $params['retval'] = &$retval;
    if (!empty($q))
        $params['q'] = $q;
    if (!empty($type))
        $params['type'] = $type;

    $udb=&$DBInfo->udb;
    $user=&$DBInfo->user;
    $users=$udb->getUserList($params);
    $title = sprintf($title, $retval['count']);
    $sz = sizeof($users);

    $list='';
    $cur = time();

    if ($sz == 1 && in_array($user->id,$DBInfo->owners)) {
        $keys = array_keys($users);

        $u = $udb->getUser($keys[0], $type != '');
        $list = '<table>';
        $list.= '<tr><th>'._("ID").'</th></th><td>'.$keys[0].'</td></tr>';
        if (isset($u->email))
            $list.= '<tr><th>'._("E-mail").'</th><td>'.$u->email.'</td></tr>';
        if (isset($u->info)) {
            foreach ($u->info as $k => $v) {
                $list.= '<tr><th>'.$k.'</th><td>'.$v.'</td></tr>';
            }
        }
        $list.= '</table>';

        if (empty($type) or $type == 'wait')
            $btn = _("Delete Users");
        else if ($type == 'del')
            $btn = _("Activate Users");

        $formhead="<form method='POST' action=''>";
        $formtail='';
        if ($DBInfo->security->is_protected('userinfo',$options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.="<input type='hidden' name='action' value='userinfo' />";
        $formtail.="<input type='hidden' name='type' value='$type' />";
        $formtail.="<input type='hidden' name='uid[]' value='$keys[0]' />".
            "<input type='submit' value='$btn' />";

        $formtail.= "</form>";

    } else if (in_array($user->id,$DBInfo->owners)) {
        $names = array_keys($users);
        $pages = intval($retval['count'] / $limit);
        $query = '?action=userinfo';
        if ($limit != 100)
            $query.= '&amp;limit='.$limit;
        if (!empty($offset))
            $query.= '&amp;offset='.$offset;

        // paginate
        $pnut = '';
        if ($pages > 0)
            $pnut = get_pagelist($formatter, $pages,
                $query.'&amp;p=', $pg);

        for ($i = 0; $i < $limit && $i < $sz; $i++) {
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
        $list = "<ul>\n".$list."</ul>\n";
        $formhead="<form method='POST' action=''>";
        $formtail='';

        if (empty($type) or $type == 'wait')
            $btn = _("Delete Users");
        else if ($type == 'del')
            $btn = _("Activate Users");
        $btn2 = _("Suspend Users");

        if ($DBInfo->security->is_protected('userinfo',$options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.="<input type='hidden' name='action' value='userinfo' />".
            "<input type='hidden' name='type' value='$type' />".
            "<input type='submit' value='$btn' /> ".
            "<input type='submit' name='suspend' value='$btn2' />";

        $formtail.= "</form>";

        $select = "<select name='type'>\n";
        foreach (array('ALL'=>'', 'WAIT'=>'wait', 'DELETED'=>'del') as $k=>$v) {
            if ($type == $v)
                $checked = ' selected="selected"';
            else
                $checked = '';
            $select.= "<option value='$v'$checked>$k</option>";
        }
        $select.= "</select>";

        $formtail.= "<form method='GET'>".$select.
            "<input type='hidden' name='action' value='userinfo' />".
            "<input type='text' name='q' value='' placeholder='Search' />";
        $formtail.= "</form>";
        $formtail.= $pnut;
    } else {
        if (!empty($DBInfo->use_userinfo)) {
        foreach ($users as $u => $v) {
            $list.='<li>'.$u."</li>\n";
        }
        } else {
            $list.='<li>'._("User infomation is restricted by wikimaster")."</li>\n";
        }
        $list = '<ul>'."\n".$list.'</ul>'."\n";
    }
    return "<h2>".$title."</h2>\n".$formhead.$list.$formtail;
}

function do_userinfo($formatter,$options) {
    global $DBInfo;

    $user=&$DBInfo->user;

    $formatter->send_header('',$options);
    if (is_array($DBInfo->owners) and in_array($user->id,$DBInfo->owners)) {
        if (isset($_POST) and isset($options['uid']) and is_array($options['uid'])) {
            $udb=&$DBInfo->udb;
            $type = !empty($options['type']) ? $options['type'] : '';
            if (!in_array($type, array('wait', 'del'))) {
                $type = '';
            }
            $suspend = !empty($options['suspend']) ? true : false;

            $change = array();
            foreach ($options['uid'] as $uid) {
                $uid=_stripslashes($uid);
                if ($type == 'del' || $suspend)
                    $ret = $udb->activateUser($uid, $suspend);
                else
                    $ret = $udb->delUser($uid);
                if ($ret)
                    $change[] = $uid;
            }
            if (!empty($change)) {
                $changed = implode(',',$change);
                if ($suspend)
                    $options['msg']= sprintf(_("User \"%s\" are suspended !"),_html_escape($changed));
                else if ($type == 'del')
                    $options['msg']= sprintf(_("User \"%s\" are activated !"),_html_escape($changed));
                else
                    $options['msg']= sprintf(_("User \"%s\" are deleted !"),_html_escape($changed));
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
