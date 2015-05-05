<?php

/**
 * auto detect image mimetype
 *
 * @author  Won-Kyu Park <wkpark@gmail.com>
 * @since   2013/12/26
 * @license GPLv2
 */
function detect_image($filename) {
    $fp = @fopen($filename, 'rb');
    if (!is_resource($fp)) return false;
    $dat = fread($fp, 4);
    $tmp = unpack('a4', $dat);
    if ($tmp[1] == 'GIF8') {
        fclose($fp);
        return 'gif';
    }
    $tmp = unpack('C1h/a3a', $dat);
    if ($tmp['h'] == 0x89 && $tmp['a'] == 'PNG') {
        fclose($fp);
        return 'png';
    }
    $tmp = unpack('n1', $dat);
    if ($tmp[1] == 0xffd8) {
        fclose($fp);
        return 'jpeg';
    }
    fclose($fp);
    return false;
}

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
    $width = intval($width);
    if (!file_exists($from))
        return false; // silently ignore

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
        $source = @call_user_func($myfunc, $from);

        // is it valid resource ?
        if (!is_resource($source)) {
            // try to autodetect image
            $type = detect_image($from);
            if ($type === false) return false;

            $imgtype = $type;
            $myfunc = 'imagecreatefrom'.$imgtype;
            $source = call_user_func($myfunc, $from);
        }

        // save transparancy
        // Please see also
        // http://stackoverflow.com/questions/279236/how-do-i-resize-pngs-with-transparency-in-php
        if ($imgtype == 'png' || $imgtype == 'gif') {
            $transparency = imagecolortransparent($source);

            if ($transparency >= 0) {
                $tcol = imagecolorsforindex($source, $transparency);
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
        // from the comment of mediawiki
        // found at include/media/Bitmap.php
        // Don't resample for paletted GIF images.
        // It may just uglify them, and completely breaks transparency.
        if ($imgtype == 'gif')
	    $ret = imagecopyresized($img, $source, 0, 0, 0, 0, $width, $new_h, $w, $h);
        else
            $ret = imagecopyresampled($img, $source, 0, 0, 0, 0, $width, $new_h, $w, $h);
        $myfunc = 'image'.$imgtype;
        $ret = $myfunc($img, $to);

        imagedestroy($img);
        imagedestroy($source);
    } else {
        $fp = popen('convert -scale '.
                $width.' '.$from.' '.$to, 'r');
        @pclose($fp);
        $ret = true;
    }

    return $ret;
}

/**
 * create image and draw string using GD
 *
 * @author  Won-Kyu Park <wkpark@gmail.com>
 * @since   2013/12/24
 * @license GPLv2
 */
function image_msg($font_size, $font_face, $text, $width = 40) {
    $wrap = wordwrap($text, $width, "\n", true);
    $wrap = rtrim($wrap);
    $strs = explode("\n", $wrap);
    if (empty($font_face)) {
        $w = imagefontwidth($font_size) * $width;
        $dy = imagefontheight($font_size);
        $h = $dy * count($strs);
        $im = ImageCreate($w, $h);
        $y = 0;
    } else {
        putenv('GDFONTPATH='.getcwd().'/data');
        $w = 0;
        $h = 0;
        foreach ($strs as $str) {
            $bbox = imagettfbbox($font_size,
                0, $font_face, $str);
            if ($bbox[2] > $w)
                $w = $bbox[2];
            $h+= $bbox[3] - $bbox[5];
        }
        $dy = $bbox[3] - $bbox[5];
        $h = $dy * count($strs);
        $im = ImageCreateTruecolor($w, $h);
        $y = $dy;
    }
    $bg = ImageColorAllocate($im, 255, 255, 255); // white background
    $pen = ImageColorAllocate($im, 0, 0, 0); // black
    imagefill($im, 0, 0, $bg);

    foreach ($strs as $str) {
        if (empty($font_face))
            ImageString($im, $font_size, 0, $y, $str, 1);
        else
            ImageTtfText($im, $font_size, 0, 0, $y, $pen, $font_face, $str);
        $y+= $dy;
    }

    return $im;
}

// vim:et:sts=4:sw=4:
