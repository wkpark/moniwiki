<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a PageLinks plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark at gmail.com>
// Since: 2003/04/25
// Modified: 2015/12/02
// Name: PageLinksPlugin
// Description: PageLinks Plugin
// URL: MoniWiki:PageLinksPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
// PluginType: macro, action
// Usage: [[PageLinks]] or ?action=pagelinks
//
// $Id: PageLinks.php,v 1.3 2010/09/07 12:11:49 wkpark Exp $

function macro_PageLinks($formatter, $value = '', $params = array()) {
    global $DBInfo;

    $offset = 0;
    if (!empty($params['offset'])) {
        if (is_numeric($params['offset']) and $params['offset'] > 0)
            $offset = $params['offset'];
    }
    $param = array();
    if (!empty($offset)) $param['offset'] = $offset;

    $limit = 50;
    if (!empty($params['limit'])) {
        $tmp = max(10, intval($params['limit']));
        $limit = min($limit, $tmp);
    }
    $param['limit'] = $limit;

    if (!empty($params['all']))
        $pages = $DBInfo->getPageLists($param);
    else
        $pages = array($formatter->page->name);

    $start = '';
    if (!empty($params['start']) and is_numeric($params['start'])) {
        if ($params['start'] > 0) $start = $params['start'];
    }

    $pagelinks = $formatter->pagelinks; // save
    $save = $formatter->sister_on;
    $formatter->sister_on = 0;
    if (empty($formatter->wordrule)) $formatter->set_wordrule();

    $ol = '';
    if ($start > 0)
        $ol = ' start="'.$start.'"';
    else
        $start = 1;
    $out = "<ol$ol>\n";
    $cache = new Cache_text("pagelinks");
    $i = 0;
    $j = 0;
    foreach ($pages as $page) {
        $lnks = $cache->fetch($page);
        sort($lnks);
        if ($lnks !== false) {
            if (!empty($params['all'])) {
                $out .= "<li>";
                $out .= $formatter->link_tag($page, '', _html_escape($page)).': ';
            }
            $out .= '<ul>';
            $links = '<li> [['.implode(']]</li><li> [[', $lnks).']]</li>';
            $links = preg_replace_callback("/(".$formatter->wordrule.")/",
                    array(&$formatter, 'link_repl'), $links);
            $out .= $links."</ul>\n";
            if (!empty($params['all'])) {
                $out .= "</li>\n";
            }
            $i++;
        }
        $j++;
    }
    $out .= "</ol>\n";

    if ($j >= $limit) {
        $j = $offset + $limit;
        $i+= $start;
        $out .= $formatter->link_to("?action=pagelinks&amp;all=1&amp;offset=$j&amp;start=$i",
                _("Show next page"));
    }
    $formatter->pagelinks = $pagelinks; // restore
    $formatter->sister_on = $save;
    return $out;
}

function do_pagelinks($formatter, $params = '') {
    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    echo macro_PageLinks($formatter, '', $params);
    $formatter->send_footer('', $params);
}

// vim:et:sts=4:sw=4:
