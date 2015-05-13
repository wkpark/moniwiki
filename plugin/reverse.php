<?php
// Copyright 2010 Apple.D
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Apple.D
// Date: 2010-01-01
// Name: Revert plugin
// Description: a enha version of the Revert Plugin
// URL: to_plugin url/interwiki name etc.
// Version: $Revision: 1.0 $
// License: GPL
//

function do_reverse($formatter, $options = array()) {
    global $DBInfo;

    $page = $DBInfo->getPage($options['page']);

    $pagename = $formatter->page->urlname;

    // validate rev
    if (!empty($options['rev'])) {
        $info = $formatter->page->get_info($options['rev']);
        if (empty($info[0])) {
            unset($options['rev']);
            if (!empty($_POST['rev'])) unset($_POST['rev']);
        }
    }

    if (!empty($pagename) and $pagename == $options['name'] and !empty($options['rev'])) {
        $formatter->page->body = $formatter->page->get_raw_body($options);
        $ret = $DBInfo->savePage($formatter->page,
                sprintf(_("Rollback to revision %s"), $options['rev']).
                (!empty($options['comment']) ? ': '.$options['comment'] : ''), $options);

        if ($ret != -1) {
            $title = sprintf(_("%s is successfully rollbacked."), $page->name);
        } else {
            $title = sprintf(_("Failed to rollback %s page"), $page->name);
        }
        $formatter->send_header('', $options);
        $formatter->send_title($title, '', $options);
        $formatter->send_footer('', $options);
        return;
    }

    if (empty($options['rev'])) {
        $title = _("Please select the target revision first.");
        $formatter->send_header('', $options);
        $formatter->send_title($title, '', $options);
        $formatter->send_footer('', $options);
        return;
    }

    $title = sprintf(_("Are you sure to revert %s page to %s revision ?"), $options['page'], $options['rev']);

    $formatter->send_header('', $options);
    $formatter->send_title($title, '', $options);	

    $msg = _("Summary");
    $ok = _("OK");
    print "<form method='post'><span>$msg: </span><input name='comment' size='80' maxlength='80' value='' /><input type='hidden' name='action' value='reverse' /><input type='hidden' name='rev' value='".$options['rev']."' /><input type='hidden' name='name' value='".$pagename."' /><input type='submit' value='$ok' /></form>";

    $formatter->send_footer('', $options);
}

// vim:et:sts=4:sw=4:
