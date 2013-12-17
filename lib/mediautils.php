<?php

/**
 * resize images using GD func or ImageMagick convert
 *
 * @author  Won-Kyu Park <wkpark@gmail.com>
 * @since   2013/12/17
 * @license GPLv2
 */
function resize_image($ext, $from, $to, $w = 0, $h = 0, $width, $height = 0) {
    global $Config;

    if (empty($w) or empty($h)) list($w, $h) = getimagesize($from);

    // generate thumbnail using the gd func or the ImageMagick(convert)
    if (empty($Config['fetch_use_imagemagick']) and function_exists('gd_info')) {
        if (!empty($height)) $new_h = $height;
        else $new_h = $width*$h/$w;

        $img = imagecreatetruecolor($width, $new_h);
        if (preg_match("/\.(jpe?g)$/i", $ext))
            $imgtype = 'jpeg';
        else if (preg_match("/\.png$/i", $ext))
            $imgtype = 'png';
        else
            $imgtype = 'gif';

        $myfunc = 'imagecreatefrom'.$imgtype;
        $source = $myfunc($from);

        // save transparancy
        // Please see also
        // http://stackoverflow.com/questions/279236/how-do-i-resize-pngs-with-transparency-in-php
        if ($imgtype == 'png' || $imgtype == 'gif') {
            $transparency = imagecolortransparent($source);

            if ($transparency >= 0) {
                // guess transparent color
                $tidx = imagecolorat($source, 1, 1); // FIXME
                $tcol = imagecolorsforindex($source, $tidx);
                $transparency = imagecolorallocate($img,
                        $tcol['red'], $tcol['green'], $tcol['blue']);
                imagefill($img, 0, 0, $transparency);
                imagecolortransparent($img, $transparency);
            } elseif ($imgtype == 'png') {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $tcol = imagecolorallocatealpha($img, 0, 0, 0, 127);
                imagefill($img, 0, 0, $tcol);
            }
        }

        // resize
        $ret = imagecopyresampled($img, $source, 0, 0, 0, 0, $width, $new_h, $w, $h);
        $myfunc = 'image'.$imgtype;
        $ret = $myfunc($img, $to);
    } else {
        $fp = popen('convert -scale '.
                $width.' '.$from.' '.$to, 'r');
        @pclose($fp);
        $ret = true;
    }

    return $ret;
}

// vim:et:sts=4:sw=4:
