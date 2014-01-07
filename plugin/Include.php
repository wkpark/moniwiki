<?php
// Copyright 2003-2014 Won-Kyu Park <wkpark at gmail.com>
// All rights reserved. Distributable under GPLv2 see COPYING
// a Include macro for the MoniWiki
//
// Usage: [[Include(Page Name,title,...)]]
//  * the first arg is the pagename.
//  * the second optional arg is title.
//  * the third optional arg is the level of title.
//  * args can be name='foobar'
//    then @name@ is replaced by "foobar"
//  * style or class are built-in params
//    you can change the style/class of the included contents

function macro_Include($formatter, $value = '') {
    global $DBInfo;

    // <<Include("Page Name", arg="1", arg2="2", ...)>>
    // parse variables
    preg_match("/^(['\"])?(?(1)(?:[^'\"]|\\\\['\"])*(?1)|[^,]*)/", $value, $m);
    $vars = array();
    $pagename = '';
    $class = '';
    $style = '';
    $styles = array();
    $title = '';
    if ($m) {
        $pagename = $m[0]; // first arg is page name
        $last = substr($value, strlen($m[0]));
        $i = 1;
        while (isset($last[0])) {
            if (preg_match("/^(?:(?:\s*,\s*)(?:([a-zA-Z0-9]+)\s*=\s*)?(['\"])?((?(2)(?:[^'\"]|\\\\['\"])*(?2)|[^,]*)))/", $last, $m)) {
                $last = substr($last, strlen($m[0]));
                $key = $m[1];
                $val = !empty($m[2]) ? substr($m[3], 0, -1) : $m[3];

                // check some built-in vars
                // set style or class
                if (in_array($key, array('style', 'class'))) {
                    if (empty($val))
                        continue;
                    if ($key == 'style')
                        $styles[] = $val;
                    else if ($key == 'class')
                        $class.= ' '.$val;
                    continue;
                }
                if (empty($key) and $i < 3) {
                    if ($i == 1) {
                        $title = $val;
                    } else {
                        $level = intval($val);
                    }
                    $i++;

                    continue;
                }

                if (!empty($key))
                    $vars[$key] = $val;
                else
                    $vars[$i] = $val;
                $i++;
            } else {
                break;
            }
        }
    }

    if (!isset($pagename[0]))
        return ''; // empty page

    // set title
    if (isset($title[0]) && $title[0] != '=') {
        if (empty($level) || $level > 5)
            $level = 3;
        $tag = str_repeat('=', $level);
        $title = $tag.' '.$title.' '.$tag;
    }

    if ($formatter->page->name != $pagename && $DBInfo->hasPage($pagename)) {

        // make replace regex
        if (!empty($vars)) {
            $class.= ' template';
            $repl = array_map(create_function('$a', 'return "/@".$a."@/i";'), array_keys($vars));
        }

        $page = $DBInfo->getPage($pagename);
        $f = new Formatter($page);
        $body = $page->_get_raw_body(); // get raw body
        if (!empty($vars))
            $body = preg_replace($repl, $vars, $body); // replace variables
        if (isset($title[0]))
            $body = $title."\n".$body;

        $params['nosisters'] = 1;

        $f->get_javascripts(); // trash default javascripts

        ob_start();
        $f->pi['#linenum'] = 0; // FIXME
        $f->send_page($body, $params);
        $out = ob_get_contents();
        ob_end_clean();

        if (!empty($class))
            $class = ' class="'.$class.'"';
        if (!empty($styles))
            $style = ' style="'.
                implode(';', $styles).'"';
        return '<div'.$class.$style.'>'.$out.'</div>';
    } else {
        return $formatter->link_repl($pagename);
    }
}

// vim:et:sts=4:sw=4:
