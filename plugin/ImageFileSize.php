<?php
// Copyright 2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a ImageFileSize plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2013-04-28
// Name: ImageFileSize
// Description: fetch the size of external images plugin
// URL: MoniWiki:ImageFileSizeMacro
// Version: $Revision: 1.0 $
// License: GPL
//
// Usage: [[ImageUrlSize(url)]]
//

function macro_ImageFileSize($formatter, $value = '') {
    global $Config;

    if (empty($value)) return '';

    require_once "lib/HTTPClient.php";

    $sz = 0;
    // check if it is valid or not
    if (preg_match('/^(https?|ftp):\/\/.*\.(jpg|jpeg|gif|png)(?:\?|&)?/', $value)) {
        $sc = new Cache_text('imagefilesize');

        if ($sc->exists($value) and $sc->mtime($value) < time() + 60*60*24*20) {
            $sz = $sc->fetch($value);
        } else {
            $http = new HTTPClient();

            // set referrer
            $referer = '';
            if (!empty($Config['fetch_referer_re'])) {
                foreach ($Config['fetch_referer_re'] as $re=>$ref) {
                    if (preg_match($re, $value)) {
                        $referer = $ref;
                        break;
                    }
                }
            }

            // default referrer
            if (empty($referer) and !empty($Config['fetch_referer']))
                $referer = $Config['fetch_referer'];

            $http->nobody = true;
            $http->referer = $referer;
            $http->sendRequest($value, array(), 'GET');
            $http->status;
            if (isset($http->resp_headers['content-length']))
                $sz = $http->resp_headers['content-length'];
            $sc->update($value, $sz);
        }

        $unit = array('Bytes', 'KB', 'MB', 'GB');
        for ($i = 0; $i < 4; $i++) {
          if ($sz <= 1024) {
            break;
          }
          $sz = $sz / 1024;
        }
        return round($sz, 2).' '.$unit[$i];
    }
    return _("Unknown");
}

// vim:et:sts=4:sw=4:
