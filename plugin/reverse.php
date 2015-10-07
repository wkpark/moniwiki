<?php
// Copyright 2010 Apple.D
// All rights reserved. Distributable under GPL see COPYING
// a revert plugin for the MoniWiki
//
// Author: Apple.D
// Since: 2010-01-01
// Name: Revert plugin
// Description: a enha version of the Revert Plugin
// URL: MoniWiki:ReversePlugin
// Version: $Revision: 1.1 $
// License: GPLv2
//
// Usage: ?action=reverse&rev=1.100

function do_reverse($formatter, $options = array()) {
    global $DBInfo;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            !$DBInfo->security->writable($options)) {
        $options['title'] = _("Page is not writable");
        return do_invalid($formatter,$options);
    }

    // check full permission to edit
    $full_permission = true;
    if (!empty($DBInfo->no_full_edit_permission) or
            ($options['id'] == 'Anonymous' && !empty($DBInfo->anonymous_no_full_edit_permission)))
        $full_permission = false;

    // members always have full permission to edit
    if (in_array($options['id'], $DBInfo->members))
        $full_permission = true;

    $is_new = false;
    if (!$formatter->page->exists()) $is_new = true;

    if (!$is_new and !$full_permission) {
        $formatter->send_header('', $options);
        $title = _("You do not have full permission to rollback this page on this wiki.");
        $formatter->send_title($title, '',$options);
        $formatter->send_footer('', $options);
        return;
    }

    $pagename = $formatter->page->urlname;

    $force = 1;
    if (isset($_POST['rev'][0]) && $DBInfo->hasPage($options['page'])) {
        $force = 0;
        if ($_POST['force']) $force = 1;
    }

    // validate rev
    $rev = isset($_POST['rev'][0]) ? $_POST['rev'] : $options['rev'];
    if (!empty($rev)) {
        $info = array();
        if (preg_match('/^[a-zA-Z0-9\.]+$/', $rev))
            $info = $formatter->page->get_info($rev);
        if (empty($info[0])) {
            // no version found
            unset($rev);
            unset($options['rev']);
            unset($_POST['rev']);
        }
    }

    // check ticket
    $ticket = getTicket($formatter->page->mtime().$options['id'].$_SERVER['REMOTE_ADDRESS']);
    if ($force and !empty($pagename) and !empty($_POST['rev']) and $ticket == $options['ticket']) {
        // simple comment check
        $comment = trim($options['comment']);
        $default = sprintf(_("Rollback to revision %s"), $rev);
        if (isset($comment[0]) && ($p = strpos($comment, $default)) === 0) {
            $comment = substr($comment, strlen($default));
            $comment = trim($comment);
            $comment = ltrim($comment, ': ');
        }
        $comment = isset($comment[0]) ? $default.': '.$comment : $default;

        // get current revision
        $current_body = $formatter->page->_get_raw_body();
        // get old revision
        $body = $formatter->page->get_raw_body($options);

        if ($body == $current_body) {
            $title = sprintf(_("No change found."));
        } else if ($body == '') {
            $title = sprintf(_("Empty Page!"));
        } else {
            $options['.reverted'] = 1;
            $formatter->page->write($body);
            $ret = $DBInfo->savePage($formatter->page, $comment, $options);
            if ($ret != -1) {
                $title = sprintf(_("%s is successfully rollbacked."), _html_escape($page->name));
            } else {
                $title = sprintf(_("Failed to rollback %s page"), _html_escape($page->name));
            }
        }
        $formatter->send_header('', $options);
        $formatter->send_title($title, '', $options);
        $formatter->send_footer('', $options);
        return;
    }

    $extra = '';
    if (empty($options['rev']))
        $title = _("Please select old revision to revert.");
    else {
        if ($DBInfo->hasPage($formatter->page->name)) {
            if ($_POST['rev'])
                $title = sprintf(_("Please check force overwrite to revert %s revision."),
                    $rev);
            else
                $title = sprintf(_("Are you really want to overwrite %s page to %s revision ?"),
                    $options['page'], $rev);
            $extra = '<input type="checkbox" name="force" />'._("Force overwrite").'<br />';
        } else {
            $title = sprintf(_("Are you really want to revert %s page to %s revision ?"),
                $options['page'], $rev);
        }
    }

    $formatter->send_header('', $options);
    $formatter->send_title($title, '', $options);	

    if ($rev) {
        $msg = _("Summary");
        $btn = _("Revert page");
        $comment = sprintf(_("Rollback to revision %s"), $rev);
        $hidden = '<input type="hidden" name="ticket" value="'.$ticket.'" />';
        echo "<form method='post'>\n",
            "<span>$msg: </span><input name='comment' size='80' maxlength='80' value='$comment: ' />\n",
            "<input type='hidden' name='action' value='reverse' />\n",
            "<input type='hidden' name='rev' value='".$rev."' />\n",
            $hidden,
            "<br /><input type='submit' value='$btn' />$extra\n",
            "</form>";
    }

    $params = array();
    $params['page'] = $options['page'];
    $params['info_actions'] = array('recall'=>'view','reverse'=>'revert');
    $params['title'] = '<h3>'.
        sprintf(_("Old Revisions of the %s"),_html_escape($formatter->page->name)).'</h3>';
    echo $formatter->macro_repl('Info', '', $params);

    $formatter->send_footer('', $options);
}

// vim:et:sts=4:sw=4:
