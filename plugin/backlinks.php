<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a Backlinks wrapper plugin
//
// Author: Won-Kyu Park <wkpark at gmail.com>
// Since: 2013/06/12
// Modified: 2015/12/02
// Name: BackLinksPlugin
// Description: BackLinks Plugin
// URL: MoniWiki:BackLinksPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
// PluginType: macro, action
//
// Usage: [[BackLinks]] or ?action=backlinks
//

require_once(dirname(__FILE__).'/FullSearch.php');

function macro_BackLinks($formatter, $value = '', $params = array()) {
    if (!isset($value[0]) || $value === true)
        $value = $formatter->page->name;

    $params['action'] = 'fullsearch';
    $params['backlinks'] = 1;
    $params['call'] = 1;
    $hits = macro_FullSearch($formatter, $value, $params);
    // $hits is sorted array

    $keys = array();
    $key = '';
    $out = '';
    $redirect = '';
    foreach ($hits as $page=>$count) {
        // redirect case
        if ($count == -2) {
            $urlname = _urlencode($page);
            $redirect .= '<li>' . $formatter->link_tag($urlname, '', _html_escape($page));
            $redirect .= " <span class='redirectIcon'><span>"._("Redirect page")."</span></span>\n";
            $redirect .= "</li>\n";
            continue;
        }

        $p = ltrim($page);
        $pkey = get_key("$p");
        if ($key != $pkey) {
            if (isset($key[0]))
                $keys[] = $key;
            $key = $pkey;
            if (!empty($out)) $out .= "</ul></div>";
            $out .= "<div><a name='$key'></a><h2><a href='#backlinks-top'>$key</a></h2>\n";
            $out .= "<ul>";
        }
        $title = $page;
        $urlname = _urlencode($title);

        $out .= '<li>' . $formatter->link_tag($urlname, '', _html_escape($title));
        if ($count == -2)
            $out.= " <span class='redirectIcon'><span>"._("Redirect page")."</span></span>\n";
        $out .= "</li>\n";
    }
    $out .= "</ul></div>\n";

    // add last key
    if (!isset($pkey[0]) && !in_array($pkey, $keys))
        $keys[] = $pkey;

    $keys = array_unique($keys);
    $index = array();
    foreach ($keys as $key) {
        $name = strval($key);
        $tag = '#'.$key;
        if ($name == 'Others') $name = _("Others");
        $index[] = "<a href='$tag'>$name</a>";
    }
    $str = implode(' | ', $index);

    if (isset($redirect[0]))
        $redirect = '<div><h2>'._("Redirects").'</h2><ul>'.$redirect.'</ul></div>';

    return "<div style='text-align:center'><a name='backlinks-top'></a>$str</div>\n<div class='index-group'>$redirect$out</div>";
}

function do_backlinks($formatter, $params = array()) {
    $params['.title'] = sprintf(_("BackLinks of %s."), _html_escape($formatter->page->name));
    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    echo macro_BackLinks($formatter, '', $params);
    $formatter->send_footer('', $params);
    return true;
}

// vim:et:sts=4:sw=4:
