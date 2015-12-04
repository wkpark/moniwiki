<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a Category plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2015-12-04
// Name: Category plugin
// Description: Category Plugin to emulate mediawiki like category
// URL: MoniWiki:CategoryPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Param: $use_builtin_category = 1;
// Param: $category_regex = '^Category:';
// Usage: [[Category(CategoryLink)]] or ?action=category
//

function macro_Category($formatter, $value = '', $params = array()) {
    if (!isset($formatter->categories))
        $formatter->categories = array();

    if (isset($value[0])) {
        // add a backlink
        $tmp = $formatter->word_repl('[['.$value.']]');
        // is it a category link
        if (preg_match('@'.$formatter->category_regex.'@', $value)) {
            $formatter->categories[] = $value;
            return '<span></span>';
        }
        // not a category link
        return $tmp;
    }

    $cc = new Cache_Text('categories');
    if (count($formatter->categories) > 0) {
        if (!$formatter->preview)
            $cc->update($formatter->page->name, $formatter->categories);
        $categories = $formatter->categories;
    } else if ($formatter->page->exists()) {
        $categories = $cc->fetch($formatter->page->name);
    } else {
        $cc->remove($formatter->page->name);
        $categories = array();
    }

    if (!empty($params['call']) || !empty($params['.call']))
        return $categories;

    if (empty($categories))
        return '';

    $out = '<div class="wikiCategory">';
    $out .= '<h2>'._("Category").'</h2>';
    $out .= '<ul>';
    foreach ($categories as $cat) {
        if (preg_match('@'.$formatter->category_regex.'@', $cat, $m)) {
            // strip category prefix
            if (isset($m[1]))
                $text = $m[1];
            else
                $text = substr($cat, strlen($m[0]));
            $tmp = $formatter->word_repl('[['.$cat.']]', $text);
            $out .= '<li>'.$tmp.'</li>'."\n";
        }
    }
    $out .= '</ul></div>';

    return $out;
}

function do_category($formatter, $params = array()) {
    global $Config;

    header('Content-Type: text/plain');

    if (!$formatter->page->exists()) {
        echo '[]';
        return;
    }

    $params['.call'] = 1;
    $categories = macro_Category($formatter, '', $params);
    echo json_encode($categories);
    return;
}

// vim:et:sts=4:sw=4:
