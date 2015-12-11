<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a ip admin plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2015-10-14
// Name: IP Info plugin
// Description: IP (range) control plugin
// URL: MoniWiki:IpInfoPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[IpInfo]], ?action=userinfo
//

require_once(dirname(__FILE__).'/../lib/checkip.php');

function sort_ip_ranges($rules) {
    $ips = array();
    foreach ($rules as $ip) {
        $ip = trim($ip);
        $l = false;
        if (strpos($ip, '/') === false)
            $l = ip2long($ip);
        if ($l === false) {
            $tmp = normalize_network($ip);
            if ($tmp === false)
                // ignore
                continue;
            $l = sprintf("%u", ip2long($tmp[0]));
            $ips[$l] = array($ip, $tmp[0], $tmp[1]);
            continue;
        }
        $l = sprintf("%u", $l);
        $ips[$l] = array($ip, $ip, 32);
    }
    ksort($ips);
    return $ips;
}

function _ip_table($ips) {
    $ips = sort_ip_ranges($ips);
    $out = '<table class="wiki"><tr><th>IP or IP range</th><th>Count</th></tr>'."\n";
    $count = 0;
    foreach ($ips as $long=>$v) {
        $range = $v[0];
        $c = 1 << (32 - $v[2]);
        if ($c > 1) $c-= 2;
        $count+= $c;
        $out.= '<tr><td>'.$range.'</td><td>'.number_format($c).'</td></tr>'."\n";
    }
    $out.= '</table>'."\n";
    $out.= sprintf(_("Total %s Blocked IPs"), number_format($count));
    return $out;
}

function get_temporary_blocked_info($all = true) {
    $dec_octet   = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|[0-9])';
    $IPv4Address = "$dec_octet\\.$dec_octet\\.$dec_octet\\.$dec_octet";

    $retval = array();
    $ret = array('retval'=>&$retval);

    $infos = array();
    if ($all)
        $caches = array('abusefilter', 'ipblock');
    else
        $caches = array('ipblock');
    foreach ($caches as $cache) {
        // ip block cache
        $ac = new Cache_Text($cache);
        $files = array();
        $ac->_caches($files, array('prefix'=>1));

        foreach ($files as $f) {
            // low level _fetch(), _remove()
            $info = $ac->_fetch($f, 0, $ret);
            if ($info === false) {
                $ac->_remove($f);
                continue;
            }
            // ignore some old cache format
            if (!isset($info['id']))
                continue;
            // ignore internal purpose IP
            if ($info['id'] == '127.0.0.1')
                continue;

            if ($cache == 'abusefilter' && !preg_match("@^{$IPv4Address}(?:/\d+)?$@", $info['id']))
                continue;

            $info['ttl'] = $retval['ttl'];
            $info['mtime'] = $retval['mtime'];
            $infos[$info['id']] = $info;
        }
    }
    return $infos;
}

function get_temporary_blacklist($all = false) {
    $dec_octet   = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|[0-9])';
    $IPv4Address = "$dec_octet\\.$dec_octet\\.$dec_octet\\.$dec_octet";

    $retval = array();
    $ret = array('retval'=>&$retval);

    $infos = array();
    if ($all)
        $caches = array('abusefilter', 'ipblock');
    else
        $caches = array('ipblock');
    foreach ($caches as $cache) {
        // ip block cache
        $ac = new Cache_Text($cache);
        $files = array();
        $ac->_caches($files, array('prefix'=>1));

        foreach ($files as $f) {
            // low level _fetch(), _remove()
            $info = $ac->_fetch($f, 0, $ret);
            if ($info === false) {
                $ac->_remove($f);
                continue;
            }
            // ignore some old cache format
            if (!isset($info['id']))
                continue;
            // ignore internal purpose IP
            if ($info['id'] == '127.0.0.1')
                continue;

            if ($cache == 'abusefilter' && !preg_match("@^{$IPv4Address}(?:/\d+)?$@", $info['id']))
                continue;

            $infos[$info['id']] = $info;
        }
    }
    $blocklist = array_keys($infos);
    $blocked = make_ip_ranges($blocklist);
    return $blocked;
}

function get_cached_temporary_blacklist($all = false) {
    $pc = new Cache_text('persist', array('depth'=>0));
    $bc = new Cache_text('ipblock');
    $blocked = $pc->fetch('blacklist', $bc->mtime());
    if ($blocked === false) {
        $blocked = get_temporary_blacklist($all);
        $pc->update('blacklist', $blocked);
    }
    return $blocked;
}

function macro_IpInfo($formatter, $value = '', $params = array()) {
    global $Config;

    $list = '';
    if ($value == 'static') {
        $cache = new Cache_text('settings', array('depth'=>0));
        if (($ips = $cache->fetch('blacklist')) !== false) {
            $list = _ip_table($ips);
        }
    } else {
        $retval = array();
        $ret = array('retval'=>&$retval);

        $infos = array();
        if (!empty($params['info'])) {
            $infos[] = $params['info'];
        } else {
            if ($value == 'range')
                $range = true;
            else
                $range = false;
            $infos = get_temporary_blocked_info(!$range);
        }

        $listhead = '<table class="wiki editinfo">';
        $listhead .= '<tr><th>'._("IP or IP range").'</th><th>'._("Last updated").'</th>'.
                '<th>'._("Status").'</th><th>'._("Expire or Elapsed").'</th><th>'._("actions").'</th></tr>';
        $list = '';
        foreach ($infos as $info) {
            $ttl = $info['ttl'] - (time() - $info['mtime']);
            $tmp = $ttl;
            if ($ttl < 0)
                $tmp = time() - $info['mtime'];

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
            if ($ttl <= 0)
                $ttl_time = '<span style="color:gray">Permenent: '.$ttl_time.'</span>';
            else
                $ttl_time = $ttl_time;

            $anchor = 'a-'.substr(md5($info['id']), 0, 7);
            $list.= '<tr><td>';
            $list.= '<a name="'.$anchor.'"></a>';
            $list.= '<a href="?action=ipinfo&amp;q='.$info['id'].
                '"><span>'.$info['id'].'</span></a></td>';
            $list.= '<td>'.date('Y-m-d H:i:s', $info['mtime']).'</td>';
            $list.= '<th>'.($info['suspended'] ? '<span style="color:red">S</span>' : '').'</th>';
            $list.= '<th>'.$ttl_time.'</th>';
            $list.= '<td><a class="button-small" href="?action=ipinfo&amp;q='.$info['id'].
                '"><span>'._("Edit").'</span></a>';
            $list.= ' <a class="button-small" href="?action=ipinfo&amp;toggle=1&amp;q='.$info['id'].
                '"><span>'._("Toggle").'</span></a>';
            $list.= '</td></tr>';

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
        if (isset($list[0]))
            $list = $listhead.$list. '</table>';
    }
    return $list;
}

function do_ipinfo($formatter, $params = array()) {
    global $DBInfo, $Config;

    $u = $DBInfo->user;
    $list = '';
    $myip = '';
    $mask = 24;

    $masks = array(
        24,
        25,
        16,
        18,
        8);

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
        60=>'2 month',
        182=>'6 month',
        365=>'1 year');

    $reasons = array(
        100=>_("Vandalism"),
        150=>_("Abusing"),
        200=>_("Incompatible License"),
        210=>_("CCL BY"),
        220=>_("CCL NC"),
        300=>_("Discussion needed"),
        400=>_("Robot"),
        500=>_("Testing"),
    );

    if (!empty($params['q'])) {
        $tmp = normalize_network($params['q']);

        $myip = $params['q'];
        if ($tmp === false) {
            $params['msg'] = sprintf(_("Invalid IP Address %s"), $ip);
            $params['q'] = '';
        } else {
            $myip = $params['q'] = $ip = $tmp[0];
            if ($tmp[1] != 32)
                $netmask = $tmp[1];
        }
    }

    $control_action = false;
    if (!empty($params['q']) && isset($_POST) && !empty($params['button_block'])) {
        $control_action = 'block';
    } else if (!empty($params['q']) && !empty($params['toggle'])) {
        $control_action = 'toggle';
        $params['ttl'] = -1; // HACK to toggle block staus
    } else if (!empty($params['q']) && !empty($params['remove'])) {
        if (in_array($u->id, $DBInfo->owners)) {
            $control_action = 'remove';
        } else {
            $control_action = 'reset';
        }
        $params['ttl'] = -1; // HACK
    }

    while ($u->is_member && $control_action !== false) {
        // check parameters
        // TTL check

        if (!empty($params['reason']) and array_key_exists($params['reason'], $reasons))
            $reason = $params['reason'];

        if (!empty($params['comment']))
            $comment = $params['comment'];

        $ttl = !empty($params['ttl']) ? (int) $params['ttl'] : 1800; // default 30 minutes
        if (in_array($u->id, $DBInfo->owners)) {
            $ttl = !empty($params['ttl']) ? $params['ttl'] : 0; // default for owners
        } else {
            if ($ttl >= 60)
                $ttl = 1800;
        }
        if ($ttl < 0)
            $ttl = 1; // remove,toggle
        else if ($ttl <= 365) // days to seconds
            $ttl = $ttl * 60*60*24;

        if ($ttl >= 0 && !in_array($u->id, $DBInfo->owners)) {
            if (empty($comment) && empty($reason)) {
                $params['msg'] = _("Please select block reason");
                break;
            }
        }

        $netmask = !empty($params['netmask']) ? (int) $params['netmask'] : $netmask;
        if ($netmask >= 32) {
            $netmask = '';
        }

        $try = $ip;
        if (!empty($netmask))
            $try.= '/'.$netmask;

        $tmp = normalize_network($try);

        if ($tmp === false) {
            if (empty($netmask))
                $params['msg'] = sprintf(_("Not a valid IP address: %s"), $try);
            else
                $params['msg'] = sprintf(_("Not a valid IP range: %s"), $try);
        } else {
            // prepare to return
            $ret = array();
            $retval = array();
            $ret['retval'] = &$retval;

            if ($tmp[1] == 32) {
                // normalized IP
                $ip = $tmp[0];
                // abusefilter cache
                $arena = 'abusefilter';
                $ac = new Cache_Text('abusefilter');

                // fetch monitor information
                $info = $ac->fetch($ip, 0, $ret);
                if ($info === false) {
                    $new_info = array('create'=>0, 'delete'=>0, 'revert'=>0, 'save'=>0, 'edit'=>0,
                            'add_lines'=>0, 'del_lines'=>0, 'add_chars'=>0, 'del_chars'=>0);
                    $new_info['id'] = $ip;
                    $new_info['suspended'] = true;
                } else {
                    $new_info = $info;
                    $new_info['id'] = $ip;
                }
            } else {
                // normalized IP
                $ip = $tmp[0].'/'.$tmp[1];

                // ipblock cache
                $arena = 'ipblock';
                $ac = new Cache_Text('ipblock');

                // fetch monitor information
                $info = $ac->fetch($ip, 0, $ret);
                if ($info === false) {
                    $new_info['id'] = $ip;
                    $new_info['suspended'] = true;
                } else {
                    $new_info = $info;
                    $new_info['id'] = $ip;
                }
            }

            if (!empty($reason))
                $new_info['reason'] = $reason;
            if (!empty($comment)) {
                // upate comments
                $comments = array();
                if (!empty($info['comment']))
                    $comments = explode("\n", $new_info['comment']);

                $comments[] = date('Y-m-d H:i', time())."\t".$u->id."\t".$comment;
                if (sizeof($comments) > 100)
                    array_shift($comments);

                $new_info['comment'] = implode("\n", $comments);
            }

            if ($ttl == 1) {
                if ($control_action == 'reset')
                    $new_info['suspended'] = false;
                else if ($control_action == 'toggle')
                    $new_info['suspended'] = !$new_info['suspended'];

                $newttl = $retval['ttl'] - (time() - $retval['mtime']);
                if ($newttl < 0)
                    $newttl = 0;

                if ($control_action == 'remove')
                    $ac->remove($ip);
                else
                    $ac->update($ip, $new_info, $newttl);
            } else {
                $new_info['suspended'] = true;
                $ac->update($ip, $new_info, $ttl);
            }

            if ($control_action == 'toggle')
                $params['msg'] = sprintf(_("Successfully Toggle Block status: %s"), $try);
            else if ($control_action == 'reset')
                $params['msg'] = sprintf(_("Successfully Enable IP (range) status: %s"), $try);
            else if ($control_action == 'remove')
                $params['msg'] = sprintf(_("Successfully Removed IP range: %s"), $try);
            else if (!empty($netmask))
                $params['msg'] = sprintf(_("Successfully Blocked IP range: %s"), $try);
            else
                $params['msg'] = sprintf(_("Successfully Blocked IP address: %s"), $try);
        }
        break;
    }

    if (!empty($params['q']) && empty($params['button_block'])) {
        // search
        $retval = array();
        $ret = array('retval'=>&$retval);

        $try = $params['q'];
        $cache = 'abusefilter';
        if (!empty($netmask)) {
            $try.= '/'.$netmask;
            $cache = 'ipblock';
        }

        // try to find blocked IP or IP range
        $ac = new Cache_Text($cache);
        $info = $ac->fetch($try, 0, $ret);

        if ($info === false) {
            // get temporary blocked IP ranges
            $blocked = get_cached_temporary_blacklist();
            $res = search_network($blocked, $params['q'], $ret);

            $permenant = false;
            if ($res === false) {
                // search blacklist ranges
                $res = search_network($Config['ruleset']['blacklist.ranges'],
                    $params['q'], $ret);
                $permenant = true;
            }

            if ($res) {
                list($network, $netmask) = explode('/', $retval);
                if ($netmask == 32) {
                    $title = _("Temporary blocked IP (range) found").' : '.$network;
                } else {
                    $found = $retval;

                    if ($permenant) {
                        $title = _("Permenantly blocked IP range found").' : '. $found;
                        // show all temporary blocked list
                        $list = macro_IpInfo($formatter);
                    } else {
                        $title = _("Temporary blocked IP range found").' : '. $found;
                        // retrieve found
                        $ac = new Cache_Text('ipblock');
                        $info = $ac->fetch($found, 0, $ret);
                        if ($info !== false) {
                            $info['ttl'] = $retval['ttl'];
                            $info['mtime'] = $retval['mtime'];

                            $list = macro_IpInfo($formatter, '', array('info'=>$info));
                        }
                    }
                }
            } else {
                $title = _("IP (range) is not found");
                // show all temporary blocked list
                $list = macro_IpInfo($formatter);
            }
        } else {
            $info['ttl'] = $retval['ttl'];
            $info['mtime'] = $retval['mtime'];
            $list = macro_IpInfo($formatter, '', array('info'=>$info));
            $title = _("Temporary blocked IP found").' : '.$params['q'];
        }
    } else if ($u->is_member) {
        $opt = 'range';
        if (!empty($params['static']))
            $opt = 'static';
        if (!empty($params['all']))
            $opt = '';
        $list = macro_IpInfo($formatter, $opt);
    } else if (!$u->is_member) {
        $myip = $params['q'] = $_SERVER['REMOTE_ADDR'];
    }

    $params['.title'] = _("IP Information");
    if (!empty($title)) {
        $params['title'] = $title;
    } else if (!empty($params['q'])) {
        $params['title'] = sprintf(_("%s: IP Information"), $params['q']);
    }

    $formatter->send_header('', $params);
    $formatter->send_title($title,'', $params);

    $searchform = <<<FORM
<form method='post' action=''>
<label>Search</label>: <input type='text' name='q' value='$myip' placeholder='IP or IP range' />
<input type='submit' name='button_search' value='search' /><br />
<input type='hidden' name='action' value='ipinfo' />
</form>
FORM;

    echo '<h2>'._("Temporary blocked IPs").'</h2>',"\n";
    echo $searchform;
    if (isset($list[0])) {
        echo $list;
        echo $searchform;
    }

    // do not show control form
    if (!$u->is_member && !in_array($u->id, $DBInfo->owners)) {
        $formatter->send_footer('', $params);
        return;
    }

    echo '<h2>'._("Input IP or IP range").'</h2>',"\n";
    $mask_select = '<select name="netmask"><option value="">-- '._("Netmask").' --</option>'."\n";
    foreach ($masks as $m) {
        $selected = '';
        if ($m == $netmask)
            $selected = ' selected="selected"';
        $mask_select.= '<option value="'.$m.'"'.$selected.'>'."\n";
        $mask_select.= $m.' : ';
        $c = 1 << (32 - $m);
        if ($c > 1) $c-= 2;
        $mask_select.= number_format($c). ' IPs';
        $mask_select.= '</option>'."\n";
    }
    $mask_select.= '</select>'."\n";

    $ttl_select = '<select name="ttl"><option value="0">-- '._("Expire").' --</option>'."\n";
    foreach ($ttls as $time=>$str) {
        $ttl_select.= '<option value="'.$time.'">'.$str.'</option>'."\n";
    }
    $ttl_select.= '</select>'."\n";

    $reason_select = '<select name="reason"><option value="">-- '._("Block reason").' --</option>'."\n";
    foreach ($reasons as $code=>$str) {
        $reason_select.= '<option value="'.$code.'">'.$str.'</option>'."\n";
    }
    $reason_select.= '</select>'."\n";

    $ip_lab = _("IP Address");
    $net_lab = _("Netmask (i.e. 24)");
    $ttl_lab = _("Expire");
    $block_btn = _("Block IP");
    echo <<<FORM
<form method='post' action=''>
<table class='wiki'><tr><th>$ip_lab</th>
<th>$net_lab</th>
<th>$ttl_lab</th>
</tr>
<tr>
<td><input type='text' name='q' value='$myip' /></td>
<td>
$mask_select
</td>
<td>
$ttl_select
</td>
</tr>
<tr><td>$reason_select</td><td colspan='2'><input type='text' name='comment' size='50' /></td></tr>
</table>

FORM;
    echo <<<FORM
<input type='hidden' name='action' value='ipinfo' />
<input type='submit' name='button_block' value='$block_btn' />
</form>

FORM;

    $formatter->send_footer('', $params);
}

// vim:et:sts=4:sw=4:
