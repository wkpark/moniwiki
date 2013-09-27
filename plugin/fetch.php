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
// Param: fetch_timeout=15
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

    $ret = array();
    $params['retval'] = &$ret;
    $params['call'] = true;
    if ($formatter->refresh) $params['refresh'] = 1;
    macro_Fetch($formatter, $url, $params);

    if (!empty($ret['error'])) {
        if (!empty($ret['mimetype']) and preg_match('/^image\//', $ret['mimetype'])) {
            $font_size = 2;
            $str = 'ERROR: '.$ret['error'];
            $w = imagefontwidth($font_size) * strlen($str);
            $h = imagefontheight($font_size);
            $im = ImageCreate($w, $h);
            ImageColorAllocate($im, 255, 255, 255); // white background
            ImageColorAllocate($im, 0, 0, 0); // black
            ImageString($im, $font_size, 0, 0, $str, 1);

            if (function_exists("imagepng")) {
                header("Content-Type: image/png");
                imagepng($im);
            } else if(function_exists("imagegif")) {
                header("Content-Type: image/gif");
                imagegif($im);
            } else if(function_exists("imagejpeg")) {
                $jpeg_quality = 5;
                header("Content-Type: image/jpeg");
                imagejpeg($im, null, $jpeg_quality);
            }
            ImageDestroy($im);
            return;
        }
    }
    echo $ret['error'];
}

function macro_Fetch($formatter, $url = '', $params = array()) {
    global $DBInfo;

    if (empty($url)) {
        $params['retval']['error'] = _("Empty URL");
        return false;
    }

    require_once "lib/HTTPClient.php";

    $sz = 0;

    $allowed = 'png|jpeg|jpg|gif';
    if (!empty($DBInfo->fetch_exts)) {
        $allowed = $DBInfo->fetch_exts;
    }

    // check if it is valid or not
    if (!preg_match('/^(?:https?|ftp):\/\/.*\.('.$allowed.')(?:\?|&)?/i', $url, $m)) {
        if (empty($DBInfo->fetch_mime_check)) {
            $params['retval']['error'] = _("Is it a valid fetch type ?");
            return false;
        }
    }
    $ext = '.'.$m[1];

    // set default params
    $maxage = !empty($DBInfo->fetch_maxage) ? (int) $DBInfo->fetch_maxage : 60*60*24*7;
    $timeout = !empty($DBInfo->fetch_timeout) ? (int) $DBInfo->fetch_timeout : 15;

    // check connection
    $http = new HTTPClient();
    $sc = new Cache_text('fetchinfo');

    $error = null;

    if ($sc->exists($url) and $sc->mtime($url) < time() + $maxage) {
        $info = $sc->fetch($url);
        $sz = $info['size'];
        $mimetype = $info['mimetype'];
        $error = !empty($info['error']) ? $info['error'] : null;

        // already retrived and found some error
        if (empty($params['refresh']) and !empty($error)) {
            $params['retval']['status'] = $info['status'];
            $params['retval']['error'] = $error;
            $params['retval']['mimetype'] = $mimetype;
            $params['retval']['size'] = $sz;
            return false;
        }

        // do not refresh for no error cases
        if (empty($error)) unset($params['refresh']);
    } else {
        // get file header
        $http->nobody = true;

        $http->sendRequest($url, array(), 'GET');
        //if ($http->status == 301 || $http->status == 302 ) {
        //
        //}
        if ($http->status != 200) {
            $params['retval']['error'] = sprintf(_("Invalid Status %d"), $http->status);
            return false;
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
        $tmp = $sz;
        for ($i = 0; $i < 4; $i++) {
            if ($tmp <= 1024) {
                break;
            }
            $tmp = $tmp / 1024;
        }
        $hsz = round($tmp, 2).' '.$unit[$i];

        if (!empty($DBInfo->fetch_max_size) and $sz > $DBInfo->fetch_max_size) {
            $params['retval']['error'] = sprintf(_("Too big file size (%s). Please contact WikiMasters to increase \$fetch_max_size"), $hsz);
            $params['retval']['mimetype'] = $mimetype;
            return false;
        }
    } else {
        $params['retval']['error'] = _("Can't get file size info");
        $params['retval']['mimetype'] = $mimetype;
        return false;
    }

    $is_image = false;
    if (preg_match('/^image\/(jpe?g|gif|png)$/', $mimetype, $m)) {
        $ext = isset($m[1]) ? '.'.$m[1] : '';
        $is_image = true;
    }

    if (!empty($DBInfo->fetch_images_only) and !$is_image) {
        // always check the content-type
        $params['retval']['error'] = sprintf(_("Invalid mime-type %s"), $mimetype);
        $params['retval']['mimetype'] = $mimetype;
        return false;
    }

    if (empty($params['call'])) {
        if ($is_image) {
            $img_url = $formatter->link_url('', '?action=fetch&amp;url='.$url);
            return '<div class="externalImage"><img src="'.$img_url.'">'.
                '<div><a href="'.$url.'"><span>['.strtoupper($m[1]).' external image'.
                ' ('. $hsz.')'.
                ']</span></a></div>';
        }

        return $formatter->link_to('?action=fetch&amp;url='.$url, $mimetype.' ('. $hsz.')');
    }

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
    if (!empty($params['refresh']) or !file_exists($fetchfile)) {
        $fp = fopen($fetchfile, 'w');
        if (!is_resource($fp)) {
            $params['retval']['error'] = sprintf(_("Fail to open %s"), $fetchfile);
            return false;
        }

        // retry to get all info
        $http = new HTTPClient();
        //if (!empty($DBInfo->fetch_max_size)) {
        //    $http->max_bodysize = $DBInfo->fetch_max_size;
        //}

        $save = ini_get('max_execution_time');
        set_time_limit(0);
        $http->timeout = $timeout;
        $http->sendRequest($url, array(), 'GET');
        set_time_limit($save);

        if ($http->status != 200) {
            fclose($fp);
            unlink($fetchfile);

            // Error found! save error status to the info cache
            $params['retval']['status'] = sprintf(_("Invalid Status %d"), $http->status);
            $params['retval']['error'] = $http->error;
            $params['retval']['mimetype'] = $mimetype;
            $params['retval']['size'] = $sz;
            $sc->update($url, array('size'=>$sz,
                        'mimetype'=>$mimetype,
                        'error'=>$http->error,
                        'status'=>$params['retval']['status']));
            return false;
        }

        if (!empty($http->resp_body)) {
            fwrite($fp, $http->resp_body);
        }
        fclose($fp);

        // update error status.
        if (!empty($error))
            $sc->update($url, array('size'=>$sz,
                        'mimetype'=>$mimetype));
    }

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
