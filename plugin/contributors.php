<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a contributors plugin for the MoniWiki
//
// Author: wkpark <wkpark@kldp.org>
// Since: 2015-09-14
// Name: Contributors plugin
// Description: Show all contributors
// URL: MoniWiki:ContributorsPlugin
// Version: $Revision: 1.0 $
// Depend: 1.2.5
// License: GPLv2
//
// Usage: ?action=contributors

require_once(dirname(__FILE__).'/Stat.php');

function do_contributors($formatter, $options) {
    global $DBInfo, $Config;

    if (!$formatter->page->exists()) {
        $formatter->send_header('', $options);
        $title = _("Page not found.");
        $formatter->send_title($title, '', $options);
        $formatter->send_footer('', $options);
        return;
    }

    // get contributors
    $params = array();
    $retval = array();
    $params['retval'] = &$retval;

    if (!empty($DBInfo->version_class)) {
        $cache = new Cache_Text('infostat');

        if (!$formatter->refresh and $cache->exists($formatter->page->name)) {
            $retval = $cache->fetch($formatter->page->name);
        }

        if (empty($retval)) {
            $version = $DBInfo->lazyLoad('version', $DBInfo);
            $out = $version->rlog($formatter->page->name, '', '', '-z');

            $retval = array();
            if (!isset($out[0])) {
                $msg = _("No older revisions available");
                $info = "<h2>$msg</h2>";
            } else {
                $params = array();
                $params['all'] = 1;
                $params['id'] = $options['id'];
                $params['retval'] = &$retval;
                $ret = _stat_rlog($formatter, $out, $params);
            }

            if (!empty($retval))
                $cache->update($formatter->page->name, $retval);
        }
    }

    $formatter->send_header('', $options);
    $title = _("Contributors of this page");
    $formatter->send_title($title, '', $options);

    // do not check admin member users
    $user = $DBInfo->user;
    $ismember = $user->is_member;

    $total = count($retval['revs']);
    $total_lab = _("Total Revisions");

    $initial = $retval['rev'];
    $init_lab = _("Initial Revision");
    $contrib_lab = _("User");
    $edit_lab = _("Edit Count");
    echo <<<HEAD
<div class="wikiInfo">
<table class="wiki center">
<tr>
<th>$total_lab</th><th>$total</th>
</tr>
<tr>
<th>$init_lab</th><th>r$initial</th>
</tr>
<tr>
<th>$contrib_lab</th><th>$edit_lab</th>
</tr>
HEAD;
    $opt = intval($Config['mask_hostname']);

    // sort users
    $authors = array_keys($retval['users']);
    $edits = array();
    foreach ($authors as $n) {
        if ($retval['users'][$n]['edit'] > 0)
            $edits[$n] = $retval['users'][$n]['edit'];
    }

    // sort by edits
    arsort($edits);

    foreach ($edits as $u=>$c) {
        if (!$ismember && preg_match('/^([0-9]+\.){3}[0-9]+$/', $u)) {
            $u = _mask_hostname($u, $opt);
        } else {
            $u = $formatter->link_tag($formatter->page->urlname, '?action=userinfo&q='.$u, $u);
        }

        echo "<tr><td>",$u,"</td><td>", $c,"</td></tr>\n";
    }
    echo "</table></div>";

    $formatter->send_footer('', $options);
    return;
}

// vim:et:sts=4:sw=4:
