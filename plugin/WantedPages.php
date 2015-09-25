<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a WantedPages macro plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2003-08-15
// Date: 2015-09-25
// Name: Wanted Pages Plugin
// Description: Wanted Pages of this Wiki
// URL: MoniWiki:WantedPagesPlugin
// Version: $Revision: 1.8 $
// License: GPLv2
//
// Usage: [[WantedPages]]
//
// $Id: WantedPages.php,v 1.7 2010/10/05 22:28:54 wkpark Exp $

function macro_WantedPages($formatter, $value = '', $params = array()) {
    global $DBInfo;

    // set as dynamic macro
    if ($formatter->_macrocache and empty($params['call']))
        return $formatter->macro_cache_repl('WantedPages', $value);

    // set default page_limit
    if (empty($params['limit']))
        $params['limit'] = 100;

    $offset = 0;
    if (!empty($params['offset'])) {
        if (is_numeric($params['offset']) and $params['offset'] > 0)
            $offset = $params['offset'];
    }

    $param = array();
    if (!empty($offset)) $param['offset'] = $offset;
    $param['limit'] = $params['limit'];

    $pages = $DBInfo->getPageLists($param);

    $pagelinks = $formatter->pagelinks; // save
    $save = $formatter->sister_on;
    $formatter->sister_on = 0;

    $cache = new Cache_text('pagelinks');

    $j = 0;
    foreach ($pages as $page) {
        $dum = '';
        $p = new WikiPage($page);
        $f = new Formatter($p);
        $pi = $f->page->get_instructions($dum);
        if (!in_array($pi['#format'], array('wiki', 'monimarkup'))) continue;
        $links = $f->get_pagelinks();
        if ($links) {
            $lns = &$links;
            foreach($lns as $link) {
                if (empty($link) or $DBInfo->hasPage($link)) continue;
                if (empty($wants[$link]))
                    $wants[$link] = array('[["'.$page.'"]]');
                else
                    $wants[$link][] = '[["'.$page.'"]]';
            }
        }
        $j++;
    }
    if (!count($wants)) return '';
    $pagelinks = $formatter->pagelinks; // save
    $formatter->sister_on = 0;

    asort($wants);

    $out = "<ul>\n";
    $old_owns = null;
    foreach ($wants as $name=>$owns) {
        if ($old_owns != $owns) {
            $olinks = array_map(array($formatter, 'link_repl'), $owns);
            $olink = implode(', ', $olinks);
            if ($old_owns)
                $out.= "</ul>\n</li>\n";
            $out.= "<li>\n".$olink.'<ul>';
            $old_owns = $owns;
        }
        $out.= '<li>'.$formatter->link_repl($name, _html_escape($name)).'</li>'."\n";
    }
    $out.= "</ul>\n</li>\n</ul>\n";

    $out.= $formatter->link_to("?action=wantedpages&amp;offset=$j", _("Show next page"));

    $formatter->sister_on = $save;
    $formatter->pagelinks = $pagelinks; // restore

    return $out;
}

function do_wantedpages($formatter, $params) {
    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    echo macro_WantedPages($formatter, $params['sec'], $params);
    $formatter->send_footer($args, $params);
}

// vim:et:sts=4:sw=4:
