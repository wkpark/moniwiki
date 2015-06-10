<?php
// Copyright 2015 Won-Kyu Park <wkpark at gmail.com>
// All rights reserved. Distributable under GPLv2 see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2015-06-08
// Name: Avatar Plugin
// Description: Avatar Plugin using the Fetch plugin
// URL: MoniWiki:AvatarPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=avatar&user=foo
//

function do_avatar($formatter, $params = array())
{
    global $DBInfo;

    $is_anonymous = empty($params['user']) || strtolower($params['user']) == 'anonymous';

    if (empty($DBInfo->use_avatar) && $is_anonymous) {
        // 43byte 1x1 transparent gif
        // http://stackoverflow.com/questions/2933251/code-golf-1x1-black-pixel
        // http://www.perlmonks.org/?node_id=7974
        $maxage = 60*60*24;
        $gif = base64_decode('R0lGODlhAQABAJAAAAAAAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw');
        Header("Content-type: image/gif");
        Header("Content-length: ".strlen($gif));
        Header("Cache-Control: public, max-age=".$maxage.", length: ".strlen($gif));
        header('Connection: Close');
        echo $gif;
        flush();
        return;
    }

    if (is_int($DBInfo->use_avatar) || $DBInfo->use_avatar == 'avatar')
        $avatar = 'identicon';
    else
        $avatar = $DBInfo->use_avatar;

    $udb = &$DBInfo->udb;
    if (!$is_anonymous) {
        $user = $udb->getUser($params['user']);
        if ($user->id == 'Anonymous')
            $is_anonymous = true;

        if (!$is_anonymous) {
            if (!empty($user->info['avatar']) && strtolower($user->info['avatar']) != 'avatar') {
                $avatar = $user->info['avatar'];
            }
        }
    }

    if (!preg_match('@^https?://@', $avatar)) {
        require_once(dirname(__FILE__).'/'.$avatar.'.php');

        $act = 'do_'.$avatar;
        $act($formatter, $params);
        return;
    }

    require_once(dirname(__FILE__).'/fetch.php');

    $url = $avatar;

    $ret = array();
    $params['retval'] = &$ret;
    $params['call'] = true;
    $params['images_only'] = true;
    if ($formatter->refresh) $params['refresh'] = 1;
    macro_Fetch($formatter, $url, $params);

    $is_image = preg_match('/\.(png|jpe?g|gif)(&|\?)?/i', $url);

    if (!$is_image || !empty($ret['error'])) {
        if (!empty($ret['mimetype']) and
                preg_match('/^image\//', $ret['mimetype'])) {
            $is_image = true;
        }

        if (!empty($ret['error']) && $is_image) {
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

    return;
}

function macro_Avatar($formatter, $value, $params = array()) {
    return "<img src='?action=avatar&amp;user=".$value."' />";
}

// vim:et:sts=4:sw=4:
