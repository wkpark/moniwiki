<?php
// Copyright 2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a external images fetcher for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Since: 2013-11-27
// Date: 2013-11-27
// Name: pull plugin
// Description: fetch raw wiki contents from Wiki site
// URL: MoniWiki:PullPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Param: pull_url='http://foo.bar/wiki.php/'
// Param: pull_fallback='AutoGoto'
//
// Usage: set $pull_url properly
//        and $auto_search='pull'; $pull_fallback='AutoGoto';

function do_pull($formatter, $params = array()) {
    global $Config;

    $pagename = $formatter->page->name;
    if ($formatter->refresh) $params['refresh'] = 1;

    $ret = array();
    $params['retval'] = &$ret;
    $params['call'] = true;

    if (!empty($Config['pull_ignore_re']) and preg_match('/'.$Config['pull_ignore_re'].'/i', $pagename)) {
        $ret['error'] = 'protected from pull';
        $ret['status'] = 404; // fake
    } else {
        macro_Pull($formatter, $pagename, $params);
    }
    if (!empty($params['check'])) {
        echo $params['retval']['status'];
        return;
    }

    if (!empty($ret['error'])) {
        if (!empty($Config['pull_fallback']) && $plugin=getPlugin($Config['pull_fallback'])) {
            // FIXME
            if (!function_exists('do_'.$plugin)) {
                include_once("plugin/$plugin.php");
            }
            if (function_exists('do_'.$plugin))
                call_user_func('do_'.$plugin, $formatter, $params);
            return;
        }
        echo $ret['error'];
    }
}

function macro_Pull($formatter, $pagename = '', $params = array()) {
    global $DBInfo;

    if (empty($pagename)) {
        $params['retval']['error'] = _("Empty PageName");
        return false;
    }
    if (empty($DBInfo->pull_url)) {
        $params['retval']['error'] = _("Empty \$pull_url");
        return false;
    }

    if (strpos($DBInfo->pull_url, '$PAGE') === false)
        $url = $DBInfo->pull_url._rawurlencode($pagename);
    else
        $url = preg_replace('/\$PAGE/', _rawurlencode($pagename), $DBInfo->pull_url);
    $url.= '?action=raw';

    require_once "lib/HTTPClient.php";

    $sz = 0;

    // set default params
    $maxage = !empty($DBInfo->pull_maxage) ? (int) $DBInfo->pull_maxage : 60*60*24*7;
    $timeout = !empty($DBInfo->pull_timeout) ? (int) $DBInfo->pull_timeout : 15;
    $maxage = (int) $maxage;

    // check connection
    $http = new HTTPClient();
    $sc = new Cache_text('mirrorinfo');

    $error = null;
    $headers = array();

    while ($sc->exists($pagename) and time() < $sc->mtime($pagename) + $maxage) {
        $info = $sc->fetch($pagename);
        if ($info == false) break;

        $sz = $info['size'];
        $etag = $info['etag'];
        $lastmod = $info['last-modified'];
        $error = !empty($info['error']) ? $info['error'] : null;

        // already retrived and found some error
        if (empty($params['refresh']) and !empty($error)) {

            return false;
        }
        // conditional get
        $headers['Cache-Control'] = 'maxage=0';
        $headers['If-Modified-Since'] = $lastmod;
        if (empty($DBInfo->pull_no_etag))
            $headers['If-None-Match'] = $etag;

        // do not refresh for no error cases
        if (empty($error)) unset($params['refresh']);
        break;
    }

    // get file header
    $http->nobody = true;

    if (!empty($headers))
        $http->headers = array_merge($http->headers, $headers);

    $http->sendRequest($url, array(), 'GET');

    if ($http->status == 304) {
        // not modified
        $params['retval']['status'] = 304;
        return true;
    }

    if ($http->status != 200) {
        $params['retval']['error'] = sprintf(_("Invalid Status %d"), $http->status);
        $params['retval']['status'] = $http->status;
        return false;
    } else if (!empty($params['check'])) {
        $params['retval']['status'] = 200;
        return true;
    }

    if (isset($http->resp_headers['content-length']))
        $sz = $http->resp_headers['content-length'];

    $etag = '';
    $lastmod = '';
    if (isset($http->resp_headers['etag']))
        $etag = $http->resp_headers['etag'];
    if (isset($http->resp_headers['last-modified']))
        $lastmod = $http->resp_headers['last-modified'];

    $sc->update($pagename, array('size'=>$sz,
                    'etag'=>$etag, 'last-modified'=>$lastmod));

    // size info
    if (is_numeric($sz)) {
        $unit = array('Bytes', 'KB', 'MB', 'GB');
        $tmp = $sz;
        for ($i = 0; $i < 4; $i++) {
            if ($tmp <= 1024) {
                break;
            }
            $tmp = $tmp / 1024;
        }
        $hsz = round($tmp, 2).' '.$unit[$i];
    } else {
        $params['retval']['error'] = _("Can't get file size info");
        $params['retval']['mimetype'] = $mimetype;
        return false;
    }

    $pagefile = $DBInfo->getPageKey($pagename);

    $mtime = @strtotime($lastmod);
    $my_mtime = $formatter->page->mtime();

    // not exactly same file.
    if ($my_mtime != $mtime or abs($mtime - $my_mtime) > 60)
        $params['refresh'] = 1; // force refresh
    
    // real fetch job.
    if (!empty($params['refresh']) or !file_exists($pagefile)) {
        @unlink($pagefile);

        $fp = fopen($pagefile, 'w');
        if (!is_resource($fp)) {
            $params['retval']['error'] = sprintf(_("Fail to open %s"), $pagefile);
            return false;
        }

        // retry to get all info
        $http = new HTTPClient();

        $save = ini_get('max_execution_time');
        set_time_limit(0);
        $http->timeout = $timeout;
        $http->sendRequest($url, array(), 'GET');
        set_time_limit($save);

        if ($http->status != 200) {
            fclose($fp);
            unlink($pagefile);

            // Error found! save error status to the info cache
            $params['retval']['error'] = !empty($http->error) ? $http->error : sprintf(_("Invalid Status %d"), $http->status);
            $params['retval']['status'] = $http->status;
            $params['retval']['etag'] = $etag;
            $params['retval']['last-modified'] = $lastmod;
            $params['retval']['size'] = $sz;
            $sc->update($pagename, array('size'=>$sz,
                        'etag'=>$mimetype,
                        'last-modified'=>$lastmod,
                        'error'=>$http->error,
                        'status'=>$params['retval']['status']));
            return false;
        }

        if (!empty($http->resp_body)) {
            fwrite($fp, $http->resp_body);
        }
        fclose($fp);
        //$mtime = @strtotime($lastmod);
        //touch($pagefile, $mtime);

        // remove PI cache to update
        $pi = new Cache_text('PI');
        $pi->remove($formatter->page->name);

        // update error status.
        if (!empty($error))
            $sc->update($pagename, array('size'=>$sz,
                        'etag'=>$etag, 'last-modified'=>$lastmod));

        $loc = $formatter->link_url($pagename);
        $loc = preg_replace('/&amp;/', '&', $loc);
        $formatter->send_header(array('Status: 302', 'Location: '.$loc), $params);
        echo 'Successfully fetched';
    } else {
        echo 'Not modified';
    }

    return null;
}

// vim:et:sts=4:sw=4:
