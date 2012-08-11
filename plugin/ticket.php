<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple CAPTCHA ticket plugin for the MoniWiki
//
// $Id: ticket.php,v 1.10 2010/08/26 12:43:21 wkpark Exp $

function _effect_distort($image, $factor=40, $grad=1, $method = 0) {
    // from http://www.codeproject.com/aspnet/CaptchaNET_2.asp Farshid Hosseini
    $width = imagesx($image);
    $height = imagesy($image);

    $fact=$factor/25;
    $disx=rand(4,10)*(rand(0,1) ? 1:-1)*$fact;
    $disy=rand(4,12)*(rand(0,1) ? 1:-1)*$fact;
    $yf=rand(30,45)*$fact;
    $xf=rand(80,95)*$fact;

    // from http://www.captcha.ru/captchas/multiwave/
    // KCAPTCHA randomize

    // periods
    $rand1 = mt_rand(750000,1200000)/10000000;
    $rand2 = mt_rand(750000,1200000)/10000000;
    $rand3 = mt_rand(750000,1200000)/10000000;
    $rand4 = mt_rand(750000,1200000)/10000000;
    // phases
    $rand5 = mt_rand(0,31415926)/10000000;
    $rand6 = mt_rand(0,31415926)/10000000;
    $rand7 = mt_rand(0,31415926)/10000000;
    $rand8 = mt_rand(0,31415926)/10000000;
    // amplitudes
    $rand9 = mt_rand(330,420)/110;
    $rand10 = mt_rand(330,450)/110;

    // make new canvas img
    $canvas=imagecreate($width,$height);
    $bgcolor = imagecolorsforindex($image, 0);
    $new = imagecolorallocate($canvas, $bgcolor['red'], $bgcolor['green'], $bgcolor['blue']);
    $fgcolor = imagecolorsforindex($image, 1);
    $new = imagecolorallocate($canvas, $fgcolor['red'], $fgcolor['green'], $fgcolor['blue']);
    imagepalettecopy($canvas,$image);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if (empty($method)) {
                // Adds a simple wave
                $newX = ($x + ($disx * Sin(3.141592 * $y / $xf)));

                $t = ($x - $width/2) / $yf * 2.4;
                $t = $t*$t;
                $newY = 
                    #($y + ($distort * sin(1.5*3.141592 * $x / $yf)));
                    ($y + ($disy * exp(-$t)*sin(3.141592 * $x / $yf)));
            } else {
                // KCAPTCHA method
                $newX = $x + (sin($x*$rand1+$rand5)+sin($y*$rand3+$rand6))*$rand9;
                $newY = $y + (sin($x*$rand2+$rand7)+sin($y*$rand4+$rand8))*$rand10;
            }

            if ($grad) { # with gradient effect based on above functions
                if ($newY > $height or $newY < 0) $newY=0;
                if ($newX < 0) $newX=0;
                else if ($newX > $width) $newX=$width;

                $newcolor = _antialias_color($image, $x, $y, $newX, $newY);
                if ($newcolor === false) // this is background color
                    $newcolor = $bgcolor;

                $r = $newcolor['red'];
                $g = $newcolor['green'];
                $b = $newcolor['blue'];

                $gratio=120;
                $bratio=100;
                $pratio=125;
            
                $red = (int)($r);
                $green = (int)($newX/$width*$gratio+$g/255*$pratio);
                $blue = (int)($newY/$height*$bgatio+$b/255*$pratio);
                $new = imagecolorallocate($canvas, $red, $green, $blue);
                $new = imagecolorclosest($canvas, $red, $green, $blue);

                
                imageSetPixel($canvas,$x,$y,$new);
            } else {
                if ($newX <0 || $newY < 0 || $newX >= $width-1 || $newY >= $height-1) {
                    continue;
                }

                $newcolor = _antialias_color($image, $x, $y, $newX, $newY);
                // ignore background 
                if ($newcolor === false) continue;

                $red = $newcolor['red'];
                $green = $newcolor['green'];
                $blue = $newcolor['blue'];
                $new = imagecolorallocate($canvas, $red, $green, $blue);
                $new = imagecolorclosest($canvas, $red, $green, $blue);
                imageSetPixel($canvas,$x,$y,$new);
            }
        }
    }

    imageCopy($image,$canvas,0,0,0,0,$width,$height);
}

function _effect_wave($image) {
    // from http://kr.php.net/manual/en/function.imagecopy.php#65555
    // and some modification
    $width = imagesx($image);
    $height = imagesy($image);
    $x=3;
    $y=-5;

    $ext=rand(4,7);
    $se=rand(10,15);

    $canvas=imagecreate($width,$height+5);
    #imageCopy($canvas,$image,0,0,0,0,$width,$height);

    for ($i=0;$i<$width;$i+=2){
        imagecopy($canvas,$image,
            $x+$i-2,$y+(-sin($i/$se+0.5)+cos($i/$se*0.8))*$ext,
            $x+$i,$y,
            2,$height);
    }

    $ext=rand(10,15);
    $se=rand(15,17);

    $canvas2=imagecreate($width,$height+5);

    for ($i=0;$i<$height;$i+=2){
        imagecopy($canvas2,$canvas,
            $x+(sin($i/$se+0.5))*$ext,$y+$i+2,
            $x,$y+$i,
            $width,2);
    }

    imageCopy($image,$canvas2,0,0,0,0,$width,$height+5);
}

/**
 * Please http://www.captcha.ru/captchas/multiwave/
 * get antialiasing color of the new position base on the source img coord.
 * rewriten for non truecolor img by wkpark at kldp.org
 *
 * @author wkpark@kldp.org
 * @since  2010/08/26
 */
function _antialias_color($img, $x, $y, $sx, $sy) {
    $frsx = $sx - floor($sx);
    $frsy = $sy - floor($sy);
    $frsx1 = 1 - $frsx;
    $frsy1 = 1 - $frsy;

    $color = array();
    $color[] = imagecolorat($img, $sx, $sy) & 0xFF;
    $color[] = imagecolorat($img, $sx+1, $sy) & 0xFF;
    $color[] = imagecolorat($img, $sx, $sy+1) & 0xFF;
    $color[] = imagecolorat($img, $sx+1, $sy+1) & 0xFF;

    $test = 0;
    foreach ($color as $c) {
        $test+= $c;
    }
    // is it background color
    if ($test == 0) return false;

    $cc = array('red'=>array(), 'green'=>array(), 'blue'=>array());
    foreach ($color as $c) {
        $i = imagecolorsforindex($img, $c);
        $cc['red'][] = $i['red'];
        $cc['green'][] = $i['green'];
        $cc['blue'][] = $i['blue'];
    }

    $newcolor = array();
    foreach ($cc as $k=>$v) {
        $newcolor[$k] = $v[0] * $frsx1 * $frsy1
                + $v[1] * $frsx * $frsy1
                + $v[2] * $frsx1 * $frsy
                + $v[3] * $frsx * $frsy;
        if ($newcolor[$k] > 255) $newcolor[$k] = 255;
    }
    return $newcolor;
}

function _effect_blur($image,$color,$dx=1,$dy=0) {
// please see http://www.hudzilla.org/phpbook/read.php/11_2_23
    $imagex = imagesx($image);
    $imagey = imagesy($image);

    for ($x = 0; $x < $imagex; ++$x) {
        for ($y = 0; $y < $imagey; ++$y) {
            $distx = rand(-$dx, $dx);
            $disty = rand(-$dy, $dy);
            $disty = 0;

            if ($x + $distx >= $imagex) continue;
            if ($x + $distx < 0) continue;
            if ($y + $disty >= $imagey) continue;
            if ($y + $disty < 0) continue;

            $oldcol = imagecolorat($image, $x, $y);
            $newcol = imagecolorat($image, $x + $distx, $y + $disty);
            if ($oldcol == $newcol) continue;

            if(function_exists('imageistruecolor') && imageistruecolor($image)){
                $r1 = ($oldcol >> 16);
                $g1 = ($oldcol >> 8) & 0xFF;
                $b1 = $oldcol & 0xFF;
                $r2 = ($newcol >> 16);
                $g2 = ($newcol >> 8) & 0xFF;
                $b2 = $newcol & 0xFF;
            } else {
                $i = imagecolorsforindex($image, $oldcol);
                $r1 = $i['red'];
                $g1 = $i['green'];
                $b1 = $i['blue'];
                $i = imagecolorsforindex($image, $newcol);
                $r2 = $i['red'];
                $g2 = $i['green'];
                $b2 = $i['blue'];
            }
            $red = (int)(($r1 + $r2)*0.5);
            $green = (int)(($g1 + $g2)*0.5);
            $blue = (int)(($b1 + $b2)*0.5);
            $new = imagecolorallocate($image, $red, $green, $blue);
            $new = imagecolorclosest($image, $red, $green, $blue);
            
            imagesetpixel($image, $x, $y, $new);
            imagesetpixel($image, $x + $distx, $y + $disty, $new);
        }
    }
}

function _effect_grid($im,$color,$pen=4) {
    $w = imagesx($im);
    $h = imagesy($im);
    for ($y=0;$y<$h;$y+=rand(3,6))
        ImageLine ($im, 0,$y,$w,$y,$color[$pen]);
    ImageLine ($im, 0,$h-1,$w,$h-1,$color[$pen]);
    for ($x=0;$x<$w;$x+=rand(4,8))
        ImageLine ($im, $x,0,$x,$h,$color[$pen]);
    ImageLine ($im, $w-1,0,$w-1,$h,$color[$pen]);
}

function do_ticket($formatter,$options) {
    global $DBInfo;

    $word_length=4;

    if (!empty($options['__seed'])) {
        // check seed
        // check referer
        $passwd=getTicket($options['__seed'],$_SERVER['REMOTE_ADDR'],
            $word_length);
    } else {
        $options['title']=_("Invalid use of ticket");
        do_invalid($formatter,$options);
        return;
    }

    if (!empty($DBInfo->gdfontpath)) {
        putenv('GDFONTPATH='.$DBInfo->gdfontpath);
    } else {
        // set default GDFONTPATH
        $old = getenv('GDFONTPATH');
        $path = $DBInfo->data_dir;
        if ($old)
            $path = $old.':'.$new;
        putenv('GDFONTPATH='.$path);
    }
        
    if (function_exists('ImageTtfText')) {
        while (!empty($DBInfo->ticket_font)) {
            if (is_numeric($DBInfo->ticket_font)) {
                break;
            }
            $FONT=$DBInfo->ticket_font;
            //$FONT="/home/foobar/data/PenguinAttack.ttf";
            if ($FONT{0}=='/' and !file_exists($FONT)) {
                $use_ttf=0;
            } else {
                $FONT=$DBInfo->ticket_font;
                $use_ttf=1;
            }
            break;
        }
    }
    if (!empty($use_ttf)) {
        $pointsize=!empty($DBInfo->ticket_font_size) ? $DBInfo->ticket_font_size:16;
        $angle=0;
        //$size = Imagettfbbox($pointsize, 0, $FONT, $passwd);
        // XXX segfault :(

        $margin=$pointsize/2;
        $size=array(0,0,0,20,65);
        //$size=array(0,0,0,20,50);
        //$w=$size[4]+20; # margin=20 ?
        $w=$pointsize*$word_length + $margin;
        $h=$pointsize+$margin;
        if ($DBInfo->use_ticket & 23) $h+=$pointsize/3;
    } else {
        $FONT=5; // giant
        if (!empty($DBInfo->ticket_gdfont))
            $FONT=$DBInfo->ticket_gdfont;
        $w=imagefontwidth($FONT)*strlen($passwd)+10;
        $h=imagefontheight($FONT);
        $pointsize = $h;
        $h+= 10;
    }

    $im= ImageCreate($w,$h);
    $color=array();
    if (isset($DBInfo->captcha_bgcolor) and preg_match('/^#[0-9a-fA-F]$/', $DBInfo->captcha_bgcolor)) {
        $r = substr($DBInfo->captcha_bgcolor, 1, 2);
        $g = substr($DBInfo->captcha_bgcolor, 3, 2);
        $b = substr($DBInfo->captcha_bgcolor, 5, 2);
        $color[]= ImageColorAllocate($im, hexdec($r), hexdec($g), hexdec($b)); // background
    } else {
        $color[]= ImageColorAllocate($im, 240, 240, 240); // default background
    }
    $color[]= ImageColorAllocate($im, 0, 0, 0); // black
    $color[]= ImageColorAllocate($im, 255, 255, 255); // white
    $pen=rand(3,19);
    $pen1=rand(3,19);
    for ($i=0;$i<18;$i++)
        $color[]= ImageColorAllocate($im,rand(100,200),rand(100,200),rand(100,200));

    if (!empty($use_ttf)) {
        $sx=$margin;
        $sy=$margin/2+$pointsize;
        ImageTtfText($im,$pointsize, $angle, $sx, $sy+1, $color[$pen], $FONT,
            $passwd);
        ImageTtfText($im,$pointsize, $angle, $sx+1, $sy, $color[$pen], $FONT,
            $passwd);
    } else {
        ImageString($im,$FONT, 5, 3, $passwd, $color[$pen]);
        ImageString($im,$FONT, 4, 4, $passwd, $color[$pen]);
    }

    $grad = '';
    if ($DBInfo->use_ticket & 8) $grad=1;
    if ($DBInfo->use_ticket & 4)
        _effect_distort($im,$pointsize,$grad);
    else if ($DBInfo->use_ticket & 16)
        _effect_distort($im,$pointsize,$grad, 1);
    if ($DBInfo->use_ticket & 1)
        _effect_blur($im,$color,1,1);
    if ($DBInfo->use_ticket & 2)
        _effect_grid($im,$color,$pen1);

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
}

// vim:et:sts=4:sw=4:
?>
