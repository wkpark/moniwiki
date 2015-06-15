<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a revert plugin for the MoniWiki
//
// Author: wkpark <wkpark@kldp.org>
// Since: 2015-06-16
// Name: Revoke plugin
// Description: Revoke Plugin
// PluginType: macro,action
// URL: MoniWiki:RevokePlugin
// Version: $Revision: 1.0 $
// Depend: 1.2.5
// License: GPLv2
//
// Usage: ?action=revoke

require_once(dirname(__FILE__).'/Stat.php');

function do_revoke($formatter, $options) {
    global $DBInfo;

    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            !$DBInfo->security->writable($options)) {
        $options['title'] = _("Page is not writable");
        return do_invalid($formatter, $options);
    }

    $is_new = false;
    if (!$formatter->page->exists()) $is_new = true;

    if ($is_new) {
        $formatter->send_header('', $options);
        $title = _("You can't revoke already deleted page.");
        $formatter->send_title($title, '', $options);
        $formatter->send_footer('', $options);
        return;
    }

    // check revocable
    $params = array();
    $retval = array();
    $params['retval'] = &$retval;
    macro_Stat($formatter, $value, $params);

    $is_ok = false;
    if ($retval['first_author'] == $options['id'] || in_array($options['id'], $DBInfo->members))
        $is_ok = true;

    // get the site specific hash code
    $ticket = $formatter->page->mtime().getTicket($DBInfo->user->id, $_SERVER['REMOTE_ADDR']);
    $hash = md5($ticket);

    $formatter->send_header('', $options);

    if ($is_ok && $_SERVER['REQUEST_METHOD'] == 'POST' && $hash == $options['hash']) {
        // simple comment check
        $comment = _stripslashes($options['comment']);
        $comment = trim($comment);
        $default = _("Revoke");
        if (isset($comment[0]) && ($p = strpos($comment, $default)) === 0) {
            $comment = substr($comment, strlen($default));
            $comment = trim($comment);
            $comment = ltrim($comment, ': ');
        }
        $options['comment'] = isset($comment[0]) ? $default.': '.$comment : $default;
        $options['.revoke'] = true;

        $ret = $DBInfo->deletePage($formatter->page, $options);
        if ($ret == -1) {
            if (!empty($options['retval']['msg']))
                $title = $options['retval']['msg'];
            else
                $title = sprintf(_("Fail to revoke \"%s\""), _html_escape($formatter->page->name));
        } else {
            $title = sprintf(_("\"%s\" is successfully revoked !"), _html_escape($formatter->page->name));
        }

        $formatter->send_title($title, '', $options);
        $formatter->send_footer('', $options);
        return;
    }

    $pagename = $formatter->page->name;
    $lab = _("Summary");

    if (!$is_ok) {
        $title = _("You are not the first author of this page or do not have enough revoke permission");
        $formatter->send_title($title, '', $options);

        $formatter->send_footer('',$options);
        return;
    }
    if ($retval['first_author'] == $options['id'])
        $title = _("You are the first author of this page");
    else
        $title = _("Do you want to revoke this page?");
    $formatter->send_title($title, '', $options);

    $comment = _("Revoke");
    print "<form method='post'>
    $lab : <input name='comment' size='80' value='$comment: ' /><br />\n";
    $btn=_("Revoke page");
    $msg = sprintf(_("Only WikiMaster can %s this page"), _("revoke"));
    if ($DBInfo->security->is_protected("revoke", $options))
        print _("Password").": <input type='password' name='passwd' size='20' value='' />
$msg<br />\n";
    print "
    <input type='hidden' name='action' value='revoke' />
    <input type='hidden' name='hash' value='$hash' />
    <input type='submit' value='$btn' />$extra
    </form>";

    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:sw=4:
