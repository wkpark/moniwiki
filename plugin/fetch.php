<?php
// Copyright 2013-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a external images fetcher for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Since: 2013-08-04
// Date: 2015-06-19
// Name: fetch plugin
// Description: fetch external images
// URL: MoniWiki:FetchPlugin
// Version: $Revision: 1.2 $
// License: GPLv2
//
// Param: fetch_exts='png|jpeg|jpg|gif'
// Param: fetch_max_size=3*1024*1024
// Param: fetch_buffer_size=1024*2048
// Param: fetch_maxage=60*60*7
// Param: fetch_maxages=array('site_alive'=>60*60, 'site_status'=>60*60*2, 'size_error'=>60);
// Param: fetch_action=http://foo.bar/wiki.php?action=fetch&url=
// Param: fetch_timeout=15
// Param: fetch_use_cache_url=0
// Param: fetch_referer='http://to_default_referer';
// Param: fetch_referer_re=array('@pattern@'=>'http://to_default_referer',...);
// Param: fetch_use_imagemagick=0
// Param: fetch_thumb_width=320
// Param: fetch_show_information=0; // to show image information
// Param: fetch_apikey=blahblahblah; // to refresh fetch file
//
// Usage:[[Fetch(url)]] or ?action=fetch&url=http://...
//

function do_fetch($formatter, $params = array()) {
    global $Config;
    // $formatter->refresh = 1;

    $value = $params['value'];
    $url = !empty($params['url']) ? $params['url'] : $value;

    if (!empty($Config['fetch_ignore_re']) and preg_match('/'.$Config['fetch_ignore_re'].'/i', $url))
        $redirect_url = true;
    if (!empty($Config['fetch_url_re']) and !preg_match('/'.$Config['fetch_url_re'].'/i', $url))
        $redirect_url = true;

    if (isset($redirect_url)) {
        $formatter->send_header(array("Status: 301","Location: ".$url));
        return;
    }

    $ret = array();
    $params['retval'] = &$ret;
    $params['call'] = true;
    if ($formatter->refresh) $params['refresh'] = 1;
    else if (!empty($params['apikey']) and !empty($Config['apikey']) and $Config['apikey'] == $params['apikey'])
        $params['refresh'] = 1;
    if (!empty($params['refresh'])) {
        if (empty($_SERVER['HTTP_REFERER']) and !empty($params['refresh']))
            $params['.localrefresh'] = 1;
        else if (strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false)
            $params['.localrefresh'] = 1;
    }

    macro_Fetch($formatter, $url, $params);

    if (!empty($ret['error'])) {
        if (!empty($ret['mimetype']) and
                preg_match('/^image\//', $ret['mimetype'])) {
            $is_image = true;
        } else {
            $is_image = preg_match('/\.(png|jpe?g|gif)(&|\?)?/i', $url);
        }

        if ($is_image and !empty($_SERVER['HTTP_REFERER'])) {
            require_once(dirname(__FILE__).'/../lib/mediautils.php');
            $formatter->header('Status: 500');

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
        $formatter->header('Status: 500');
        echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">';
        echo '<html><head><title>'.$Config['sitename'].'</title>';
        echo "<meta name='viewport' content='width=device-width' />";
        echo '</head><body>';
        echo '<h1>500 Internal Server Error</h1>';
        echo '<div><a href="'.$url.'">Original source</a> : ';
        echo $url,'</div>';
        echo '<h2>Error Message</h2>';
        echo '<pre>', $ret['error'].'</pre>';
        echo '<hr>';
        echo '<div>', 'You can <a href="?action=fetch&amp;refresh=1&amp;url='.$url.'">refresh</a>';
        echo ' this URL manually</div>';
        echo '</body></html>';

        return;
    }
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

    $maxages = array(
        'site_alive'=>60*60, /* retry after one hour */
        'site_status'=>60*60*2, /* retry after two hours */
        'size_error'=>60, /* retry after 60 sec */
    );

    if (is_array($DBInfo->fetch_maxages))
        $maxages = array_merge($maxages, $DBInfo->fetch_maxages);

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
        if (!empty($params['refresh']) || !empty($params['.localrefresh'])) {
            $si->remove($siteurl);
        } else if (empty($params['refresh']) && ($check = $si->fetch($siteurl)) !== false) {
            $params['retval']['status'] = $check['status'];
            $params['retval']['error'] = $check['error'];
            return false;
        }
    }

    $sc = new Cache_text('fetchinfo');
    $error = null;

    if (empty($params['refresh']) and $sc->exists($url) and ($info = $sc->fetch($url)) !== false) {
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
                        'error'=>$params['retval']['error']), $maxages['site_alive']);

                return false;
            }

            $sc->update($url, array('size'=>-1,
                        'mimetype'=>'',
                        'error'=>$params['retval']['error'],
                        'status'=>$params['retval']['status']), $maxages['site_status']);

            return false;
        }

        if (isset($http->resp_headers['content-length']))
            $sz = $http->resp_headers['content-length'];

        if (isset($http->resp_headers['content-type']))
            $mimetype = $http->resp_headers['content-type'];
        else
            $mimetype = 'application/octet-stream';

        $info = array('size'=>$sz, 'mimetype'=>$mimetype);
        if (isset($http->resp_headers['last-modified']))
            $info['last-modified'] = $http->resp_headers['last-modified'];
        if (isset($http->resp_headers['etag']))
            $info['etag'] = $http->resp_headers['etag'];

        if (is_numeric($sz))
            $sc->update($url, $info);
        else
            $sc->update($url, $info, $maxages['size_error']);
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

    if ((!empty($params['images_only']) || !empty($DBInfo->fetch_images_only)) and !$is_image) {
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

    if (!empty($mimetype) and isset($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD'] == 'HEAD') {
        header('Content-Type: '.$mimetype);
        header('Content-Length: '.$sz);
        header('Last-Modified: '.substr(gmdate('r', filemtime($fetchfile)), 0, -5).'GMT');

        return null;
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
                        'status'=>$params['retval']['status']), $maxages['site_status']);
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

        $info = array('size'=>$sz, 'mimetype'=>$mimetype);
        if (isset($http->resp_headers['last-modified']))
            $info['last-modified'] = $http->resp_headers['last-modified'];
        if (isset($http->resp_headers['etag']))
            $info['etag'] = $http->resp_headers['etag'];

        // update error status.
        if (!empty($error))
            $sc->update($url, $info);
    }

    if (!empty($mimetype) and preg_match('/^image\//', $mimetype)) {
        $is_image = true;
    } else {
        $is_image = preg_match('/\.(png|jpe?g|gif)(&|\?)?/i', $url);
    }

    if (!empty($DBInfo->fetch_show_information) and $is_image and empty($_SERVER['HTTP_REFERER'])) {
        $img_url = $formatter->link_url('', '?action=fetch&amp;url='.$url);
        header('Pragma: no-cache');
        echo '<!DOCTYPE html>',"\n";
        echo "<html>\n<head>\n<title>".$DBInfo->sitename."</title>\n</head>\n<body>\n";
        echo '<h1>'._("Fetch File Information").'</h1>',"\n";
        echo '<h2>'._("Details").'</h2>',"\n";
        echo '<table>',"\n";
        echo '<tr><th>'._("Source URL").'</th><td><a href="'.$url.'">'.$url.'</a></td></tr>',"\n";
        echo '<tr><th>'._("Mime Type").'</th><td>'.$mimetype.'</td></tr>',"\n";
        echo '<tr><th>'._("File Size").'</th><td>'.$hsz.'</td></tr>',"\n";
        if (!empty($info['last-modified']))
            echo '<tr><th>'._("Last Modified").'</th><td>'.$info['last-modified'].'</td></tr>',"\n";
        echo '</table>',"\n";
        echo '<hr>',"\n";
        echo '<div>', 'You can <a href="?action=fetch&amp;refresh=1&amp;url='.$url.'">refresh</a>';
        echo ' this URL manually</div>',"\n";
        echo '<div class="externalImage"><div><img src="'.$img_url.'"></div>',"\n";
        echo '</body>',"\n",'</html>',"\n";
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
        if ($params['is_mobile']) {
            $force_thumb = (!isset($params['m']) or $params['m'] == 1);
        }
    }

    // generate thumb file to support low-bandwidth mobile version
    $thumbfile = '';
    $thumb_url = '';
    while ((!empty($params['thumb']) or $force_thumb) and
            preg_match('/^image\/(jpe?g|gif|png)$/', $mimetype)) {
        if (empty($thumb_width)) {
            $thumb_width = 320; // default
            if (!empty($DBInfo->fetch_thumb_width))
                $thumb_width = $DBInfo->fetch_thumb_width;
        }

        $thumbfile = preg_replace('@'.$ext.'$@', '.w'.$thumb_width.$ext, $fetchfile);
        if (!empty($fetch_url))
            $thumb_url = preg_replace('@'.$ext.'$@', '.w'.$thumb_width.$ext, $fetch_url);
        if (empty($params['refresh']) && file_exists($thumbfile)) break;

        list($w, $h) = getimagesize($fetchfile);
        if ($w <= $thumb_width) {
            $thumbfile = $fetchfile;
            $thumb_url = $fetch_url;
            break;
        }

        require_once('lib/mediautils.php');
        // generate thumbnail using the gd func or the ImageMagick(convert)

        resize_image($ext, $fetchfile, $thumbfile, $w, $h, $thumb_width);
        break;
    }

    if (!empty($thumbfile))
        $fetchfile = $thumbfile;
    if (!empty($thumb_url))
        $fetch_url = $thumb_url;

    if (!empty($fetch_url) and !empty($DBInfo->fetch_use_cache_url)) {
        if (!empty($thumb_width)) header('X-Thumb-Width:'.$thumb_width);
        header("Pragma: no-cache");
        header('Cache-Control: public, max-age=0, s-maxage=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate', false);
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
