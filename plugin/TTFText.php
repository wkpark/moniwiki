<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a TTF Text plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-04-17
// Name: TTFText
// Description: TTF Text plugin with Background image technic.
// URL: MoniWiki:TTFTextPlugin
// Version: $Revision: 1.3 $
// License: GPL
//
// Usage: [[TTFText(text,fontname=Eunjin,fontsize=12,float=left)]]
//
// $Id: TTFText.php,v 1.3 2010/09/07 12:11:49 wkpark Exp $

function macro_TTFText($formatter,$value,$params=array()) {
    global $DBInfo;

    $pc=array(0,0,0);
    if ($DBInfo->gdfontpath)
        putenv('GDFONTPATH='.$DBInfo->gdfontpath);
    else
        putenv('GDFONTPATH='.getcwd().'/data');

    $args=explode(',',$value);

    $text=array_shift($args);

    $float='';
    $imagemode=0;
    $args= empty($args) ? array():($args);
    foreach ($args as $arg) {
        list($k,$v)=explode('=',trim($arg),2);
        if ($k == 'font') {
            $font=$v;
            if ($font{0}!='/') {
                $real=getcwd().'/data/'.$font; # XXX
                if (!preg_match('/\.ttf$/i',$real))
                    $real.='.ttf';
                if (file_exists($real)) $font=$real;
            }
        } else if ($k == 'font-size' or $k == 'fontsize') {
            $fontsize=intval($v);
        } else if ($k == 'dropcap' and (empty($v) or $v == 1)) {
            $float="float:left;";
        } else if ($k == 'img' or $k == 'image') {
            $imagemode=1;
        } else if ($k == 'float' and in_array($v, array('left','middle','right'))) {
            $float="float:$v;";
        } else if ($k == 'color' and preg_match('/^#[0-9a-f]{6}$/',$v)) {
            $pc=sscanf($v, '#%2x%2x%2x');
        }
    }

    $font=$font ? $font : realpath('./data/Eunjin.ttf');

    $pointsize=$fontsize ? $fontsize:15;

    $uniq=md5($value);
    $vartmp_dir=&$DBInfo->vartmp_dir;
    $cache_dir=$DBInfo->upload_dir."/TTFText";
    $cache_url=$DBInfo->upload_url ? $DBInfo->upload_url.'/TTFText':
        $DBInfo->url_prefix.'/'.$cache_dir;

    if ($DBInfo->cache_public_dir) {
        $fc=new Cache_text('ttftext', array('ext'=>'png','dir'=>$DBInfo->cache_public_dir));
        $pngname=$fc->getKey($uniq, false);
        $outpath_png= $DBInfo->cache_public_dir.'/'.$pngname;
        
        $png_url=
            $DBInfo->cache_public_url ? $DBInfo->cache_public_url.'/'.$pngname:
            $DBInfo->url_prefix.'/'.$outpath_png;
    } else {
        $outpath_png=$cache_dir.'/'.$uniq.'.png';
        $png_url=$cache_url.'/'.$uniq.'.png';
    }

    $bbox= imageTtfBBox($pointsize, 0,$font,$text);
    $h = $bbox[3] - $bbox[5];
    $w = $bbox[2] - $bbox[0];

    /*
    $w= mb_strlen($text,$DBInfo->charset)*$pointsize;
    print "$w x $h<br />";
     */

    $margin=4; // XXX
    $w= $w + 2*$margin;
    $h= $h + $margin;

    if ($formatter->refresh || !file_exists($outpath_png)) {
        if (!file_exists(dirname($outpath_png))) {
            umask(000);
            _mkdir_p(dirname($outpath_png),0777);
            umask(022);
        }

        $sx = 0;
        $sy = $h;

        $im= ImageCreateTruecolor($w,$h);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        $pen = imagecolorallocate($im, $pc[0], $pc[1], $pc[2]);
        imagefill ($im, 0, 0, $white );

        $sx=$margin;    
        $sy=$margin/2+$pointsize;    
        ImageTtfText($im,$pointsize, 0, $sx, $sy, $pen ? $pen:$black, $font, $text);
        #ImageftText($im,$pointsize, 0, $sx, $sy, $pen ? $pen:$black, $font, $text);

        imagePng($im,$outpath_png);
    }
    $png_url=qualifiedUrl($png_url);

    if ($imagemode) {
        $text= str_replace('"',"&#34;",$text);
        return "<img src=\"$png_url\" alt=\"$text\" style='vertical-align:middle' />";
    }
    return "<span style='display:block;$float".
        "background:url(\"$png_url\") no-repeat;width:${w}px;height:${h}px;' />".
        "<span style='display:none'>".$text."</span></span>";
}

// vim:et:sts=4:sw=4
?>
