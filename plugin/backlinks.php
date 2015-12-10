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
    global $Config;

    // setup dynamic macro
    if ($formatter->_macrocache and empty($params['call']))
        return $formatter->macro_cache_repl('BackLinks', $value);
    if (empty($params['call']))
        $formatter->_dynamic_macros['@BackLinks'] = 1;

    if (!isset($value[0]) || $value === true)
        $value = $formatter->page->name;

    $params['backlinks'] = 1;
    $params['call'] = 1;
    $hits = macro_FullSearch($formatter, $value, $params);
    // $hits is sorted array

    $out = '';
    $title = '';
    // check the internal category parameter
    if (!isset($params['.category'])) {
        if (isset($Config['category_regex'][0]) &&
                preg_match('@'.$Config['category_regex'].'@', $value, $m, PREG_OFFSET_CAPTURE)) {
            if (isset($m[1]))
                $params['.category'] = $m[1];
            else
                $params['.category'] = substr($value, strlen($m[0][0])); // FIXME
        }
    }
    if (isset($params['.category'][0])) {
        // check subcategories
        $category = $params['.category'];
        $title = '<h2>'.sprintf(_("Pages in category \"%s\"."), _html_escape($category)).'</h2>';

        $cats = array();
        foreach ($hits as $p=>$c) {
            if (preg_match('@'.$Config['category_regex'].'@', $p, $m, PREG_OFFSET_CAPTURE)) {
	        if (isset($m[1]))
                    $cats[$p] = $m[1];
                else
                    $cats[$p] = substr($p, strlen($m[0][0])); // FIXME
                unset($hits[$p]);
            }
        }
        if (count($cats) > 0) {
            $params['.count'] = true;
            $out = '<h2>'. _("Subcategories") .'</h2>';
            $out .= _index($formatter, $cats, $params);
            $params['.count'] = false;
        }
    } else if (empty($params['.notitle'])) {
        $title = '<h2>'.sprintf(_("BackLinks of \"%s\"."), _html_escape($value)).'</h2>'."\n";
    }
    $index = _index($formatter, $hits, $params);
    if ($index !== false)
        return $out.$title.$index;

    if (empty($out)) {
        if (isset($params['.category'][0]))
            return '<h2>'.sprintf(_("No pages found in category \"%s\"."),
                _html_escape($params['.category'])) .'</h2>';
        else
            return '<h2>'._("No backlinks found.") .'</h2>';
    }
    return $out;
}

function _index($formatter, $pages, $params = array()) {
    global $Config;

    if (isset($GLOBALS['.index_id'])) {
        $GLOBALS['.index_id']++;
        $index_id = $GLOBALS['.index_id'];
    } else {
        $index_id = $GLOBALS['.index_id'] = 0;
    }
    $anchor = 'index-anchor'.$index_id;

    $count = !empty($params['.count']) ? true : false;
    if ($count) {
        $cc = new Cache_Text('category');
        $bc = new Cache_Text('backlinks');
    }
    $keys = array();
    $key = '';
    $out = '';
    $redirect = '';
    $n = 0;
    foreach ($pages as $page=>$info) {
        // redirect case
        if ($info == -2) {
            $urlname = _urlencode($page);
            $redirect .= '<li>' . $formatter->link_tag($urlname, '', _html_escape($page));
            $redirect .= " <span class='redirectIcon'><span>"._("Redirect page")."</span></span>\n";
            $redirect .= "</li>\n";
            $n++;
            continue;
        } else if (is_int($info)) {
            $title = $page;
        } else {
            $title = $info;
        }
        $pkey = get_key("$title");
        if ($key != $pkey) {
            if (isset($pkey[0]))
                $keys[] = $pkey;
            $key = $pkey;
            if (isset($out[0])) $out .= "</ul></div>";
            $out .= "<div><a name='$key'></a><h2><a href='#$anchor'>$key</a></h2>\n";
            $out .= "<ul>";
        }
        $urlname = _urlencode($page);

        $extra = '';
        // count subpages
        if ($count) {
            // get backlinks mtime
            $mtime = $bc->mtime($page);
            // get category counter info
            $cci = $cc->fetch($page, $mtime);
            if ($formatter->refresh || $cci === false) {
                // count backlinks
                $links = $bc->fetch($page);
                $c = 0;
                $p = 0;
                foreach ($links as $link) {
                    if (preg_match('@'.$Config['category_regex'].'@', $link)) {
                        $c++;
                    } else {
                        $p++;
                    }
                }
                // update cotegory counter info
                $cci = array('C'=>$c, 'P'=>$p);
                $cc->update($page, $cci);
            }

            // mediawiki like category status: Category (XX C, YY P)
            $tmp = array();
            if (!empty($cci['C']))
                $tmp[] = $cci['C'].' C';
            if (!empty($cci['P']))
                $tmp[] = $cci['P'].' P';
            if (isset($tmp[0])) {
                $extra = ' ('.implode(', ', $tmp).')';
            }
        }

        $out .= '<li>' . $formatter->link_tag($urlname, '', _html_escape($title));
        $out .= $extra;
        if ($info == -2)
            $out.= " <span class='redirectIcon'><span>"._("Redirect page")."</span></span>\n";
        $out .= "</li>\n";
        $n++;
    }
    $out .= "</ul></div>\n";

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

    if ($n > 10)
        $attr = " class='index-group'";
    else
        $attr = '';

    if ($n > 0)
        return "<div style='text-align:center'><a name='$anchor'></a>$str</div>\n<div$attr>$redirect$out</div>";
    else
        return false;
}

function do_backlinks($formatter, $params = array()) {
    global $Config;

    if (isset($params['value'][0])) {
        $value = _stripslashes($params['value']);
    } else {
        $value = $params['value'] = $formatter->page->name;
    }

    if (isset($Config['category_regex'][0]) &&
            preg_match('@'.$Config['category_regex'].'@', $value, $m, PREG_OFFSET_CAPTURE)) {
	if (isset($m[1]))
            $category = $m[1];
        else
            $category = substr($value, strlen($m[0][0])); // FIXME
        $params['.category'] = $category;
    } else {
        $params['.title'] = sprintf(_("BackLinks of \"%s\"."), _html_escape($value));
        $params['.category'] = false;
    }
    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    $params['.notitle'] = 1;
    echo macro_BackLinks($formatter, $value, $params);
    $formatter->send_footer('', $params);
    return true;
}

// vim:et:sts=4:sw=4:
