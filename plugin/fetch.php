<?php
// Copyright 2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a external images fetcher for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Since: 2013-08-04
// Date: 2013-12-16
// Name: fetch plugin
// Description: fetch external images
// URL: MoniWiki:FetchPlugin
// Version: $Revision: 1.1 $
// License: GPLv2
//
// Param: fetch_exts='png|jpeg|jpg|gif'
// Param: fetch_max_size=3*1024*1024
// Param: fetch_buffer_size=1024*2048
// Param: fetch_maxage=24*60*60
// Param: fetch_action=http://foo.bar/wiki.php?action=fetch&url=
// Param: fetch_timeout=15
// Param: fetch_use_cache_url=0
// Param: fetch_referer='http://to_default_referer';
// Param: fetch_referer_re=array('@pattern@'=>'http://to_default_referer',...);
// Param: fetch_use_imagemagick=0
// Param: fetch_thumb_width=320
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
        if (!empty($ret['mimetype']) and
                preg_match('/^image\//', $ret['mimetype'])) {
            $is_image = true;
        } else {
            $is_image = preg_match('/\.(png|jpe?g|gif)(&|\?)?/i', $url);
        }

        if ($is_image) {
            require_once(dirname(__FILE__).'/../lib/mediautils.php');

            $font_face = !empty($Config['fetch_font']) ? $Config['fetch_font'] : '';
            $font_size = !empty($Config['fetch_font_size']) ? $Config['fetch_font_size'] : 2;

            $str = 'ERROR: '.$ret['error'];

            $im = image_msg($font_size,
                $font_face, $str);

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
    // check valid url
    if (!preg_match('@^((ftp|https?)://[^/]+)/@', $url, $m))
        return false;

    $siteurl = $m[1];

    require_once "lib/HTTPClient.php";

    $sz = 0;

    $allowed = 'png|jpeg|jpg|gif';
    if (!empty($DBInfo->fetch_exts)) {
        $allowed = $DBInfo->fetch_exts;
    }

    // urlencode()
    $url = _urlencode($url);

    // set default params
    $maxage = !empty($DBInfo->fetch_maxage) ? (int) $DBInfo->fetch_maxage : 60*60*24*7;
    $timeout = !empty($DBInfo->fetch_timeout) ? (int) $DBInfo->fetch_timeout : 15;

    $vartmp_dir = $DBInfo->vartmp_dir;
    $buffer_size = 2048 * 1024; // default buffer size
    if (!empty($DBInfo->fetch_buffer_size) and
            $DBInfo->fetch_buffer_size > 2048 * 1024) {
        $buffer_size = $DBInfo->fetch_buffer_size;
    }

    // set referrer
    $referer = '';
    if (!empty($DBInfo->fetch_referer_re)) {
        foreach ($DBInfo->fetch_referer_re as $re=>$ref) {
            if (preg_match($re, $url)) {
                $referer = $ref;
                break;
            }
        }
    }
    // default referrer
    if (empty($referer) and !empty($DBInfo->fetch_referer))
        $referer = $DBInfo->fetch_referer;

    // check site available
    $si = new Cache_text('siteinfo');
    if ($si->exists($siteurl)) {
        if (!empty($params['refresh'])) {
            $si->remove($siteurl);
        } else if (empty($params['refresh']) && ($check = $si->fetch($siteurl)) !== false) {
            $params['retval']['status'] = $check['status'];
            $params['retval']['error'] = $check['error'];
            return false;
        }
    }

    $sc = new Cache_text('fetchinfo');
    $error = null;

    if (empty($params['refresh']) and $sc->exists($url) and $sc->mtime($url) < time() + $maxage) {
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
    } else {
        // check connection
        $http = new HTTPClient();
        // get file header
        $http->nobody = true;

        $http->referer = $referer;
        $http->sendRequest($url, array(), 'GET');
        //if ($http->status == 301 || $http->status == 302 ) {
        //
        //}
        if ($http->status != 200) {
            if ($http->status == 404)
                $params['retval']['error'] = '404 File Not Found';
            else
                $params['retval']['error'] = !empty($http->error) ? $http->error : sprintf(_("Invalid Status %d"), $http->status);
            $params['retval']['status'] = $http->status;

            // check alive site
            if ($http->status == -210) {
                $si->update($siteurl, array('status'=>$http->status,
                        'error'=>$params['retval']['error']), /* ttl 1-days */ 60*60*24);

                return false;
            }

            $sc->update($url, array('size'=>-1,
                        'mimetype'=>'',
                        'error'=>$params['retval']['error'],
                        'status'=>$params['retval']['status']), /* ttl 3-days */ 60*60*24*3);

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

        if (empty($buffer_size) && !empty($DBInfo->fetch_max_size) and $sz > $DBInfo->fetch_max_size) {
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
    } else {
        $ext = '.'.get_extension('data/mime.types', $mimetype);
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
            return '<div class="externalImage"><div><img src="'.$img_url.'">'.
                '<div><a href="'.$url.'"><span>['.strtoupper($m[1]).' '._("external image").
                ' ('. $hsz.')'.
                ']</span></a></div></div>';
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
        if (!empty($buffer_size))
            $http->max_buffer_size = $buffer_size;
        $http->vartmp_dir = $vartmp_dir;

        $save = ini_get('max_execution_time');
        set_time_limit(0);
        $http->timeout = $timeout;
        $http->referer = $referer;
        $http->sendRequest($url, array(), 'GET');
        set_time_limit($save);

        if ($http->status != 200) {
            fclose($fp);
            unlink($fetchfile);

            // Error found! save error status to the info cache
            $params['retval']['status'] = $http->status;
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
            fclose($fp);
        } else {
            fclose($fp);

            if (!empty($http->resp_body_file) && file_exists($http->resp_body_file)) {
                copy($http->resp_body_file,
                    $fetchfile);
                unlink($http->resp_body_file);
            }
        }

        // update error status.
        if (!empty($error))
            $sc->update($url, array('size'=>$sz,
                        'mimetype'=>$mimetype));
    }

    if (!empty($fetch_url) and !empty($DBInfo->fetch_use_cache_url)) {
        $formatter->send_header(array('Status: 302', 'Location: '.$fetch_url));

        return null;
    }

    if (!empty($params['thumbwidth'])) {
        // check allowed thumb widths.
        $thumb_widths = isset($DBInfo->thumb_widths) ? $DBInfo->thumb_widths :
                array('120', '240', '320', '480', '600', '800', '1024');

        $width = 320; // default
        if (!empty($DBInfo->default_thumb_width))
            $width = $DBInfo->default_thumb_width;

        if (!empty($thumb_widths)) {
            if (in_array($params['thumbwidth'], $thumb_widths))
                $width = $params['thumbwidth'];
            else {
                header("HTTP/1.1 404 Not Found");
                echo "Invalid thumbnail width",
                    "<br />",
                    "valid thumb widths are ",
                    implode(', ', $thumb_widths);
                return;
            }
        } else {
            $width = $params['thumbwidth'];
        }
        $thumb_width = $width;
        $force_thumb = true;
    } else {
        // automatically generate thumb images to support low-bandwidth mobile version
        if (is_mobile()) {
            $force_thumb = (!isset($params['m']) or $params['m'] == 1);
        }
    }

    // generate thumb file to support low-bandwidth mobile version
    $thumbfile = '';
    while ((!empty($params['thumb']) or $force_thumb) and
            preg_match('/^image\/(jpe?g|gif|png)$/', $mimetype)) {
        if (empty($thumb_width)) {
            $thumb_width = 320; // default
            if (!empty($DBInfo->fetch_thumb_width))
                $thumb_width = $DBInfo->fetch_thumb_width;
        }

        $thumbfile = preg_replace('@'.$ext.'$@', '.w'.$thumb_width.$ext, $fetchfile);
        if (empty($params['refresh']) && file_exists($thumbfile)) break;

        list($w, $h) = getimagesize($fetchfile);
        if ($w <= $thumb_width) {
            $thumbfile = $fetchfile;
            break;
        }

        require_once('lib/mediautils.php');
        // generate thumbnail using the gd func or the ImageMagick(convert)

        resize_image($ext, $fetchfile, $thumbfile, $w, $h, $thumb_width);
        break;
    }

    $down_mode = 'inline';
    if (!empty($thumbfile))
        $fetchfile = $thumbfile;

    header("Content-Type: $mimetype\r\n");
    header("Content-Length: ".filesize($fetchfile));
    //header("Content-Disposition: $down_mode; ".$fname );
    header("Content-Description: MoniWiki PHP Fetch Downloader" );
    $mtime = filemtime($fetchfile);
    $lastmod = gmdate("D, d M Y H:i:s", $mtime) . ' GMT';
    $etag = md5($lastmod.$url.$thumbfile);
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
