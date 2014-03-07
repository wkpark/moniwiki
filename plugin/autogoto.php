<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// AutoGoto plugin
//
// Usage: set $auto_search='AutoGoto'; in the config.php
//
// $Id: autogoto.php,v 1.6 2010/06/19 07:03:00 wkpark Exp $

function do_AutoGoto($formatter,$options) {
    global $DBInfo;

    $supported=array('man'=>'Man','google'=>'Google','macro'=>'Macro','tpl'=>'TPL');

    if (!empty($DBInfo->autogoto_options)) {
        if (is_array($DBInfo->autogoto_options)) {
            $supported = array_merge($supported, $DBInfo->autogoto_options);
        } else if (is_string($DBInfo->autogoto_options)) {
            $opts=explode(',',$DBInfo->autogoto_options);
            foreach ($opts as $opt) {
                $opt=trim($opt);
                if (empty($opt)) continue;
                $v=explode(' ',$opt);
                if (!empty($v[1])) $supported[$v[0]]=$v[1];
            }
        }
        $v=explode(' ',trim($formatter->page->name));
        if ($v[1] and array_key_exists(strtolower($v[0]),$supported)) {
            $val = urlencode($v[1]);
            $options['value'] = $supported[strtolower($v[0])].':'.$val;
            do_goto($formatter,$options);
            return true;
        }
    }

    // automatically make a list of pagenames to check.
    $pages = array();
    $name = trim($formatter->page->name);

    // is this a CamelCase wikiname?
    if (strpos($name, ' ') === false and
            preg_match('/^[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*$/', $name)) {
        // insert spaces
        $name = preg_replace('/([a-z0-9])([A-Z])/', '\1 \2', $name);
    }
    $w = preg_split('/\s+/', $name);

    $pages[] = implode(' ', $w);
    $pages[] = ucwords($pages[0]);

    if (count($w) > 1) {
        $pages[] = ucfirst($pages[0]);
        $pages[] = str_replace(' ', '', $pages[0]);
        $pages[] = str_replace(' ', '', $pages[1]);
    }
    // MediaWiki style naming
    if (strpos($name, '_') !== false)
        $pages[] = str_replace('_', ' ', $name);

    $pages = array_unique($pages);

    foreach ($pages as $p) {
        if ($DBInfo->hasPage($p)) {
            $options['value'] = $p;
            do_goto($formatter, $options);
            return true;
        }
    }
    if (function_exists('iconv')) {
        if (strtolower($DBInfo->charset) != 'utf-8' ) {
            $t = @iconv('UTF-8',$DBInfo->charset,$formatter->page->name);
            if ($t and $DBInfo->hasPage($t)) {
                $options['value']=$t;
                do_goto($formatter,$options);
                return true;
            }
        } else if (!empty($DBInfo->url_encodings)) {
            $cs = explode(',',$DBInfo->url_encodings);
            foreach ($cs as $c) {
                $t = @iconv($c, $DBInfo->charset, $formatter->page->name);
                if ($t and $DBInfo->hasPage($t)) {
                    $options['value']=$t;
                    do_goto($formatter,$options);
                    return true;
                }
            }
        }
    }
    $options['value']=$formatter->page->name;
    $options['check']=1;
    $options['noexact'] = !empty($DBInfo->titlesearch_noexact) ? true : false;
    if (do_titlesearch($formatter,$options))
        return true;
    $options['value']=$formatter->page->name;
    # do not call AutoGoto recursively
    $options['redirect']=1;
    do_goto($formatter,$options);
    return true;
}

// vim:et:sts=4:sw=4:
?>
