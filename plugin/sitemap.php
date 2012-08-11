<?php
// Copyright (C) 2008 Yeon-Hyeong Yang <lbird94@gmail.com>
// All rights reserved. Can be distributed under GPL v2.
// a sitemap plugin for MoniWiki
//
// Author: Yeon-Hyeong Yang <lbird94@gmail.com>
// Date: 2008-12-25
// Name: Sitemap plugin
// Description: Sitemap action plugin
// URL: http://computing.lbird.net/2631012
// Release: 0.1
// Version: $Revision: 1.1 $
// License: GPL v2
//
// Imported and modified from do_rss_rc() and macro_TitleIndex()
// $Id: sitemap.php,v 1.1 2008/12/25 05:51:45 wkpark Exp $

function do_sitemap($formatter,$options) {
    global $DBInfo;

    # get page list 
    if ($formater->group) {
	$group_pages = $DBInfo->getLikePages($formater->group);
	foreach ($group_pages as $page)
	    $all_pages[] = str_replace($formatter->group,'',$page);
    } else
	$all_pages = $DBInfo->getPageLists();
    usort($all_pages, 'strcasecmp');
    $items = ''; // empty string

    # process page list
    $zone = '+00:00';
    foreach ($all_pages as $page) {
	$url = qualifiedUrl($formatter->link_url(_rawurlencode($page)));
        $p = new WikiPage($page);
        $t = $p->mtime();
        $date = gmdate("Y-m-d\TH:i:s",$t).$zone; // W3C datetime format

	$item = "<url>\n";
	$item.= "  <loc>".$url."</loc>\n";
	$item.= "  <lastmod>".$date."</lastmod>\n";
	$item.= "</url>\n";
	$items.= $item;
    }

    # process output
    $out = $items;
    if ($options['oe'] and (strtolower($options['oe']) != $DBInfo->charset)) {
	$charset = $options['oe'];
	if (function_exists('iconv')) {
	    $new=iconv($DBInfo->charset,$charset,$items);
	    if (!$new) $charset = $DBInfo->charset;
	    if ($new) $out = $new;
	}
    } else $charset = $DBInfo->charset;

    $head=<<<HEAD
<?xml version="1.0" encoding="$charset"?>
<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9"
         url="http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"
         xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

HEAD;
    $foot=<<<FOOT
</urlset>
FOOT;

    # output
    header("Content-Type: text/xml");
    print $head.$out.$foot;
}

// vim:et:sts=4:sw=4:
