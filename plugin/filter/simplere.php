<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple regex filter plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-26
// Name: a simple regex filter plugin
// Description: a simple regex filter plugin
// URL: MoniWiki:SimpleReFilter
// Version: $Revision: 1.1 $
// License: GPL
//
// $Id: simplere.php,v 1.1 2008/12/26 05:43:35 wkpark Exp $

function filter_simplere($formatter,$value,$options) {
    global $DBInfo;

    if (!empty($options['page']) and $DBInfo->hasPage($options['page'])) {
        $p = $DBInfo->getPage($options['page']);
        $raw = $p->get_raw_body();
        $lines = explode("\n",$raw);
        $rule = array();
        $repl = array();
        foreach ($lines as $line) {
            $line=trim($line);
            if ($line{0}=='#' or $line=='') continue;
            if (preg_match('/^([\/@])([^\\1]+)\\1([^\\1]+)\\1$/',$line,$match)) {
                $rule[] = $match[1].$match[2].$match[1];
                $repl[] = $match[3];
            }
        }
        $filter = new SimpleReFilter($rule,$repl);
        return $filter->process($value);
    }

    return $value;
}

class SimpleReFilter {
    var $rule = array();
    var $repl = array();

    function SimpleReFilter($rule, $repl) {
        $this->rule = $rule;
        $this->repl = $repl;
    }

    function process($text) {
        return preg_replace($this->rule, $this->repl, $text);
    }
}

// vim:et:sts=4:sw=4:
?>
