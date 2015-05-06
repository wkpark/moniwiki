<?php
// Copyright 2007-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2007-01-09
// Date: 2015-05-06
// Name: WHTML Processor
// Description: HTML with WikiLinks Processor
// URL: MoniWiki:WikiHtmlProcessor
// Version: $Revision: 1.4 $
// License: GPLv2
//
// Usage: {{{#!whtml
// <h1>Hello world ! Hello [MoniWiki]</h1>
// Hello World
// }}}
// $Id: whtml.php,v 1.3 2009/10/09 08:20:54 wkpark Exp $

function processor_whtml($formatter, $value, $options = array()) {
    global $Config;

    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    if ($line)
        list($tag,$args)=explode(' ',$line,2);

    $formatter->set_wordrule();
    if (!empty($formatter->use_smileys) and empty($formatter->smiley_rule))
        $formatter->initSmileys();


    $save=$formatter; // do not disturb $formatter
    $formatter->nonexists='always';

    $chunks=preg_split('/(<[^>]+>)/',$value,-1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i=0,$sz=count($chunks); $i<$sz; $i++) {
        if ($chunks[$i][0] != '<') {
            $out=$chunks[$i];
            if (!empty($formatter->smiley_rule))
                $out=preg_replace_callback($formatter->smiley_rule,
                    array(&$formatter, 'smiley_repl'),$out);
            $out=preg_replace_callback("/(".$formatter->wordrule.")/",
                    array(&$formatter, 'link_repl'), $out);
            $chunks[$i]=$out;
        }
    }

    $formatter=$save;

    $data = implode('', $chunks);

    // XSS filtering
    if (!empty($Config['no_xss_filter']))
        return $data;

    return $formatter->filter_repl('xss', $data);
}

// vim:et:sts=4:sw=4:
