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
    $type = !empty($options['type']) ? trim($options['type']) : 'wait';
    $act = !empty($options['act']) ? trim($options['act']) : '';
    $act = strtolower($act);
    $type = strtolower($type);

    $strs = array('all'=>_("Total %d users found."),
        'wait'=>_("Total %d Suspended users found."),
        'del'=>_("Total %d Deleted users found."));
    if (!in_array($type, array('wait', 'del', 'monitor'))) {
        $type = 'all';
    }

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

    if (!empty($q) || $type != 'monitor') {
        $users=$udb->getUserList($params);
        $sz = sizeof($users);
    }

    if ($type != 'monitor') {
        $title = $strs[$type];
        $title = sprintf($title, $retval['count']);
    } else {
        $title = _("Contributors Monitor");
    }

    $list='';
    $cur = time();

    $allowed = $DBInfo->security_class == 'acl' &&
            $DBInfo->security->is_allowed($options['action'], $options);
    if (!$allowed)
        $allowed = in_array($user->id,$DBInfo->owners);

    if ($allowed && $type == 'monitor') {
        // abusefilter cache
        $ac = new Cache_Text('abusefilter');

        // prepare to return
        $ret = array();
        $retval = array();
        $ret['retval'] = &$retval;

        if (!empty($q) && in_array($act, array('reset', 'suspend'))) {
            if ($act == 'reset') {
                // clear abusefilter cache
                $title = _("Reset monitoring state");
                $info = $ac->fetch($q, 0, $ret);
                if ($info !== false)
                    $ac->remove($q);
            } else if ($act == 'suspend') {
                // suspend more
                $title = _("Suspend more 30 minutes");
                $info = $ac->fetch($q, 0, $ret);
                $ttl = $retval['ttl'] + 60*30; // 30 minutes more;
                if ($info !== false)
                    $ac->update($q, $info, $ttl);
            }
        }

        $files = array();
        $ac->_caches($files, array('prefix'=>1));

        $list = '<table class="wiki editinfo">';
        $list.= '<tr><th>'._("ID").'</th></th><th>'._("IP").'</th><th>'._("mtime").
                '</th><th>'._("Suspended or TTL").'</th><th>'._("Edits").'</th><th>'._("actions").'</th></tr>';
        foreach ($files as $f) {
            // low level _fetch(), _remove()
            $info = $ac->_fetch($f, 0, $ret);
            if ($info === false) {
                $ac->_remove($f);
                continue;
            }
            if (!isset($info['id']))
                continue;

            $ttl = $retval['ttl'];
            $check = array(
                'create'=>'C',
                'edit'=>'E',
                'save'=>'S',
                'delete'=>'X',
                'revert'=>'R',
                'revoke'=>'V',
            );

            $edit = array(
                'add_lines'=>'L+',
                'add_chars'=>'C+',
                'del_lines'=>'L-',
                'del_chars'=>'C-',
            );

            $class = array(
                    'add_lines'=>'diff-added',
                    'add_chars'=>'diff-added',
                    'del_lines'=>'diff-removed',
                    'del_chars'=>'diff-removed',
            );
            $edits = array();
            foreach ($check as $c=>$k) {
                if (!empty($info[$c]))
                    $edits[] = '<span class="'.$c.'"><span>'.$k.'</span>'.
                        '<span class="num">'.$info[$c].'</span></span>';
            }
            $out = implode(',', $edits);
            $edits = array();
            foreach ($edit as $c=>$k) {
                if (!empty($info[$c])) {
                    $edits[] = '<span class="'.$class[$c].'">'.$k.''.$info[$c].'</span>';
                }
            }
            $out.= '<br />'.implode('', $edits);

            $list.= '<tr><td>';
            $list.= '<a href="?action=userinfo&amp;type=all&q='.$info['id'].
                '"><span>'.$info['id'].'</span></a></td>';
            if (isset($info['ip']) and $info['id'] != $info['ip'])
                $list.= '<td>'.$info['ip'].'</td>';
            else
                $list.= '<td>&nbsp;</td>';
            $list.= '<td>'.date('Y-m-d H:i:s', $retval['mtime']).'</td>';
            $list.= '<th>'.$ttl.'</th>';
            $list.= '<td><span class="editinfo">'.$out.'</span></td>';
            $list.= '<td>';
            $list.= '<a class="button-small" href="?action=userinfo&amp;type=monitor'.
                '&amp;act=reset&amp;q='.$info['id'].
                '"><span>'._("Reset").'</span></a> ';
            $list.= '<a class="button-small" href="?action=userinfo&amp;type=monitor'.
                '&amp;act=suspend&amp;q='.$info['id'].
                '"><span>'._("Suspend more").'</span></a> ';
            $list.= '</td>';
            $list.= '</tr>';
        }
        $list.= '</table>';
    } else if ($sz == 1 && $allowed) {
        $keys = array_keys($users);

        $inf = $udb->getInfo($keys[0], $type != 'all');
        $list = '<table>';
        $list.= '<tr><th>'._("ID").'</th></th><td>'.$keys[0].'</td></tr>';
        foreach ($inf as $k => $v) {
            $list.= '<tr><th>'.$k.'</th><td>'.$v.'</td></tr>';
        }
        $list.= '</table>';

        if ($type == 'all')
            $btn = _("Delete Users");
        else if ($type == 'del' or $type == 'wait')
            $btn = _("Activate Users");
        if ($type != 'wait')
            $btn2 = _("Suspend Users");

        $formhead="<form method='POST' action=''>";
        $formtail='';
        if ($DBInfo->security->is_protected('userinfo',$options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.="<input type='hidden' name='action' value='userinfo' />";
        $formtail.="<input type='hidden' name='type' value='$type' />";
        $formtail.="<input type='hidden' name='uid[]' value='$keys[0]' />".
            "<input type='submit' value='$btn' /> ";
        if ($type != 'wait')
            $formtail.=
                "<input type='submit' name='suspend' value='$btn2' />";

        $formtail.= "</form>";

    } else if ($allowed) {
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
                '<a href="?action=userinfo&amp;type='.
                $type.'&amp;q='.$u.'">'.$u."</a> (<span style='color:".$color."'>".$date."</span>)</li>\n";
        }
        $list = "<ul>\n".$list."</ul>\n";
        $formhead="<form method='POST' action=''>";
        $formtail='';

        if ($type == 'all')
            $btn = _("Delete Users");
        else if ($type == 'del' or $type == 'wait')
            $btn = _("Activate Users");
        if ($type != 'wait')
            $btn2 = _("Suspend Users");

        if ($DBInfo->security->is_protected('userinfo',$options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.="<input type='hidden' name='action' value='userinfo' />".
            "<input type='hidden' name='type' value='$type' />".
            "<input type='submit' value='$btn' /> ";

        if ($type != 'wait')
            $formtail.=
                "<input type='submit' name='suspend' value='$btn2' />";

        $formtail.= "</form>";

        $select = "<select name='type'>\n";
        foreach (array('ALL'=>'all', 'WAIT'=>'wait', 'DELETED'=>'del') as $k=>$v) {
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

    $extra = '';
    if ($allowed) {
        if ($type != 'monitor')
            $extra = '<a href="?action=userinfo&amp;&type=monitor" class="button"><span>'._("Contributors Monitor")."</span></a>";
        else
            $extra = '<a href="?action=userinfo" class="button"><span>'._("Suspended Users")."</span></a> ".
                    '<a href="?action=userinfo&amp;type=monitor" class="button"><span>'._("Refresh")."</span></a>";
    }

    return "<h2>".$title."</h2>\n".$formhead.$list.$formtail.$extra;
}

function do_userinfo($formatter,$options) {
    global $DBInfo;

    $user=&$DBInfo->user;

    $formatter->send_header('',$options);
    $allowed = $DBInfo->security_class == 'acl' &&
            $DBInfo->security->is_allowed($options['action'], $options);
    if ($allowed || in_array($user->id, (array) $DBInfo->owners)) {
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
                if ($type == 'del' || $type == 'wait' || $suspend)
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
