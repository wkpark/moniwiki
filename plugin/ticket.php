<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a ticket plugin for the MoniWiki
// $Id$

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

    if ($options['__seed']) {
        // check seed
        // check referer
        $passwd=getTicket($options['__seed'],$_SERVER['REMOTE_ADDR'],4);
    } else {
        $options['title']=_("Invalid use of ticket");
        do_invalid($formatter,$options);
        return;
    }

    if ($DBInfo->gdfontpath)
        putenv('GDFONTPATH='.$DBInfo->gdfontpath);
    if (function_exists('ImageTtfText')) {
        if ($DBInfo->ticket_font) {
            $FONT=$DBInfo->ticket_font;
            //$FONT="/home/foobar/data/PenguinAttack.ttf";
            if ($FONT{0}=='/' and !file_exists($FONT)) {
                $use_ttf=0;
            } else {
                $FONT=$DBInfo->ticket_font;
                $use_ttf=1;
            }
        }
    }
        
    if ($use_ttf) {
        $pointsize=16;
        $angle=0;
        //$size = Imagettfbbox($pointsize, 0, $FONT, $passwd);
        // XXX segfault :(
        $size=array(0,0,0,20,65);
        //$size=array(0,0,0,20,50);
        $w=$size[4]+20; # margin=20 ?
        $h=$size[3]- $size[5]+10; # margin= 10 ?
    } else {
        $FONT=5; // giant
        if ($DBInfo->ticket_gdfont)
            $FONT=$DBInfo->ticket_gdfont;
        $w=imagefontwidth($FONT)*strlen($passwd)+10;
        $h=imagefontheight($FONT)+10;
    }

    Header("Content-type: image/png");
    $im= ImageCreate(($size[4]+20), ($size[5]+10));
    $im= ImageCreate($w,$h);
    $color=array();
    $color[]= ImageColorAllocate($im, 240, 240, 240); // background
    $color[]= ImageColorAllocate($im, 0, 0, 0); // black
    $color[]= ImageColorAllocate($im, 255, 255, 255); // white
    $pen=rand(3,19);
    for ($i=0;$i<18;$i++)
        $color[]= ImageColorAllocate($im,rand(100,200),rand(100,200),rand(100,200));
    if ($use_ttf) {
        ImageTtfText($im,$pointsize, $angle, 6, 25, $color[$pen], $FONT,
            $passwd);
        ImageTtfText($im,$pointsize, $angle, 7, 24, $color[$pen], $FONT,
            $passwd);
    } else {
        ImageString($im,$FONT, 5, 3, $passwd, $color[$pen]);
        ImageString($im,$FONT, 4, 4, $passwd, $color[$pen]);
    }

    switch ($DBInfo->use_ticket) {
        case 1:
            _effect_blur($im,$color,1,1);
            break;
        case 3:
            _effect_blur($im,$color,1,1);
        case 2:
        default:
            _effect_grid($im,$color,$pen);
            break;
    }

    ImagePng($im);
    ImageDestroy($im);
}

// vim:et:sts=4:sw=4:
?>
