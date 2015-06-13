<?php
// Copyright (C) 2008 Yeon-Hyeong Yang <lbird94@gmail.com>
// All rights reserved. Can be distributed under GPL v2.
// a sitemap plugin for MoniWiki
//
// Author: Yeon-Hyeong Yang <lbird94@gmail.com>
// Since: 2008-12-25
// Modified: 2015-06-13
// Name: Sitemap plugin
// Description: Sitemap action plugin
// URL: http://computing.lbird.net/2631012
// Release: 0.2
// Version: $Revision: 1.2 $
// License: GPL v2
//
// Imported and modified from do_rss_rc() and macro_TitleIndex()
// $Id: sitemap.php,v 1.1 2008/12/25 05:51:45 wkpark Exp $

function do_sitemap($formatter,$options) {
    global $DBInfo, $Config;

    $tc = new Cache_text('persist', array('depth'=>0));

    $extra = '';
    if (!empty($formater->group))
        $extra = '.'.$formatter->group;
    // all pages
    $mtime = $DBInfo->mtime();
    $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
    $etag = md5($mtime.$DBInfo->etag_seed.$extra);
    $options['etag'] = $etag;
    $options['mtime'] = $mtime;

    // set the s-maxage for proxy
    $date = gmdate('Y-m-d-H-i-s', $mtime);
    $proxy_maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';
    $header[] = 'Content-Type: text/xml';
    $header[] = 'Cache-Control: public'.$proxy_maxage.', max-age=0, must-revalidate';
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    if (!$need)
        $header[] = 'HTTP/1.0 304 Not Modified';
    else
        $header[] = 'Content-Disposition: attachment; filename="sitemap-'.$date.'.xml"';
    $formatter->send_header($header, $options);
    if (!$need) {
        @ob_end_clean();
        return;
    }

    if (($ret = $tc->fetch('sitemap'.$extra, array('print'=>1))) !== false)
        return;

    # get page list 
    set_time_limit(0);

    if ($formater->group) {
	$group_pages = $DBInfo->getLikePages($formater->group);
	foreach ($group_pages as $page)
	    $all_pages[] = str_replace($formatter->group,'',$page);
    } else {
        $args = array('all'=>1);
        $all_pages = $DBInfo->getPageLists($args);
    }
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
    $ttl = !empty($DBInfo->titleindex_ttl) ? $DBInfo->titleindex_ttl : 60*60*24;
    $tc->update('sitemap'.$extra, $head.$out.$foot, $ttl);
    echo $head.$out.$foot;
}

// vim:et:sts=4:sw=4:
