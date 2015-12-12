<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a RandomPage plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-05-10
// Name: RandomQuote macro plugin
// Description: show RandomQuote
// URL: MoniWiki:RandomQuotePlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[RandomQuote]]
//

define('DEFAULT_QUOTE_PAGE', 'FortuneCookies');

function macro_RandomQuote($formatter,$value="",$options=array()) {
    global $DBInfo;

    $re='/^\s*\* (.*)$/';
    $args=explode(',',$value);

    $log = '';
    foreach ($args as $arg) {
        $arg=trim($arg);
        if (!empty($arg[0]) and in_array($arg[0],array('@','/','%')) and
                preg_match('/^'.$arg[0].'.*'.$arg[0].'[sxU]*$/',$arg)) {
            if (preg_match($arg,'',$m)===false) {
                $log=_("Invalid regular expression !");
                continue;
            }
            $re=$arg;
        } else
            $pagename=$arg;
    }

    if (!empty($pagename) and $DBInfo->hasPage($pagename))
        $fortune=$pagename;
    else
        $fortune=DEFAULT_QUOTE_PAGE;

    if (!empty($options['body'])) {
        $raw=$options['body'];
    } else {
        $page=$DBInfo->getPage($fortune);
        if (!$page->exists()) return '';
        $raw=$page->get_raw_body();
    }

    preg_match_all($re.'m',$raw,$match);
    $quotes=&$match[1];

    if (!($count=sizeof($quotes))) return '[[RandomQuote('._("No match!").')]]';
    #if ($formatter->preview==1) return '';
    if ($count<3 and preg_grep('/\[\[RandomQuote/',$quotes))
        return '[[RandomQuote('._("Infinite loop possible!").')]]';

    $quote=$quotes[rand(0,$count-1)];

    $dumb=explode("\n",$quote);
    if (sizeof($dumb)>1) {
        if (isset($formatter->preview)) $save = $formatter->preview;
        $formatter->preview=1;
        $options['nosisters']=1;
        ob_start();
        $formatter->send_page($quote,$options);
        if (isset($save))
            $formatter->preview=$save;
        $out= ob_get_contents();
        ob_end_clean();
    } else {
        $formatter->set_wordrule();
        $quote=str_replace("<","&lt;",$quote);
        $quote=preg_replace($formatter->baserule,$formatter->baserepl,$quote);
        $out = preg_replace_callback("/(".$formatter->wordrule.")/",
                array(&$formatter, 'link_repl'), $quote);
    }
    #  ob_start();
    #  $options['nosisters']=1;
    #  $formatter->send_page($quote,$options);
    #  $out= ob_get_contents();
    #  ob_end_clean();
    #  return $out;
    return $log.$out;
}

// vim:et:sts=4:sw=4:
