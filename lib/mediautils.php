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
    $test = substr($tmp[1], 0, 2);
    if ($test == 'BM') {
        fclose($fp);
        return 'bmp';
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
 * from http://php.net/manual/kr/function.imagecreatefromwbmp.php#86214
 * by AeroX and alexander 2008
 */
function imagecreatefrombmp($bmpfile) {
    // Load the image into a string
    $file = fopen($bmpfile, 'rb');
    $read = fread($file, 10);
    while(!feof($file)) {
        $read.= fread($file, 2048);
    }

    $temp = unpack("H*", $read);
    $hex = $temp[1];
    $header = substr($hex, 0, 108);

    // Process the header
    // Structure: http://www.fastgraph.com/help/bmp_header_format.html
    if (substr($header, 0, 4) == "424d") {
        // Cut it in parts of 2 bytes
        $header_parts = str_split($header, 2);

        // Get the width 4 bytes
        $width = hexdec($header_parts[19].$header_parts[18]);

        // Get the height 4 bytes
        $height = hexdec($header_parts[23].$header_parts[22]);

        // Unset the header params
        unset($header_parts);
    }

    // Define starting X and Y
    $x = 0;
    $y = 1;

    // Create newimage
    $image = imagecreatetruecolor($width, $height);

    // Grab the body from the image
    $body = substr($hex, 108);

    // Calculate if padding at the end-line is needed
    // Divided by two to keep overview.
    // 1 byte = 2 HEX-chars
    $body_size = (strlen($body) / 2);
    $header_size = ($width * $height);

    // Use end-line padding? Only when needed
    $usePadding = ($body_size > ($header_size * 3) + 4);

    // Using a for-loop with index-calculation instaid of str_split to avoid large memory consumption
    // Calculate the next DWORD-position in the body
    for ($i = 0; $i < $body_size; $i+= 3) {
        // Calculate line-ending and padding
        if ($x >= $width) {
            // If padding needed, ignore image-padding
            // Shift i to the ending of the current 32-bit-block
            if ($usePadding)
                $i += $width % 4;

            // Reset horizontal position
            $x = 0;

            // Raise the height-position (bottom-up)
            $y++;

            // Reached the image-height? Break the for-loop
            if ($y > $height)
                break;
        }

        // Calculation of the RGB-pixel (defined as BGR in image-data)
        // Define $i_pos as absolute position in the body
        $i_pos = $i * 2;
        $r = hexdec($body[$i_pos + 4].$body[$i_pos + 5]);
        $g = hexdec($body[$i_pos + 2].$body[$i_pos + 3]);
        $b = hexdec($body[$i_pos].$body[$i_pos + 1]);

        // Calculate and draw the pixel
        $color = imagecolorallocate($image, $r, $g, $b);
        imagesetpixel($image, $x, $height - $y, $color);

        // Raise the horizontal position
        $x++;
    }

    // Unset the body / free the memory
    unset($body);

    // Return image-object
    return $image;
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
        if ($imgtype == 'bmp')
            $myfunc = 'imagejpeg';
        else
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

    $min_height = 200;
    $margin = 40;
    $padding = $margin >> 1;
    if (empty($font_face)) {
        $max = 10;
        foreach ($strs as $s) {
            $l = strlen($s);
            $max = strlen($s) > $max ? $l : $max;
        }
        $w = imagefontwidth($font_size) * $max + $margin;
        $dy = imagefontheight($font_size);
        $h = $dy * count($strs);
        $y = 0;
        if ($min_height > $h) {
            $y = ($min_height - $h) >> 1;
            $h = $min_height;
        }
        $im = ImageCreate($w, $h);
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
        $w+= $margin;
        $dy = $bbox[3] - $bbox[5];
        $h = $dy * count($strs);
        $y = $dy;
        if ($min_height > $h) {
            $y = ($min_height - $h) >> 1;
            $h = $min_height;
            $y+= $dy >> 1;
        }
        $im = ImageCreateTruecolor($w, $h);
    }
    $bg = ImageColorAllocate($im, 255, 255, 255); // white background
    $pen = ImageColorAllocate($im, 0, 0, 0); // black
    imagefill($im, 0, 0, $bg);

    foreach ($strs as $str) {
        if (empty($font_face))
            ImageString($im, $font_size, $padding, $y, $str, 1);
        else
            ImageTtfText($im, $font_size, 0, $padding, $y, $pen, $font_face, $str);
        $y+= $dy;
    }

    return $im;
}

// vim:et:sts=4:sw=4:
