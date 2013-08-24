<?php
// Copyright 2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a external images fetcher for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Since: 2013-08-04
// Date: 2013-08-24
// Name: fetch plugin
// Description: fetch external images
// URL: MoniWiki:FetchPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Param: fetch_exts='png|jpeg|jpg|gif'
// Param: fetch_max_size=3*1024*1024
// Param: fetch_maxage=24*60*60
// Param: fetch_action=http://foo.bar/wiki.php?action=fetch&url=
// Param: fetch_use_cache_url=0
//
// Usage:[[Fetch(url)]] or ?action=fetch&url=http://...
//

function do_fetch($formatter, $params = array()) {
    global $Config;

    $value = $params['value'];
    $url = !empty($params['url']) ? $params['url'] : $value;

    if (!empty($Config['fetch_ignore_re']) and preg_match('/'.$Config['fetch_ignore_re'].'/i', $url))
        $redirect_url = true;
    if (!empty($Config['fetch_url_re']) and !preg_match('/'.$Config['fetch_url_re'].'/i', $url))
        $redirect_url = true;

    if (isset($redirect_url)) {
        $formatter->send_header(array("Status: 302","Location: ".$url));
        return;
    }

    $err = '';
    $params['err'] = &$err;
    $params['call'] = true;
    macro_Fetch($formatter, $url, $params);

    if (!empty($params['err'])) {
        echo $err;
        return;
    }
}

function macro_Fetch($formatter, $url = '', $params = array()) {
    global $DBInfo;

    if (empty($url)) {
        $params['err'] = _("Empty URL");
        return null;
    }

    require_once "lib/HTTPClient.php";

    $sz = 0;

    // check if it is valid or not
    if (!preg_match('/^(https?|ftp):\/\/.*\.(jpe?g|gif|png)(?:\?|&)?/i', $url)) {
        if (empty($DBInfo->fetch_mime_check)) {
            $params['err'] = _("Is it valid image ? file URL ?");
            return null;
        }
    }

    // set default params
    $maxage = !empty($DBInfo->fetch_maxage) ? $DBInfo->fetch_maxage : 60*60*24*7;

    // check connection
    $http = new HTTPClient();
    $sc = new Cache_text('fetchinfo');

    if ($sc->exists($url) and $sc->mtime($url) < time() + $maxage) {
        $info = $sc->fetch($url);
        $sz = $info['size'];
        $mimetype = $info['mimetype'];
    } else {
        // get file header
        $http->nobody = true;

        $http->sendRequest($url, array(), 'GET');
        //if ($http->status == 301 || $http->status == 302 ) {
        //
        //}
        if ($http->status != 200) {
            $params['err'] = sprintf(_("Invalid Status %d"), $http->status);
            return null;
        }

        if (isset($http->resp_headers['content-length']))
            $sz = $http->resp_headers['content-length'];

        if (isset($http->resp_headers['content-type']))
            $mimetype = $http->resp_headers['content-type'];
        else
            $mimetype = 'application/octet-stream';
        $sc->update($url, array('size'=>$sz,
                        'mimetype'=>$mimetype));
    }

    // size info
    if (is_numeric($sz)) {
        $unit = array('Bytes', 'KB', 'MB', 'GB');
        for ($i = 0; $i < 4; $i++) {
            if ($sz <= 1024) {
                break;
            }
            $sz = $sz / 1024;
        }
        $hsz = round($sz, 2).' '.$unit[$i];

        if (!empty($DBInfo->fetch_max_size)) {
            if ($sz > $DBInfo->fetch_max_size) {
                $params['err'] = sprintf(_("Fetch file size %s is too big. Please contact WikiMasters to increase \$fetch_max_size"), $hsz);
                return null;
            }
        }
    } else {
        $params['err'] = _("Can't get file size info");
        return null;
    }

    if (!preg_match('/^image\/(jpe?g|gif|png)$/', $mimetype, $m)) {
        // always check the content-type
        $params['err'] = sprintf(_("Invalid mime-type %s"), $mimetype);
        return null;
    }
    $ext = isset($m[1]) ? '.'.$m[1] : '';

    // cache dir/filename/cache url
    if (!empty($DBInfo->cache_public_dir) and
        !empty($DBInfo->cache_public_url)) {
        $fc = new Cache_text('fetchfile',
            array('dir'=>$DBInfo->cache_public_dir));
        $fetchname = $fc->getKey($url);
        $fetchfile = $DBInfo->cache_public_dir.'/'
            .$fetchname.$ext;
        $fetch_url =
            $DBInfo->cache_public_url.'/'.$fetchname.$ext;
    } else {
        $fc = new Cache_text('fetchfile');
        $fetchname = $fc->getKey($url);
        $fetchfile = $fc->cache_dir.'/' .$fetchname;
        $fetch_url = null;
    }

    // real fetch job.
    if (!file_exists($fetchfile)) {
        $fp = fopen($fetchfile, 'w');
        if (!is_resource($fp)) {
            $params['err'] = sprintf(_("Fail to open %s"), $fetchfile);
            return null;
        }

        // retry to get all info
        $http = new HTTPClient();
        //if (!empty($DBInfo->fetch_max_size)) {
        //    $http->max_bodysize = $DBInfo->fetch_max_size;
        //}
        $http->sendRequest($url, array(), 'GET');

        if (!empty($http->resp_body)) {
            fwrite($fp, $http->resp_body);
        }
        fclose($fp);
    }

    if (empty($params['call'])) return null;

    if (!empty($fetch_url) and !empty($DBInfo->fetch_use_cache_url)) {
        $formatter->send_header(array('Status: 302', 'Location: '.$fetch_url));

        return null;
    }

    $down_mode = 'inline';

    header("Content-Type: $mimetype\r\n");
    header("Content-Length: ".filesize($fetchfile));
    //header("Content-Disposition: $down_mode; ".$fname );
    header("Content-Description: MoniWiki PHP Fetch Downloader" );
    $mtime = filemtime($fetchfile);
    $lastmod = gmdate("D, d M Y H:i:s", $mtime) . ' GMT';
    $etag = md5($lastmod.$url);
    header("Last-Modified: " . $lastmod);
    header('ETag: "'.$etag.'"');
    header("Pragma:");
    header('Cache-Control: public, max-age='.$maxage);
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    if (!$need) {
        header('X-Cache-Debug: Cached OK');
        header('HTTP/1.0 304 Not Modified');
        @ob_end_clean();
        return null;
    }

    @ob_clean();
    $ret = readfile($fetchfile);
    return null;
}

// vim:et:sts=4:sw=4:
