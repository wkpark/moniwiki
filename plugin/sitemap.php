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
    if (!empty($formatter->prefix))
        $extra .= qualifiedUrl($formatter->prefix);
    // all pages
    $mtime = $DBInfo->mtime();
    $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
    $etag = md5($mtime.$DBInfo->etag_seed.$extra);
    $options['etag'] = $etag;
    $options['mtime'] = $mtime;

    // set the s-maxage for proxy
    $date = gmdate('Y-m-d-H-i-s', $mtime);
    $proxy_maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';

    // only xml format supported
    $format = 'text/xml';
    if (isset($options['format']) and in_array($options['format'], array('text/xml')))
        $format = $options['format'];

    $header[] = 'Content-Type: '.$format;
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

    if (!$formatter->refresh && ($ret = $tc->fetch('sitemap'.$extra.'.mtime')) !== false) {
        if (($ret = $tc->fetch('sitemap'.$extra, 0, array('print'=>1))) !== false)
            return;
    }

    // set sitemap public cache
    $ext = $format == 'text/xml' ? 'xml' : 'txt';
    $sc = new Cache_text('sitemap',
        array('dir'=>$DBInfo->cache_public_dir, 'ext'=>$ext, 'depth'=>0));

    # get page list 
    set_time_limit(0);

    if ($formater->group) {
	$group_pages = $DBInfo->getLikePages($formater->group);
	foreach ($group_pages as $page)
	    $all_pages[] = str_replace($formatter->group,'',$page);

        usort($all_pages, 'strcasecmp');
    } else if (!empty($Config['sitemap_sortby'])) {
        // call PageSort macro.
        $opts = array();
        $opts['sortby'] = $Config['sitemap_sortby']; // date or size
        $opts['.call'] = 1;
        $ret = $formatter->macro_repl('PageSort', '', $opts);

        $all_pages = array();
        if (!empty($ret['count'])) {
            $tc->fetch('pagedate.raw');
            $rawfile = $tc->cache_path.'/'.$tc->getKey('pagedate.raw');
            $fp = fopen($rawfile, 'r');
            if (is_resource($fp)) {
                while (($line = fgets($fp, 1024)) != false) {
                    $tmp = explode("\t", $line);
                    $all_pages[] = $tmp[0];
                }
                fclose($fp);
            }
        }
    } else {
        $args = array('all'=>1);
        $all_pages = $DBInfo->getPageLists($args);

        usort($all_pages, 'strcasecmp');
    }

    $count = sizeof($all_pages);

    $map = '';
    $zone = '+00:00';
    $ttl = !empty($DBInfo->sitemap_ttl) ? $DBInfo->sitemap_ttl : 60*60*24*7;

    if ($count > 50000) {
        $map = <<<HEAD
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n
HEAD;
        $date = gmdate("Y-m-d\TH:i:s", time()).$zone; // W3C datetime format
        $total = intval($count / 50000) + (($count % 50000 > 0) ? 1 : 0);

        for ($i = 0; $i < $total; $i++) {
            $mapname = $sc->getKey(sprintf('sitemap'.$extra.'%03d', $i));

            $map.= "<sitemap>\n<loc>\n".qualifiedUrl($DBInfo->cache_public_url.'/'.$mapname).'</loc>'."\n";
            $map.= '<lastmod>'.$date."</lastmod>\n</sitemap>\n";
        }
        $map.= "</sitemapindex>\n";

        $tc->update('sitemap'.$extra, $map);
        $tc->update('sitemap'.$extra.'.mtime', array('dummy'=>1), $ttl);
    }

    # charset
    if ($options['oe'] and (strtolower($options['oe']) != $DBInfo->charset))
       $charset = $options['oe'];
    else $charset = $DBInfo->charset;

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

    # process page list
    $i = 0;
    $ii = 0;
    $items = array();
    foreach ($all_pages as $page) {
        $ii++;
        $url = qualifiedUrl($formatter->link_url(_rawurlencode($page)));

        $p = new WikiPage($page);
        $t = $p->mtime();
        $date = gmdate("Y-m-d\TH:i:s",$t).$zone; // W3C datetime format

        $item = "<url>\n";
        $item.= "  <loc>".$url."</loc>\n";
        $item.= "  <lastmod>".$date."</lastmod>\n";
        $item.= "</url>";
        $items[] = $item;
        if ($ii >= 50000) {
            $ii = 0;
            // process output
            $out = implode("\n", $items);
            if ($options['oe'] and (strtolower($options['oe']) != $DBInfo->charset)) {
                $charset = $options['oe'];
                if (function_exists('iconv')) {
                    $new=iconv($DBInfo->charset,$charset,$items);
                    if (!$new) $charset = $DBInfo->charset;
                    if ($new) $out = $new;
                }
            }

            $sc->update(sprintf('sitemap'.$extra.'%03d', $i), $head.$out.$foot);
            $i++;
            $items = array();
        }
    }
    $sc->update('sitemap'.$extra.'.mtime', array('dummy'=>1), $ttl);

    // process output
    if ($count > 50000) {
        if (count($items)) {
            $out = implode("\n", $items);

            if ($options['oe'] and (strtolower($options['oe']) != $DBInfo->charset)) {
                $charset = $options['oe'];
                if (function_exists('iconv')) {
                    $new=iconv($DBInfo->charset,$charset,$items);
                    if (!$new) $charset = $DBInfo->charset;
                    if ($new) $out = $new;
                }
            }
            $sc->update(sprintf('sitemap'.$extra.'%03d', $i), $head.$out.$foot);
        }
    } else {
        $out = implode("\n", $items);

        if ($options['oe'] and (strtolower($options['oe']) != $DBInfo->charset)) {
            $charset = $options['oe'];
            if (function_exists('iconv')) {
                $new=iconv($DBInfo->charset,$charset,$items);
                if (!$new) $charset = $DBInfo->charset;
                if ($new) $out = $new;
            }
        }
        $map = $head.$out.$foot;
        $tc->update('sitemap'.$extra, $map);
        $tc->update('sitemap'.$extra, array('dummy'=>1), $ttl);
    }

    echo $map;
}

// vim:et:sts=4:sw=4:
