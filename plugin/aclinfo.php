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
        $ret = $DBInfo->security->get_acl('aclinfo',$options);
        if (is_array($ret)) {
            list($allowed, $denied, $protected) = $ret;
        }
    } else {
        $options['msg']=_("ACL is not enabled on this Wiki");
        do_invalid($formatter,$options);
        return;
    }

    $u = $DBInfo->user;
    if ($options['get'] > 0 && in_array($u->id, $DBInfo->owners)) {
        header('Content-Type: text/plain');
        if ($options['get'] == 1)
            $ac = new Cache_Text('aux_acl');
        else
            $ac = new Cache_Text('acl');
        $files = array();
        $ac->_caches($files, array('prefix'=>1));

        // prepare to return
        $ret = array();
        $retval = array();
        $ret['retval'] = &$retval;

        $acls = array();

        foreach ($files as $f) {
            // low level _fetch(), _remove()
            $info = $ac->_fetch($f, 0, $ret);
            if ($info === false) {
                $ac->_remove($f);
                continue;
            }

            foreach ($info as $g=>$types) {
                foreach ($types as $type=>$v) {
                    if (!is_array($v))
                        continue;
                    if (!isset($acls[$g]))
                        $acls[$g] = array();

                    $acls[$g][$retval['id']] = $g."\t".$type."\t".implode(',', $v);
                }
            }
        }

        foreach ($acls as $g=>$acl) {
            ksort($acl);
            foreach ($acl as $id=>$entry) {
                echo $id,"\t",$entry,"\n";
            }
        }

        return;
    }

    $formatter->send_header('',$options);
    $options['.title'] = sprintf(_("ACL Information of '%s'."), _html_escape($options['page']));

    if ($u->is_member) {
        if (method_exists($DBInfo->security, 'get_page_acl')) {
            $groups = array('@ALL', '@User');
            // FIXME
            foreach ($DBInfo->security->group as $group) {
                preg_match('/^(@[^\s]+)\s/', $group, $m);
                if (isset($m[1])) {
                    $groups[] = $m[1];
                }
            }

            // editable actions
            $actions = array('savepage', 'deletepage', 'info', 'diff', 'recall', 'revert');
            if (!empty($DBInfo->aclinfo_actions)) {
                $actions = $DBInfo->aclinfo_actions;
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST) && !empty($options['remove'])) {
                // remove ACL entry
                $msgs = array();
                $page = $options['value'];

                if (!empty($page)) {
                    $tmp = array_keys($options['remove']);
                    $group = $tmp[0];
                    if (in_array($group, $groups)) {
                        $acl = array($group=>null);
                        $DBInfo->security->add_page_acl($page, $acl);
                    }
                }
            } else if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST)) {
                $msgs = array();
                $page = $options['value'];
                $group = $options['group'];
                $type = $options['type'];
                $acts = (array)$options['act'];
                $ttl = (int)$options['ttl'];
                // check
                if (!in_array($group, $groups)) {
                    $msgs[] = _("Invalid ACL group name");
                }

                if (empty($type)) {
                    $type = 'deny';
                }
                if (!in_array($u->id, $DBInfo->owners)) {
                    $type = 'deny';
                    if (in_array($group, array('@ALL', '@Guest', '@User')))
                        $group = null;
                }

                if (!in_array($type, array('deny', 'allow'))) {
                    $msgs[] = _("Invalid ACL type");
                }
                if (empty($group)) {
                    $msgs[] = _("Empty ACL group");
                }
                if (empty($ttl)) {
                    $msgs[] = _("Empty TTL");
                }

                $acts = array_map('strtolower', $acts);
                $acl_actions = array_map('strtolower', $actions);
                // check actions
                $tmp = array();
                foreach ($acts as $act) {
                    if (in_array($act, $acl_actions))
                        $tmp[] = $act;
                }
                $acts = $tmp;

                if (!empty($msgs)) {
                    $options['title'] = implode(', ', $msgs);
                } else if (!empty($page) && !empty($group) && !empty($type) && !empty($acts) && !empty($ttl)) {
                    if ($ttl <= 365)
                        $ttl = $ttl * 60 * 60 * 24;
                    $param = array('ttl'=>$ttl);
                    $acl = array($group=>array($type=>$acts, 'ttl'=>$ttl, 'mtime'=>time()));
                    $DBInfo->security->add_page_acl($page, $acl, $param);
                } else {
                    $options['title'] = _("Fail to add ACL");
                }
            }

            $formatter->send_title('', '', $options);

            $retval = array();
            $opts = array('retval'=>&$retval);
            $acl = $DBInfo->security->get_page_acl($options['page'], $opts);
            if ($acl !== false) {
                $form_header = $form_footer = '';
                $form_th = '';
                if (!empty($retval['ttl'])) {
                    $form_header = '<form method="POST"><input type="hidden" name="action" value="aclinfo" />';
                    $form_header.= '<input type="hidden" name="value" value="'._html_escape($options['page']).'">';
                    $form_footer = '</form>';
                    $form_th = '<th>'._("Control").'</th>';
                }
                echo $form_header;
                echo '<table class="wiki"><tr><th style="white-space:nowrap">',_("ACL Group"),"</th><th>",
                     _("Type"),"</th><th>",_("Actions"),"</th>",$form_th,"</tr>\n";
                foreach ($acl as $group=>$entry) {
                    $ttl_time = '';
                    if (!empty($entry['ttl'])) {
                        $ttl = $entry['ttl'];
                        $mtime = $entry['mtime'];
                        $ttl = $ttl - (time() - $mtime);
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
                        else
                            $ttl_time = sprintf("%02d:%02d:%02d", $h, $m, $s);
                    }

                    foreach ($entry as $type=>$v) {
                        if (!is_array($v)) continue;
                        echo "<tr><th>",$group,"</th>";
                        echo '<th>',$type,'</th><td>', implode(', ', $v),'</td>';
                        if (!empty($form_th)) {
                            if (!empty($ttl_time))
                                echo '<td>',$ttl_time,' <input type="submit" name="remove['.$group.']" value="Delete" /></td>';
                            else
                                echo '<td></td>';
                        }
                        echo "</tr>\n";
                    }
                }
                echo '</table>',"\n";
                echo $form_footer;
            }

            $group_select = '<select name="group"><option>-- '._("Group").' --</option>';
            foreach ($groups as $g) {
                $selected = $g == '@ALL' ? ' selected="selected"' : '';
                $group_select.= '<option value="'.$g.'"'.$selected.'>'.$g.'</option>';
            }
            $group_select.= '</select>'."\n";

            $ttls = array(
                1800=>'30 minutes',
                3600=>'1 hour',
                7200=>'2 hours',
                10800=>'3 hours',
                21600=>'6 hours',
                43200=>'12 hours',
                1=>'1 day',
                2=>'2 days',
                7=>'7 days',
                30=>'1 month',
                365=>'1 year');

            $ttl_select = '<select name="ttl"><option>-- '._("TTL").' --</option>';
            foreach ($ttls as $time=>$str) {
                $ttl_select.= '<option value="'.$time.'">'.$str.'</option>';
            }
            $ttl_select.= '</select>'."\n";

            if (in_array($u->id, $DBInfo->owners)) {
                $type_select = '<select name="type"><option>-- '._("Type").' --</option>';
                $type_select.= '<option value="allow">allow</option>';
                $type_select.= '<option value="deny" selected="selected">deny</option>';
                $type_select.= '</select>';
            } else {
                $type_select = '<input type="hidden" name="type" value="deny" />deny';
            }

            $action_list = '';
            foreach ($actions as $act) {
                $action_list.= '<input type="checkbox" name="act[]" value="'.$act.'" checked="checked" />'.$act.' ';
            }

            $form = '<form method="POST">';
            $form.= '<input type="hidden" name="action" value="aclinfo" />';
            $form.= '<input type="hidden" name="value" value="'.
                _html_escape($options['page']).'" />';
            $form.= $group_select;
            $form.= $type_select;
            $form.= $action_list;
            $form.= $ttl_select;
            $form.= '<input type="submit" value="Add ACL" />';
            $form.= '</form>';
            echo $form;
        }
    } else {
        $formatter->send_title('', '', $options);
    }

    $test = false;
    if ($test && $u->is_member) {
        $params = array('page'=>$options['page'], 'id'=>'Anonymous');
        $ret = $DBInfo->security->get_acl('aclinfo', $params);
        if (is_array($ret)) {
            list($allowed, $denied, $protected) = $ret;
            $title = '<h2>'._("ACL Information of an Anonymous user.").'</h2>';
            show_acl_table($title, $allowed, $denied, $protected);
        }
    } else {
        $title = '<h2>'._("ACL Information.").'</h2>';
        show_acl_table($title, $allowed, $denied, $protected);
    }

    $formatter->send_footer('',$options);
    return;
}

function show_acl_table($title, $allowed, $denied, $protected) {
    if (empty($allowed))
        return;

    if (!empty($title))
        echo $title;

    echo '<table class="wiki"><tr><th>',
         _("Type"),"</th><th>",_("Actions"),"</th></tr>\n";
    echo '<tr><th>allow</th><td>';
    foreach ($allowed as $k=>$v)
        echo $k.'('.$v.'), ';
    echo '</td></tr>',"\n";
    echo '<tr><th>deny</th><td>';
    foreach ($denied as $k=>$v)
        echo $k.'('.$v.'), ';
    echo '</td></tr>',"\n";
    echo '<tr><th>protect</th><td>';
    echo implode(', ', $protected);
    echo '</td></tr>',"\n";
    echo '</table>',"\n";
}

// vim:et:sts=4:sw=4:
