<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a attachment list plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-04-16
// Name: AttachmentsPlugin
// Description: make a list of attachments for a given page.
// URL: MoniWiki:AttachmentsPlugin
// Version: $Revision: 1.3 $
// License: GPL
//
// Usage: [[Attachments(PageName)]]
//
// $Id: Attachments.php,v 1.3 2010/04/26 07:20:01 wkpark Exp $

function macro_Attachments($formatter,$value,$params=array()) {
    global $DBInfo;
    if ($value and $DBInfo->hasPage($value)) {
        $p=$DBInfo->getPage($value);
        $body=$p->get_raw_body();
        $baseurl=$formatter->link_url(_urlencode($value));
        //$formatter->page=&$p;
    } else if ($params['text']) {
        $body=$params['text'];
    } else {
        $body=$formatter->page->get_raw_body();
    }

    // from wiki.php
    $punct="<\'}\]\|\.\!"; # , is omitted for the WikiPedia
    $url='attachment';
    $urlrule="((?:$url):\"[^\"]+\"[^\s$punct]*|(?:$url):([^\s$punct]|(\.?[^\s$punct]))+|\[\[Attachment\([^\)]+\)\]\])";
    // do not include pre block
    $body=preg_replace("/\{\{\{.+?\}\}\}/s",'',$body);

    $my=array();
    $lines=explode("\n",$body);
    foreach ($lines as $line) {
        preg_match_all("/$urlrule/i",$line,$match);

        if (!$match) continue;
        $my=array_merge($my,$match[0]);
    }
    $my=array_unique($my);

    if (!empty($params['call'])) return $my;

    return " * ".implode("\n * ",$my);
}

function do_attachments($formatter,$options) {
    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);
    $ret= macro_Attachments($formatter,$options['value']);
    $formatter->send_page($ret);
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:sw=4:
?>

