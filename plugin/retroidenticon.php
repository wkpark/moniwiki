<?php
// Copyright 2014 Won-Kyu Park <wkpark at gmail.com>
// All rights reserved. Distributable under GPLv2 see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2014-03-11
// Name: Retro Identicon
// Description: Retro Identicon Plugin
// URL: MoniWiki/RetroIdenticon
// Reference: http://writings.orangegnome.com/writes/creating-identicons/
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=retroidenticon&seed=xxxx
//

function do_retroidenticon($formatter, $params = array())
{
    $pixeldim = 5;
    $pixelsize = 80;
    $outsize = $pixelsize * $pixeldim;

    // Please see http://writings.orangegnome.com/writes/creating-identicons/
    // Get seed
    if (!empty($params['seed']))
        $seed = $params['seed'];
    else
        $seed = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);

    if (!empty($params['pixelsize']) and $params['pixelsize'] > 1 and $params['pixelsize'] < 80) {
        $pixelsize = $params['pixelsize'];
        $outsize = $pixelsize * $pixeldim;
    }

    // HTTP Conditional get.
    $mtime = filemtime(__FILE__);
    $lastmod = substr(gmdate('r', $mtime), 0, -5).'GMT';

    $etag = $mtime . $outsize . $seed;
    $etag = md5($etag);
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    $maxage = 60*60*24*7;

    header('Last-Modified: '.$lastmod);
    header('ETag: "' .$etag. '"');
    header('Cache-Control: private, max-age='.$maxage);
    header('Pragma: cache');
    if (!$need) {
        header('HTTP/1.0 304 Not Modified');
        @ob_end_clean();
        return true;
    }

    // Convert seed to MD5
    $hash = md5($seed);
    // Get color from first 6 characters
    $color = substr($hash, 0, 6);

    $pixels = array();

    // make a multidimension array
    $half = round(($pixeldim - 1) / 2);
    for ($j = 0; $j < $pixeldim; $j++) {
        for ($i = 0; $i <= $half; $i++) {
            $k = 6 + $i * $pixeldim + $j;
            $pixels[$i][$j] = hexdec(substr($hash, $k, 1)) % 2 === 1;
            $pixels[$pixeldim - $i - 1][$j] = $pixels[$i][$j];
        }
    }

    // set image size
    $image = imagecreatetruecolor($outsize, $outsize);
    // forground color. The hex code we assigned earlier needs to be decoded to RGB
    $color = imagecolorallocate($image,
            hexdec(substr($color, 0, 2)) & 255,
            hexdec(substr($color, 2, 2)) & 255,
            hexdec(substr($color, 4, 2)) & 255);

    // FIXME background color
    $bg = imagecolorallocate($image, 238, 238, 238);

    // Color the pixels
    for ($k = 0; $k < count($pixels); $k++) {
        for ($l = 0; $l < count($pixels[$k]); $l++) {
            // default pixel color is the background color
            $pixel = $bg;

            // If the value in the $pixels array is true, make the pixel color the primary color
            if ($pixels[$k][$l]) {
                $pixel = $color;
            }

            // Color the pixel. In a 400x400 image with a 5x5 grid of "pixels", each "pixel" is 80x80
            imagefilledrectangle($image, $k * $pixelsize, $l * $pixelsize, ($k + 1) * $pixelsize, ($l + 1) * $pixelsize, $pixel);
        }
    }

    // Output the image
    header('Content-type: image/png');
    imagepng($image);
    imagedestroy($image);
    return;
}

// vim:et:sts=4:sw=4:
