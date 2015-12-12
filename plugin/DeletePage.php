<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a DeletePage plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-04-12
// Name: DeletePage plugin
// Description: show DeletePage form
// URL: MoniWiki:DeletePagePlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=deletefile
//

function do_post_DeletePage($formatter,$options) {
    global $DBInfo;

    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            !$DBInfo->security->writable($options)) {
        $options['title'] = _("Page is not writable");
        return do_invalid($formatter,$options);
    }

    $page = $DBInfo->getPage($options['page']);

    $not_found = !$page->exists();

    if ($not_found && !in_array($options['id'], $DBInfo->owners)) {
        $formatter->send_header('', $options);
        $title = _("Page not found.");
        $formatter->send_title($title, '',$options);
        $formatter->send_footer('', $options);
        return;
    }

    // check full permission to edit
    $full_permission = true;
    if (!empty($DBInfo->no_full_edit_permission) or
            ($options['id'] == 'Anonymous' && !empty($DBInfo->anonymous_no_full_edit_permission)))
        $full_permission = false;

    // members always have full permission to edit
    if (in_array($options['id'], $DBInfo->members))
        $full_permission = true;

    if (!$full_permission) {
        $formatter->send_header('', $options);
        $title = _("You do not have full permission to delete this page on this wiki.");
        $formatter->send_title($title, '',$options);
        $formatter->send_footer('', $options);
        return;
    }

    // get the site specific hash code
    $ticket = $page->mtime().getTicket($DBInfo->user->id, $_SERVER['REMOTE_ADDR']);
    $hash = md5($ticket);

    if (isset($options['name'][0])) $options['name']=urldecode($options['name']);
    $pagename= $formatter->page->urlname;
    if (isset($options['name'][0]) and $options['name'] == $options['page']) {
        $retval = array();
        $options['retval'] = &$retval;

        $ret = -1;
        // check hash
        if (empty($options['hash']))
            $ret = -2;
        else if ($hash == $options['hash'])
            $ret = $DBInfo->deletePage($page, $options);
        else
            $ret = -3;

        if ($ret == -1) {
            if (!empty($options['retval']['msg']))
                $title = $options['retval']['msg'];
            else
                $title = sprintf(_("Fail to delete \"%s\""), _html_escape($page->name));
        } else if ($ret == -2) {
            $title = _("Empty hash code !");
        } else if ($ret == -3) {
            $title = _("Incorrect hash code !");
        } else {
            $title = sprintf(_("\"%s\" is deleted !"), _html_escape($page->name));
        }

        $myrefresh='';
        if (!empty($DBInfo->use_save_refresh)) {
            $sec=$DBInfo->use_save_refresh - 1;
            $lnk=$formatter->link_url($formatter->page->urlname,"?action=show");
            $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
        }
        $formatter->send_header($myrefresh,$options);

        $formatter->send_title($title,"",$options);
        $formatter->send_footer('',$options);
        return;
    } else if (isset($options['name'][0])) {
        #print $options['name'];
        $options['msg'] = _("Please delete this file manually.");
    }
    $title = sprintf(_("Delete \"%s\" ?"), $page->name);
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $btn = _("Summary");
    echo "<form method='post'>\n";
    if ($not_found)
        echo _("Page already deleted.").'<br />';
    else
        echo "$btn: <input name='comment' size='80' value='' /><br />\n";
    if (!empty($DBInfo->delete_history) && in_array($options['id'], $DBInfo->owners))
        print _("with revision history")." <input type='checkbox' name='history' />\n";
    print "\n<input type=\"hidden\" name=\"hash\" value=\"".$hash."\" />\n";

    $pwd = _("Password");
    $btn = _("Delete Page");
    $msg = _("Only WikiMaster can delete this page");
    if ($DBInfo->security->is_protected("DeletePage",$options))
        print "$pwd: <input type='password' name='passwd' size='20' value='' />
            $msg<br />\n";
    print "
        <input type='hidden' name='action' value='DeletePage' />
        <input type='hidden' name='name' value='$pagename' />
        <span class='button'><input type='submit' class='button' value='$btn' /></span>
        </form>";
    #  $formatter->send_page();
    $formatter->send_footer('',$options);
}

// vim:et:sts=4:sw=4:
