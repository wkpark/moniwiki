<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a QRcode plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2015-11-13
// Name: QRCode plugin
// Description: QRCode Plugin using http://phpqrcode.sourceforge.net
// URL: MoniWiki:QRCodePlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[QR]] or ?action=qr
//

function macro_QR($formatter, $value = '', $params = array()) {
    if (isset($value[0]))
        $params['value'] = $value;
    $q = '?action=qr';
    if (isset($value[0]))
        $q.= '&amp;value='._urlencode($value);
    return '<img src="'.$q.'" />';
}

function do_qr($formatter, $params = array()) {
    global $Config;

    if (isset($params['value']) && isset($params['value'][0])) {
        $value = $params['value'];
    } else {
        $encoded = _urlencode(strtr($formatter->page->name, ' ', '_'));
        $value = qualifiedUrl($formatter->link_url($encoded));
    }

    if (!empty($Config['cache_public_dir']) and
            !empty($Config['cache_public_url'])) {
        $fc = new Cache_text('qr', array('ext'=>'png', 'dir'=>$Config['cache_public_dir']));
        $pngname = $fc->getKey($value);
        $pngfile = $Config['cache_public_dir'].'/'.$pngname;
        $png_url=
            !empty($Config['cache_public_url']) ? $Config['cache_public_url'].'/'.$pngname:
        $Config['url_prefix'].'/'.$pngfile;
    } else {
        $uniq = md5($value);
        $pngfile = $cache_dir.'/'.$uniq.'.png';
        $png_url = $cache_url.'/'.$uniq.'.png';
    }

    $img_exists = file_exists($pngfile);
    if (!$img_exists || $formatter->refresh) {
        require_once(dirname(__FILE__).'/../lib/phpqrcode.php');
        QRcode::png($value, $pngfile, 'l', 3, 1);
    }

    if (!empty($Config['use_cache_url'])) {
        header("Pragma: no-cache");
        header('Cache-Control: public, max-age=0, s-maxage=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate', false);
        $formatter->send_header(array('Status: 302', 'Location: '.$png_url));

        return null;
    }

    $down_mode = 'inline';
    header("Content-Type: image/png\r\n");
    $mtime = filemtime($pngfile);
    $lastmod = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
    $etag = md5($lastmod.$key);
    header('Last-Modified: ' . $lastmod);
    header('ETag: "'.$etag.'"');
    $maxage = 60*60*24*30;
    header('Cache-Control: public, max-age='.$maxage);
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    if (!$need) {
        header('HTTP/1.0 304 Not Modified');
        @ob_end_clean();
        return null;
    }

    @ob_clean();
    $ret = readfile($pngfile);
    return null;
}

// vim:et:sts=4:sw=4:
