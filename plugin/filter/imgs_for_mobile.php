<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a image path fix postfilter plugin for the MoniWiki
//

function _fix_thumbnails($m) {
    global $_img_thumb_width;

    $width = $_img_thumb_width;
    if ($width < 0) {
        // full size image
        $m[3] = preg_replace('@thumbnails/@', '', $m[3]);
        $path = '/'.$m[3].'.'.$m[5];
    } else {
        $path = '/'.$m[3].'.w'.$width.'.'.$m[5];
    }
    return $m[1].'src='.$m[2].$path.$m[2];
}

function postfilter_imgs_for_mobile($formatter, $value, $options = array()) {
    global $_img_thumb_width;
    if (empty($options['thumb_width'])) {
        return $value;
    }
    $_img_thumb_width = $options['thumb_width'];

    $chunks = preg_split('/(<[^>]+>)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 0, $sz = count($chunks); $i < $sz; $i++) {
        if (substr($chunks[$i], 0, 5) == '<img ' &&
                preg_match('@action=(fetch|download)@', $chunks[$i])) {
            if ($_img_thumb_width == 1) {
                // do not show images at all
                $dumm = preg_replace('@\bsrc=@', 'src="data:image/gif;base64,R0lGODlhAQABAJAAAAIIAwAAACH5BAUQAAAALAAAAAABAAEAAAIBRAA7" data-src=', $chunks[$i]);
                $dumm = preg_replace('@\bwidth=@', 'data-width=', $dumm);
                $chunks[$i] = $dumm;
                continue;
            }
            if (strpos($chunks[$i], 'action=download') !== false) {
                $dumm = preg_replace_callback('@(<img .*)src=(\'|\")\/([^\\2]+)\.w(\d+)\.(png|jpe?g|gif)\\2@i', '_fix_thumbnails', $chunks[$i]);
                $chunks[$i] = $dumm;
            } else {
                if ($_img_thumb_width < 0)
                    // full size image
                    $dumm = preg_replace('@&(amp;)?thumbwidth=(\d+)@', '', $chunks[$i]);
                else
                    $dumm = preg_replace('@thumbwidth=(\d+)@', 'thumbwidth='.$_img_thumb_width, $chunks[$i]);
                $chunks[$i] = $dumm;
            }
        }
    }

    return implode('', $chunks);
}

// vim:et:sts=4:sw=4:
