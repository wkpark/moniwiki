<?php
// Copyright 2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a OrphanedPages plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2010-10-13
// Name: Orphaned Pages Plugin
// Description: Show Orphaned Pages of this Wiki
// URL: MoniWiki:OrphanedPagesPlugin
// Version: $Revision$
// License: GPL
//
// Usage: [[OrphanedPages]]
//
// $Id$

function macro_OrphanedPages($formatter,$value, $params = array()) {
    global $DBInfo;

    $pagelinks = $formatter->pagelinks; // save
    $save = $formatter->sister_on;
    $formatter->sister_on = 0;

    $pages = $DBInfo->getPageLists();
    $orphaned = array_flip($pages);
    $cache = new Cache_text('pagelinks');
    foreach ($pages as $page_name) {
        $links = $cache->fetch($page_name);
        if (empty($links)) continue;
        foreach ($links as $link) {
            if (isset($orphaned[$link]))
                unset($orphaned[$link]);
        }
    }
    $out = '';

    $orphaned = array_flip($orphaned);
    $out = "<ol>\n";
    foreach ($orphaned as $page) {
        $out.= "<li>" . $formatter->link_tag($page, '', htmlspecialchars($page)) . "</li>\n";
    }
    $out.= "</ol>\n";
    return $out;
}

function do_orphanedpages($formatter, $options) {
    $formatter->send_header('', $options);
    $formatter->send_title('', '', $options);
    echo macro_OrphanedPages($formatter, $options['sec'], $options);
    $formatter->send_footer($args, $options);
}

// vim:et:sts=4:sw=4:
