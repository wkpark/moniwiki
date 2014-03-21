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
// Version: $Revision: 1.1 $
// License: GPL
//
// Usage: [[OrphanedPages]]
//
// $Id: OrphanedPages.php,v 1.1 2010/10/12 20:12:07 wkpark Exp $

function macro_OrphanedPages($formatter,$value, $params = array()) {
    global $DBInfo;

    // set as dynamic macro
    if ($formatter->_macrocache and empty($params['call']))
        return $formatter->macro_cache_repl('OrphanedPages', $value);
    $formatter->_dynamic_macros['@OrphanedPages'] = 1;

    $pagelinks = $formatter->pagelinks; // save
    $save = $formatter->sister_on;
    $formatter->sister_on = 0;

    $offset = 0;
    if (!empty($params['offset'])) {
        if (is_numeric($params['offset']) and $params['offset'] > 0)
            $offset = $params['offset'];
    }
    $param = array();
    if (!empty($offset)) $param['offset'] = $offset;
    $pages = $DBInfo->getPageLists($param);

    $start = 0;
    if (!empty($params['start']) and is_numeric($params['start'])) {
        if ($params['start'] > 0) $start = $params['start'];
    }

    // set time_limit
    $mt = explode(' ', microtime());
    $timestamp = $mt[0] + $mt[1];
    $j = 0;

    $time_limit = isset($DBInfo->process_time_limit[0]) ?
            $DBInfo->process_time_limit : 3; // default 3-seconds

    $orphaned = array();
    $cache = new Cache_text('backlinks');
    set_time_limit(0);
    $j = 0;
    $i = 1;
    foreach ($pages as $page_name) {
        $links = $cache->fetch($page_name);
        if (empty($links)) {
            $orphaned[$page_name] = $i;
            if ($i++ == $pagecount) break;
        }
        $j++;

        // check time_limit
        if ($time_limit and ++$j % 20 == 0) {
            $mt = explode(' ', microtime());
            $now = $mt[0] + $mt[1];
            if ($now - $timestamp > $time_limit) break;
        }
    }
    $out = '';
    $j+= $offset + 1;
    $i+= $start - 1;

    $start = ' start="'.$start.'"';

    $orphaned = array_flip($orphaned);
    $out = "<ol$start>\n";
    foreach ($orphaned as $page) {
        $out.= "<li>" . $formatter->link_tag($page, '', _html_escape($page)) . "</li>\n";
    }
    $out.= "</ol>\n";
    $out.= $formatter->link_to("?action=orphanedpages&amp;offset=$j&amp;start=$i", _("Show next page"));

    return $out;
}

function do_orphanedpages($formatter, $options) {
    $formatter->send_header('', $options);
    $formatter->send_title('', '', $options);
    echo macro_OrphanedPages($formatter, $options['sec'], $options);
    $formatter->send_footer($args, $options);
}

// vim:et:sts=4:sw=4:
