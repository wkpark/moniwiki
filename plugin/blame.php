<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a rcs blame action plugin for the MoniWiki
//
// Author: wkpark <wkpark@kldp.org>
// Date: 2013-05-06
// Name: Blame plugin
// Description: Blame Plugin
// PluginType: action
// URL: MoniWiki:BlamePlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=blame&rev=1.xx
//

function do_blame($formatter, $params) {
    global $DBInfo;

    $rev = '';
    $option = '';
    if (!empty($params['rev'])) {
        if (preg_match('/^\d\.\d+$/', $params['rev'])) {
            $rev = $params['rev'];
            $option = ' -r'.$rev;
        }
    }

    if (!$formatter->page->exists()) {
        $params['msg'] = _("Error: Page Not found !");
        do_invalid($formatter, $params);
        return;
    }

    $formatter->send_header('', $params);

    if (isset($rev[0]))
        $params['.title'] = sprintf(_("Blame r%s"), $rev);
    else
        $params['.title'] = sprintf(_("Blame of %s page"), _html_escape($formatter->page->name));

    $key = $DBInfo->getPageKey($formatter->page->name);

    // FIXME call blame
    $fp = popen("blame -x,v/ $option ".$key, 'r'); //.' '.$formatter->NULL, 'r');

    $out = '';
    if (is_resource($fp)) {
        while (!feof($fp)) {
            $line = fgets($fp, 2048);
            $out.= $line;
        }
        pclose($fp);
    }

    $formatter->send_title($title, '', $params);

    $lines = explode("\n", $out);
    $end = array_pop($lines);
    if ($end != '') array_push($lines, $end);

    $u = &$DBInfo->user;
    $is_member = $u->is_member;
    // members
    $members = $DBInfo->members;
    // check modified blame or not
    if (($p = strpos($lines[0], "\t")) !== false && $p < 23) {
        $sep = "@\t@";
        $count = 5;
    } else {
        $sep = "@\s+@";
        $count = 4;
    }

    $ipicon = '<img src="'.$DBInfo->imgs_dir.'/misc/ip.png" />';
    if (!empty($DBInfo->use_avatar)) {
        if (is_string($DBInfo->use_avatar))
            $type = $DBInfo->use_avatar;
        else
            $type = 'identicon';
        $avatarlink = qualifiedUrl($formatter->link_url('', '?action='. $type .'&amp;seed='));
    }

    echo '<div class="wikiBlame"><table>';
    $ov = '';
    $alts = array('', ' alt');
    $j = 0;
    $ii = 1;
    $blame_url = $formatter->link_url($formatter->page->urlname, '?action=blame&rev=');
    foreach ($lines as $line) {
        $tmp = preg_split($sep, $line, $count);
        $v = trim($tmp[0]);
        if ($count == 4) {
            $u = trim($tmp[1], '(');
            $t = trim($tmp[2], '):');
            $l = $tmp[3];
        } else {
            $ip = $tmp[1];
            $u = $tmp[2];
            $t = $tmp[3];
            $l = $tmp[4];

            if (!empty($DBInfo->use_avatar)) {
                $crypted = crypt($ip, $ip);
                $mylnk = preg_replace('/seed=/', 'seed='.$crypted, $avatarlink);
                $avatar = '<img src="'.$mylnk.'" style="width:16px;height:16px;vertical-align:middle" alt="avatar" />';
            } else {
                $avatar = '';
            }
            if ($u == 'Anonymous') {
                if (!$is_member) {
                    $avatar.$u = _mask_hostname($ip, 2);
                } else {
                    if (isset($DBInfo->interwiki['Whois']))
                        $wip = "<a href='".$DBInfo->interwiki['Whois']."$ip' target='_blank'>$ipicon</a>";
                    else
                        $wip = "<a href='?action=whois&amp;q=".$ip."' target='_blank'>$ipicon</a>";
                    $u = $ip;

                    if (!empty($DBInfo->use_admin_user_url))
                        $u = '<a href="'.$DBInfo->use_admin_user_url.$u.'">'.$u.'</a>';
                    $u = $avatar.$u.$wip;
                }
            } else {
                if (isset($DBInfo->interwiki['Whois']))
                    $wip = "<a href='".$DBInfo->interwiki['Whois']."$ip' target='_blank'>$ipicon</a>";
                else
                    $wip = "<a href='?action=whois&amp;q=".$ip."' target='_blank'>$ipicon</a>";

                if ($is_member) {
                    if (!in_array($u, $members)) {
                        $u = $avatar.$u.$wip;
                    } else {
                        $u = $avatar.$u;
                    }
                } else {
                    $u = $avatar.$u;
                }
            }
            $t = date('y-m-d', $t);
        }
        if ($ov != $v)
            $alt = $alts[++$j % 2];
        else
            $alt = '';
        $link = '<a href="'.$blame_url.$v.'">'.$v.'</a>';
        echo '<tr><td class="version'.$alt.'">r'.$link,'</td> <td class="author'.$alt.'">', "$u",
            '</td> <td class="date'.$alt.'">', $t,'</td><td class="line">'.$ii.'</td><td class="src'.$alt.'">'.str_replace('<', '&lt', $l).'</td></tr>';
        $ov = $v;
        $ii++;
    }
    echo '</table></div>';

    $formatter->send_footer('', $params);
    return;
}

// vim:et:sts=4:sw=4:
