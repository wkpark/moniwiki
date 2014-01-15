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

/**
 * simple Dictionary class with dictionary
 */
class _localDict {
    var $vars = array();

    function _localDict($vars) {
        $this->vars = $vars;
    }

    function callback($match) {
        $val = strtolower($match[1]);
        if (isset($val[0]) and isset($this->vars[$val]))
            return $this->vars[$val];
        if (isset($match[2][0]))
            return $match[2];
        return $match[0];
    }

    function replace($regex, $text) {
        return preg_replace_callback($regex, array($this, 'callback'), $text);
    }
}

function macro_Include($formatter, $value = '') {
    global $DBInfo;

    if (!isset($GLOBALS['_included_']))
        $GLOBALS['_included_'] = array();

    $max_recursion = isset($DBInfo->include_max_recursion) ? $DBinfo->include_max_recursion : 2;

    $included = &$GLOBALS['_included_'];

    // <<Include("Page Name", arg="1", arg2="2", ...)>>
    // parse variables
    preg_match("/^(['\"])?(?(1)(?:[^'\"]|\\\\['\"])*(?1)|[^,]*)/", $value, $m);
    $vars = array();
    $pagename = '';
    $class = '';
    $style = '';
    $styles = array();
    $title = '';
    $debug = false;
    if ($m) {
        $pagename = $m[0]; // first arg is page name

        // detect recursive include
        if (in_array($pagename, $included)) {

            if (isset($formatter->recursion) and $formatter->recursion > $max_recursion)
                return '';
        } else {
            $included[] = $pagename;
        }

        $last = substr($value, strlen($m[0]));
        $i = 1;
        while (isset($last[0])) {
            if (preg_match("/^(?:(?:\s*,\s*)(?:([a-zA-Z0-9_-]+)\s*=\s*)?(['\"])?((?(2)(?:[^'\"]|\\\\['\"])*(?2)|[^,]*)))/", $last, $m)) {
                $last = substr($last, strlen($m[0]));
                $key = strtolower($m[1]);
                $val = !empty($m[2]) ? substr($m[3], 0, -1) : $m[3];

                // check some built-in vars
                // set style or class
                if (in_array($key, array('style', 'class', 'debug'))) {
                    if (empty($val))
                        continue;
                    if ($key == 'style')
                        $styles[] = $val;
                    else if ($key == 'class')
                        $class.= ' '.$val;
                    else
                        $debug = true;
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

    // out debug msg;
    $msg = '';
    if ($debug) {
        ob_start();
        var_dump($vars);
        $msg = ob_get_contents();
        ob_end_clean();
    }

    // set title
    if (isset($title[0]) && $title[0] != '=') {
        if (empty($level) || $level > 5)
            $level = 3;
        $tag = str_repeat('=', $level);
        $title = $tag.' '.$title.' '.$tag;
    }

    if ($DBInfo->hasPage($pagename)) {

        // default class for template
        if (!empty($vars)) {
            $class.= ' template';
        }

        // add some default variables
        if (!isset($vars['pagename']))
            $vars['pagename'] = $formatter->page->name;

        $repl = new _localDict($vars);

        $page = $DBInfo->getPage($pagename);
        $f = new Formatter($page);

        // for recursion detect
        $f->recursion = isset($formatter->recursion) ? $formatter->recursion + 1 : 1;
        $body = $page->_get_raw_body(); // get raw body

        // mediawiki like replace variables
        // @foo@ or @foo[separator]default value@ are accepted
        // the separator can be space , and |
        $body = $repl->replace(
            '/@([a-z0-9_-]+)(?:(?:,|\|)((?!\s)(?:[^@]|@@)*(?!\s)))?@/',
            $body);
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
        return '<div'.$class.$style.'>'.$msg.$out.'</div>';
    } else {
        return $formatter->link_repl($pagename);
    }
}

// vim:et:sts=4:sw=4:
