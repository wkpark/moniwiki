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
    if ($options['id'] == 'Anonymous' && !empty($options['q']) && empty($DBInfo->use_anonymous_editcount))
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

    if (empty($uid) and !empty($q))
        $uids = (array)$q;
    else
        $uids = $uid;

    if (empty($q) and !empty($uid) and sizeof($uid) == 1) {
        $q = $uid;
    }

    if ($limit > 100) $limit = 100;
    if ($pg > 1) $off+= ($pg - 1) * $limit;

    $params = array('offset' => $off, 'limit'=>$limit);
    $retval = array();
    $params['retval'] = &$retval;

    $udb=&$DBInfo->udb;
    $user=&$DBInfo->user;

    $members = $DBInfo->members;
    $ismember = $user->is_member;

    // set default query string
    if (!$ismember and empty($q)) {
        $q = $user->id;
        if ($q == 'Anonymous')
            $q = $_SERVER['REMOTE_ADDR'];
    }

    if (!empty($q) and empty($options['type'])) $type = 'all';

    $act = strtolower($act);
    $type = strtolower($type);

    $strs = array('all'=>_("Total %d users found."),
        'wait'=>_("Total %d Permanently Suspended users found."),
        'del'=>_("Total %d Deleted users found."));
    if (!in_array($type, array('wait', 'del', 'monitor'))) {
        $type = 'all';
    }

    if (!empty($q))
        $params['q'] = $q;
    if (!empty($type))
        $params['type'] = $type;

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

    $userinfo = '';
    $anchor = '';
    $extra = '';
    $cur = time();

    $min_ttl = !empty($DBInfo->user_suspend_time_default) ? intval($DBInfo->user_suspend_time_default) : 60*30;

    $allowed = $DBInfo->security_class == 'acl' &&
            $DBInfo->security->is_allowed($options['action'], $options);
    if (!$allowed)
        $allowed = in_array($user->id,$DBInfo->owners);

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

                    $ttl = $min_ttl;
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
                    if ($ttl < 60*30) {
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
                    $inc = max($min_ttl, $inc);

                    $ttl+= $act == 'inc' ? $inc : -intval($inc / 2);

                    if ($ttl < 60*10)
                        $ttl = 60*10;
                    else if ($ttl > 60*60*24*364)
                        $ttl = 60*60*24*364;
                } else if ($act == 'pause' || $act == 'block') {
                    $ttl+= $min_ttl; // pause and add minimum suspend time (default: 60*30)
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

            $anchor = 'a-'.substr(md5($info['id']), 0, 7);
            $list.= '<tr><td>';
            $list.= '<a name="'.$anchor.'"></a><input type="checkbox" name="uid[]" value="'.$info['id'].'" />';
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
                    $comment.= '<li>['.$date.'] '.$log.' --'.$by.'</li>'."\n";
                }
                $comment.= '</ul>';
                $list.= '<tr><td>&nbsp;</td><td colspan="7"><div class="msgboard">'.$comment.'</div></td></tr>';
            }
        }
        $list.= '</table>';

        $extra = '<ul>';
        $extra.= '<li>'.'<strong style="color:magenta">P</strong>'.':'._("Permanently Suspended").'</li>';
        $extra.= '<li>'.'<strong style="color:red">S</strong>'.':'._("Temporary Suspended").'</li>';
        $extra.= '</ul>';
    } else if ($sz == 1 && $allowed) {
        // abusefilter cache
        $ac = new Cache_Text('abusefilter');

        $actions = array();
        if (!empty($DBInfo->userinfo_actions) and is_array($DBInfo->userinfo_actions)) {
            $actions = $DBInfo->userinfo_actions;
        }

        $keys = array_keys($users);

        $hide_infos = array('bookmark', 'password', 'scrapped_pages', 'quicklinks', 'ticket', 'tz_offset');

        $inf = $udb->getInfo($keys[0], $type != 'all');
        unset($inf['eticket']); // hide eticket
        if ($ismember)
            $allowed_infos = array_keys($inf);
        else
            $allowed_infos = array('nick', 'home',
                'edit_count', 'edit_add_lines', 'edit_add_chars', 'edit_del_lines', 'edit_del_chars',
                'strike_total', 'strikeout_total');

        $addr = !empty($inf['remote']) ? $inf['remote'] : '';
        unset($inf['remote']);

        $anchor = '#a-'.substr(md5($keys[0]), 0, 7);

        $id_form = '';
        $ip_form = '';
        if (!empty($actions)) {
            $url = qualifiedUrl($formatter->link_url($formatter->page->urlname));
            $action_form = ' <form style="display:inline;margin:0" method="get" action="'.$url.'">';
            $action_form.= '<select name="action" onchange="if (this.selectedIndex != 0) this.form.submit();">';
            $action_form.= '<option value="">----</option>';
            foreach ($actions as $a) {
                $action_form.= '<option value="'.$a.'">'._($a)."</option>\n";
            }
            $id_form = $action_form.'<input type="hidden" name="q" value="'._html_escape($keys[0]).'">'.
                 "</select></form>\n";
            $ip_form = $action_form.'<input type="hidden" name="q" value="'.$addr.'">'.
                 "</select></form>\n";
        }

        $list = '<table class="info">';
        $list.= '<tr><th>'._("ID").'/'._("IP").'</th></th><td>'.$keys[0].$id_form.'</td></tr>';
        if (!empty($addr) and $keys[0] != $addr && !in_array($keys[0], $members))
            $list.= '<tr><th>'._("IP").'</th></th><td>'.$addr.$ip_form.'</td></tr>';

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
        $info = $ac->fetch($keys[0]);
        if ($info !== false && isset($info['suspended']) and $info['suspended'] == 'true') {
            $list.= '<tr><th>'._("Status").'</th><th style="color:red">'._("Temporary Suspended").'</th></tr>';
        }
        $list.= '</table>';
        $userinfo = $list;
        $list = '';

        if ($type == 'all')
            $btn = _("Delete User");
        else if ($type == 'del' or $type == 'wait')
            $btn = _("Activate User");

        $suspend_btn = _("Permanently Suspend User");
        if (!$ismember && $q == $user->id)
            $pause_btn = _("Temporary Suspend Me!");
        else if ($ismember)
            $pause_btn = _("Temporary Suspend User");

        $comment_btn = _("Comment");

        $formhead="<form method='POST' action=''>";
        $formtail='';
        if ($DBInfo->security->is_protected('userinfo',$options))
            $formtail= _("Password").
                ": <input type='password' name='passwd' /> ";
        $formtail.="<input type='hidden' name='action' value='userinfo' />";
        $formtail.="<input type='hidden' name='type' value='$type' />";
        $formtail.="<input type='hidden' name='uid' value='$keys[0]' />";

        // comments

        $mb = new Cache_Text('msgboard');

        if (($info = $mb->fetch($q, 0, $ret)) !== false) {
            if (!empty($info['comment'])) {
                $comments = explode("\n", $info['comment']);
                $comment = '<ul>';
                foreach ($comments as $c) {
                    list($date, $by, $log) = explode("\t", $c);
                    $comment.= '<li>['.$date.'] '.$log.' --'.$by.'</li>'."\n";
                }
                $comment.= '</ul>';

                $formtail.= '<div class="msgboard">'.$comment.'</div>';
            }
        }

        // send comment
        $formtail.=
            "<div>"._("Message").": <input type='text' name='comment' size='80' /> </div>";
        if (($ismember or $q == $user->id) && $type == 'all')
            $formtail.=
                "<span class='button'><input class='button' type='submit' name='pause' value='$pause_btn' /></span> ";
        if ($ismember && $type != 'wait')
            $formtail.=
                "<span class='button'><input class='button' type='submit' name='suspend' value='$suspend_btn' /></span> ";
        if ($ismember)
            $formtail.=
                "<span class='button'><input class='button' type='submit' value='$btn' /></span> ";

        $formtail.=
            "<span class='button'><input class='button' type='submit' name='comment_btn' value='$comment_btn' /></span> ";

        $formtail.= "</form>";

        // do not show form for non members
        //if (!$ismember)
        //    $formtail = $formhead = '';

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
            $extra.= '<a href="?action=userinfo&amp;type=monitor'.$anchor.'" class="button"><span>'._("Contributors Monitor")."</span></a>";
        else
            $extra.= '<a href="?action=userinfo" class="button"><span>'._("Permanently Suspended Users")."</span></a> ".
                    '<a href="?action=userinfo&amp;type=monitor" class="button"><span>'._("Refresh")."</span></a>";
    }

    return "<h2>".$title."</h2>\n".$userinfo.$formhead.$list.$formtail.$extra;
}

function do_userinfo($formatter,$options) {
    global $DBInfo;

    $user=&$DBInfo->user;

    $min_ttl = !empty($DBInfo->user_suspend_time_default) ? intval($DBInfo->user_suspend_time_default) : 60*30;

    $formatter->send_header('',$options);
    $allowed = $DBInfo->security_class == 'acl' &&
            $DBInfo->security->is_allowed($options['action'], $options);

    $ismember = $user->is_member;

    $suspend = !empty($options['suspend']) ? true : false;
    $pause = !empty($options['pause']) ? true : false;
    $comment_btn = !empty($options['comment_btn']) ? true : false;
    $comment = !empty($options['comment']) ? trim($options['comment']) : '';

    $uids = (array)$options['uid'];

    if ($user->id == 'Anonymous')
        $myid = $_SERVER['REMOTE_ADDR'];
    else
        $myid = $user->id;

    if (!$ismember && $allowed) {
        // not a member users
        $suspend = false;
        if (empty($comment))
            $comment_btn = false;
        else
            $comment_btn = true;

        // a normal user can pause himself
        if ((sizeof($uids) > 1) || $uids[0] != $myid)
            $pause = false;
        // reset type
        $options['type'] = '';
    }

    // cleanup comment
    $comment = strtr($comment, array("\n"=>' ', "\t"=>' '));
    $comment = _html_escape($comment);

    // FIXME only owners can delete/suspend users
    $can_delete_user = in_array($user->id, $DBInfo->owners);

    if ($allowed || $ismember) {
        if (isset($_POST) and empty($options['act']) and isset($options['uid'])) {

            $udb=&$DBInfo->udb;
            $type = !empty($options['type']) ? $options['type'] : '';
            if (!in_array($type, array('wait', 'del'))) {
                $type = '';
            }

            // normal user not allowed to suspend, delete user
            if (!$can_delete_user) {
                $suspend = false;
                $type = '';
            }

            $change = array();

            if ($can_delete_user and !$pause and !$comment_btn) {
                foreach ($uids as $uid) {
                    $uid=_stripslashes($uid);
                    if ($type == 'del' || $type == 'wait' || $suspend)
                        $ret = $udb->activateUser($uid, $suspend);
                    else
                        $ret = $udb->delUser($uid);
                    if ($ret)
                        $change[] = $uid;
                }
            } else if ($comment_btn and !empty($comment)) {
                $mb = new Cache_Text('msgboard');

                foreach ($uids as $uid) {
                    $info = $mb->fetch($uid, 0);

                    $ttl = 0;
                    if ($info === false) {
                        $info = array();
                        $info['comment'] = '';
                    }

                    // add comment
                    if (!empty($comment)) {
                        // upate comments
                        $comments = array();
                        if (!empty($info['comment']))
                            $comments = explode("\n", $info['comment']);

                        $comments[] = date('Y-m-d H:i', time())."\t".$myid."\t".$comment;
                        if ($uid == '127.0.0.1' and sizeof($comments) > 500)
                            array_shift($comments);
                        else if (sizeof($comments) > 1000)
                            array_shift($comments);

                        $info['comment'] = implode("\n", $comments);
                    }
                    $mb->update($uid, $info);
                    $change[] = $uid;
                }
            } else if (!empty($uids) && $pause) {
                // user can suspend temporary himself
                if ($ismember || (sizeof($uids) == 1) && $uid == $user->id)
                    $change = $uids;
            }

            if (!empty($change)) {
                $changed = implode(',',$change);
                if ($suspend)
                    $options['msg']= sprintf(_("User \"%s\" are suspended !"),_html_escape($changed));
                else if ($pause)
                    $options['msg']= sprintf(_("User \"%s\" are temporary suspended !"),_html_escape($changed));
                else if ($type == 'del' || $type == 'wait')
                    $options['msg']= sprintf(_("User \"%s\" are activated !"),_html_escape($changed));
                else if ($comment_btn)
                    $options['msg']= sprintf(_("Message added to \"%s\"."),_html_escape($changed));
                else
                    $options['msg']= sprintf(_("User \"%s\" are deleted !"),_html_escape($changed));
            }

            if (((!$suspend and ($type == 'del' || $type == 'wait')) or $pause) and !empty($change)) {
                // make users temporary suspdended 5-minutes
                // or temporary suspdended 30 minutes for newly suspended user
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

                        if ($pause)
                            $ttl = $min_ttl;
                        else
                            $ttl = 60*5;
                    } else {
                        $new_info = $info;
                        $ttl = $retval['ttl'] - (time() - $retval['mtime']);
                        $new_info['id'] = $q;

                        if ($pause)
                            $addttl = $min_ttl;
                        else
                            $addttl = 60*5;

                        if ($ttl < $addttl)
                            $ttl = $addttl;
                    }
                    $new_info['suspended'] = true;

                    // add comment
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
