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
    $uid = !empty($options['uid']) ? $options['uid'] : '';
    $type = !empty($options['type']) ? trim($options['type']) : 'wait';
    $act = !empty($options['act']) ? trim($options['act']) : '';
    $comment = !empty($options['comment']) ? trim($options['comment']) : '';

    if (!empty($q) and empty($options['type'])) $type = 'all';

    $act = strtolower($act);
    $type = strtolower($type);

    $strs = array('all'=>_("Total %d users found."),
        'wait'=>_("Total %d Permanently Suspended users found."),
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

    if (empty($act) and !empty($q)) {
        if ($udb->_exists($q)) {
            $type = 'all';
        } else if ($udb->_exists($q, true)) {
            $params['type'] = $type = 'wait';
        }
    }

    if (!empty($q) || $type != 'monitor') {
        $users=$udb->getUserList($params);
        $sz = sizeof($users);

        // not found anonymous IP address
        if ($sz == 0 and preg_match('@^(\d{1,3}\.){3}\d{1,3}$@', $q)) {
            $users = array();
            $users[$q] = time();
            $sz = 1;
        }
    }

    // HACK to make simple message board
    if (!empty($comment) and empty($q) and $type == 'monitor') {
        $q = '127.0.0.1';
        $sz = 1;
        $users[$q] = time();
    }

    if ($type != 'monitor') {
        $title = $strs[$type];
        $title = sprintf($title, $retval['count']);
    } else {
        $title = _("Contributors Monitor");
    }

    $list='';
    $extra = '';
    $cur = time();

    $allowed = $DBInfo->security_class == 'acl' &&
            $DBInfo->security->is_allowed($options['action'], $options);
    if (!$allowed)
        $allowed = in_array($user->id,$DBInfo->owners);

    $ismember = in_array($user->id, $DBInfo->members);

    if ($allowed && $type == 'monitor' && $ismember) {
        $suspend_btn = _("Temporary Suspend User");

        $formhead = "<form method='POST' action=''>";
        $formtail = '';
        if ($DBInfo->security->is_protected('userinfo', $options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.= "<input type='hidden' name='action' value='userinfo' />";
        $formtail.= "<input type='hidden' name='type' value='$type' />";
        $formtail.= "<input type='hidden' name='act' value='pause' />";
        $formtail.= _("Summary")." : <input type='text' size='80' name='comment' />";
        $formtail.=
            "<span class='button'><input class='button' type='submit' name='suspend' value='$suspend_btn' /></span> ";
        $formtail.= "</form>";

        // abusefilter cache
        $ac = new Cache_Text('abusefilter');

        // prepare to return
        $ret = array();
        $retval = array();
        $ret['retval'] = &$retval;

        if (empty($uid) and !empty($q))
            $uids = (array)$q;
        else
            $uids = $uid;

        if (!empty($uids) && in_array($act, array('inc', 'dec', 'reset', 'suspend', 'block', 'pause', 'clear'))) {
            if ($act == 'reset') {
                // clear abusefilter cache
                $msgid = _("%s: Reset editting information.");
            } else if ($act == 'inc') {
                // increse TTL
                $msgid = _("%s: Increse monitoring time period.");
            } else if ($act == 'dec') {
                // reduce TTL
                $msgid = _("%s: Decrese monitoring time period.");
            } else if ($act == 'pause') {
                // pause more
                $msgid = _("%s: Temporary pause 30 minutes.");
            } else if ($act == 'block') {
                // block
                $msgid = _("%s: Temporary Block IP address.");
            } else {
                // clear
                $msgid = _("%s: Clear Suspended state");
            }

            $change = array();

            foreach ($uids as $q) {
                // fetch monitor information
                $info = $ac->fetch($q, 0, $ret);
                $ttl = 0;
                if ($info === false) {
                    $suspended = false;
                    if ($udb->_exists($q, true)) {
                        $suspended = true;
                    }
                    $uinfo = $udb->getInfo($q, $suspended);

                    $new_info = array('create'=>0, 'delete'=>0, 'revert'=>0, 'save'=>0, 'edit'=>0,
                            'add_lines'=>0, 'del_lines'=>0, 'add_chars'=>0, 'del_chars'=>0);
                    $new_info['id'] = $q;
                    if (isset($uinfo['remote']))
                        $new_info['ip'] = $uinfo['remote'];

                    $ttl = 60*30;
                } else {
                    $new_info = $info;
                    $ttl = $retval['ttl'] - (time() - $retval['mtime']);
                    $new_info['id'] = $q;
                }

                if ($act == 'reset') {
                    // reset edit information
                    $new_info = array_merge($new_info, array('create'=>0, 'delete'=>0, 'revert'=>0, 'save'=>0, 'edit'=>0));
                    $new_info['suspended'] = false;
                } else if ($act == 'clear') {
                    // clear suspended state
                    $new_info['suspended'] = false;
                    $new_info['comment'] = '';
                } else if ($act == 'inc' || $act == 'dec') {
                    if ($ttl < 60*10) {
                        $inc = 60*30;
                    } else if ($ttl < 60*30) {
                        $inc = 60*30;
                    } else if ($ttl < 60*60) {
                        $inc = 60*60;
                    } else if ($ttl < 60*60*6) {
                        $inc = 60*60*6;
                    } else if ($ttl < 60*60*12) {
                        $inc = 60*60*12;
                    } else if ($ttl < 60*60*24) {
                        $inc = 60*60*24;
                    } else if ($ttl < 60*60*24*7) {
                        $inc = 60*60*24*7;
                    } else if ($ttl < 60*60*24*14) {
                        $inc = 60*60*24*14;
                    } else if ($ttl < 60*60*24*30) {
                        $inc = 60*60*24*30;
                    } else if ($ttl < 60*60*24*30*2) {
                        $inc = 60*60*24*30*2;
                    } else {
                        $inc = 60*60*24*30*6;
                    }

                    $ttl+= $act == 'inc' ? $inc : -intval($inc / 2);

                    if ($ttl < 60*10)
                        $ttl = 60*10;
                    else if ($ttl > 60*60*24*364)
                        $ttl = 60*60*24*364;
                } else if ($act == 'pause' || $act == 'block') {
                    $ttl+= 60*30; // pause and add 30 minutes
                    $new_info['suspended'] = true;
                    if (!empty($comment)) {
                        // add comment
                        $comments = array();
                        if (!empty($new_info['comment']))
                            $comments = explode("\n", $new_info['comment']);

                        $comments[] = date('Y-m-d H:i', time())."\t".$user->id."\t".$comment;
                        if ($q == '127.0.0.1' and sizeof($comments) > 10)
                            array_shift($comments);
                        else if (sizeof($comments) > 5)
                            array_shift($comments);

                        $new_info['comment'] = implode("\n", $comments);
                    }
                }

                $ac->update($q, $new_info, $ttl);
                $change[] = $q;
            }
            // make title
            $title = sprintf($msgid, implode(',', $change));
        }

        $files = array();
        $ac->_caches($files, array('prefix'=>1));

        $list = '<table class="wiki editinfo">';
        $list.= '<tr><th>'._("ID").'</th></th><th>'._("IP").'</th><th>'._("Last updated").'</th>'.
                '<th>'._("State").'</th>'.
                '<th colspan="2">'._("TTL").'</th><th>'._("Edits").'</th><th>'._("actions").'</th></tr>';
        foreach ($files as $f) {
            // low level _fetch(), _remove()
            $info = $ac->_fetch($f, 0, $ret);
            if ($info === false) {
                $ac->_remove($f);
                continue;
            }
            if (!isset($info['id']))
                continue;

            $ttl = $retval['ttl'] - (time() - $retval['mtime']);
            $tmp = $ttl;

            $d = intval($tmp / 60 / 60 / 24);
            $tmp-= $d * 60*60*24;
            $h = intval($tmp / 60 / 60);
            $tmp-= $h * 60*60;
            $m = intval($tmp / 60);
            $tmp-= $m * 60;
            $s = $tmp % 60;
            $ttl_time = '';
            if (!empty($d))
                $ttl_time = $d.' '._("days").' ';

            $ttl_time.= sprintf("%02d:%02d:%02d", $h, $m, $s);

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

            $tag = '';
            $permanently_suspended = $udb->_exists($info['id'], true);
            if ($permanently_suspended)
                $tag = '<span style="color:magenta">P</span>';

            $list.= '<tr><td>';
            $list.= '<input type="checkbox" name="uid[]" value="'.$info['id'].'" />';
            $list.= '<a href="?action=userinfo&amp;type=all&q='.$info['id'].
                '"><span>'.$info['id'].'</span></a></td>';
            if (isset($info['ip']) and $info['id'] != $info['ip'])
                $list.= '<td>'.$info['ip'].'</td>';
            else
                $list.= '<td>&nbsp;</td>';
            $list.= '<td>'.date('Y-m-d H:i:s', $retval['mtime']).'</td>';
            $list.= '<th>'.$tag.($info['suspended'] ? "<span style='color:red'>S</span>" : '').'</th>';
            $list.= '<th>'.$ttl_time.'</th>';
            $list.= '<td><a href="?action=userinfo&amp;type=monitor'.
                '&amp;act=inc&amp;q='.$info['id'].
                '"><span>&#9650;</span></a><br />';
            $list.= '<a href="?action=userinfo&amp;type=monitor'.
                '&amp;act=dec&amp;q='.$info['id'].
                '"><span>&#9660;</span></a>';
            $list.= '</td>';
            $list.= '<td><span class="editinfo">'.$out.'</span></td>';
            $list.= '<td>';
            $list.= '<a class="button-small" href="?action=userinfo&amp;type=monitor'.
                '&amp;act=pause&amp;q='.$info['id'].
                '"><span>'._("Suspend").'</span></a> ';
            $list.= '<a class="button-small" href="?action=userinfo&amp;type=monitor'.
                '&amp;act=reset&amp;q='.$info['id'].
                '"><span>'._("Reset").'</span></a> ';
            $list.= '<a class="button-small" href="?action=userinfo&amp;type=monitor'.
                '&amp;act=clear&amp;q='.$info['id'].
                '"><span>'._("Clear").'</span></a> ';
            $list.= '<a class="button-small" href="?action=userinfo&amp;type=monitor'.
                '&amp;act=block&amp;q='.$info['ip'].
                '"><span>'._("Block IP").'</span></a> ';
            $list.= '</td>';
            $list.= '</tr>';

            if (!empty($info['comment'])) {
                $comments = explode("\n", $info['comment']);
                $comment = '<ul>';
                foreach ($comments as $c) {
                    list($date, $by, $log) = explode("\t", $c);
                    $comment.= '<li>'.$date.' '.$log.' --'.$by.'</li>'."\n";
                }
                $comment.= '</ul>';
                $list.= '<tr><td colspan="7">'.$comment.'</td></tr>';
            }
        }
        $list.= '</table>';

        $extra = '<ul>';
        $extra.= '<li>'.'<strong style="color:magenta">P</strong>'.':'._("Permanently Suspended").'</li>';
        $extra.= '<li>'.'<strong style="color:red">S</strong>'.':'._("Temporary Suspended").'</li>';
        $extra.= '</ul>';
    } else if ($sz == 1 && $allowed) {
        $keys = array_keys($users);
        $hide_infos = array('bookmark', 'password', 'scrapped_pages', 'quicklinks', 'ticket', 'tz_offset');

        $inf = $udb->getInfo($keys[0], $type != 'all');
        if ($ismember)
            $allowed_infos = array_keys($inf);
        else
            $allowed_infos = array('nick', 'home',
                'edit_count', 'edit_add_lines', 'edit_add_chars', 'edit_del_lines', 'edit_del_chars',
                'strike_total', 'strikeout_total');

        $addr = !empty($inf['remote']) ? $inf['remote'] : '';

        $list = '<table>';
        $list.= '<tr><th>'._("ID").'/'._("IP").'</th></th><td>'.$keys[0].'</td></tr>';
        if (!empty($DBInfo->use_avatar) && !empty($addr) && !empty($DBInfo->use_uniq_avatar)) {
            $avatar_type = 'identicon';
            if (is_string($DBInfo->use_avatar))
                $avatar_type = $DBInfo->use_avatar;
            $avatarlink = qualifiedUrl($formatter->link_url('', '?action='. $avatar_type .'&amp;seed='));

            $uniq_avatar = $DBInfo->use_uniq_avatar;
            if ($ismember)
                $uniq_avatar = 'Y'; // change avatar after year :>

            $key = $addr . $uniq_avatar;
            if (!$ismember) $key.= $q; // not a member: show different avatar for login user
            $crypted = md5($key);
            $mylnk = preg_replace('/seed=/', 'seed='.$crypted, $avatarlink);

            // for user defined avatar
            $mylnk.= '&amp;user='.$q;
            $list.= '<tr><th>'._("Avatar").'</th></th><td><img src="'.$mylnk.'" /></td></tr>';
        }
        foreach ($allowed_infos as $k) {
            if (!in_array($k, $hide_infos) and !empty($inf[$k]))
                $list.= '<tr><th>'.$k.'</th><td>'.$inf[$k].'</td></tr>';
        }
        $list.= '</table>';

        if ($type == 'all')
            $btn = _("Delete User");
        else if ($type == 'del' or $type == 'wait')
            $btn = _("Activate User");

        $suspend_btn = _("Permanently Suspend User");
        $pause_btn = _("Temporary Suspend User");

        $formhead="<form method='POST' action=''>";
        $formtail='';
        if ($DBInfo->security->is_protected('userinfo',$options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.="<input type='hidden' name='action' value='userinfo' />";
        $formtail.="<input type='hidden' name='type' value='$type' />";
        $formtail.="<input type='hidden' name='uid' value='$keys[0]' />";
        if ($type == 'all')
            $formtail.=
                '<a href="?action=userinfo&amp;type=monitor&amp;act=pause&amp;q='.
                    $keys[0].'" class="button"><span>'.$pause_btn."</span></a> ";
        if ($type != 'wait')
            $formtail.=
                "<span class='button'><input class='button' type='submit' name='suspend' value='$suspend_btn' /></span> ";
        $formtail.=
            "<span class='button'><input class='button' type='submit' value='$btn' /></span> ";

        $formtail.= "</form>";

        // do not show form for non members
        if (!$ismember)
            $formtail = $formhead = '';

    } else if ($allowed && $ismember) {
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
            $btn2 = _("Permanently Suspend Users");

        if ($DBInfo->security->is_protected('userinfo',$options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.="<input type='hidden' name='action' value='userinfo' />".
            "<input type='hidden' name='type' value='$type' />".
            "<span class='button'><input class='button' type='submit' value='$btn' /></span> ";

        if ($type != 'wait')
            $formtail.=
                "<span class='button'><input class='button' type='submit' name='suspend' value='$btn2' /></span> ";

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

    if ($allowed && $ismember) {
        if ($type != 'monitor')
            $extra.= '<a href="?action=userinfo&amp;type=monitor" class="button"><span>'._("Contributors Monitor")."</span></a>";
        else
            $extra.= '<a href="?action=userinfo" class="button"><span>'._("Permanently Suspended Users")."</span></a> ".
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
        if (isset($_POST) and empty($options['act']) and isset($options['uid'])) {
            $uids = (array)$options['uid'];

            $udb=&$DBInfo->udb;
            $type = !empty($options['type']) ? $options['type'] : '';
            if (!in_array($type, array('wait', 'del'))) {
                $type = '';
            }
            $suspend = !empty($options['suspend']) ? true : false;
            $pause = !empty($options['pause']) ? true : false;

            $change = array();
            foreach ($uids as $uid) {
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
                else if ($pause)
                    $options['msg']= sprintf(_("User \"%s\" are temporary suspended !"),_html_escape($changed));
                else if ($type == 'del' || $type == 'wait')
                    $options['msg']= sprintf(_("User \"%s\" are activated !"),_html_escape($changed));
                else
                    $options['msg']= sprintf(_("User \"%s\" are deleted !"),_html_escape($changed));
            }

            if (!$suspend and !empty($change) and ($type == 'del' || $type == 'wait')) {
                // make users temporary suspdended 5-minutes
                // abusefilter cache
                $ac = new Cache_Text('abusefilter');

                // prepare to return
                $ret = array();
                $retval = array();
                $ret['retval'] = &$retval;

                foreach ($change as $q) {
                    // fetch monitor information
                    $info = $ac->fetch($q, 0, $ret);
                    $ttl = 0;
                    if ($info === false) {
                        $new_info = array('create'=>0, 'delete'=>0, 'revert'=>0, 'save'=>0, 'edit'=>0,
                                'add_lines'=>0, 'del_lines'=>0, 'add_chars'=>0, 'del_chars'=>0);
                        $new_info['id'] = $q;

                        $ttl = 60*5;
                    } else {
                        $new_info = $info;
                        $ttl = $retval['ttl'] - (time() - $retval['mtime']);
                        $new_info['id'] = $q;
                        if ($ttl < 60*5)
                            $ttl = 60*5;
                    }
                    $new_info['suspended'] = true;
                    $ac->update($q, $new_info, $ttl);
                }
            }
        }
        $list= macro_UserInfo($formatter,'',$options);
    } else {
        $options['msg']= sprintf(_("You are not allowed to \"%s\" !"),"userinfo");
        $list='';
    }

    $options['.title'] = _("User Information");

    $formatter->send_title('','',$options);
    print $list;
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
