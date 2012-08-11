<?php
// Copyright 2008-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a SFD Fontforge glyph rendering plugin for the MoniWiki
//
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2008-05-20
// Date: 2008-12-19
// Name: a FontForge sfd renderer
// Description: a FontForge sfd glyph renderer
// URL: MoniWiki:FontForgePlugin
// Version: $Revision: 1.5 $
// License: GPL
// Usage: {{{#!sfd
// sfd char file
// }}}
//
// $Id: sfd.php,v 1.5 2010/09/07 12:11:49 wkpark Exp $

function processor_sfd($formatter,$value="") {
    global $DBInfo;

    $width=1000;
    $height=1000;

    $EM=1000;
    $op=array('l'=>1,'c'=>0,'m'=>1);

    $CONVERT="convert -transparent white -density 24x24 ";

    $vartmp_dir=&$DBInfo->vartmp_dir;

    if(getenv("OS")=="Windows_NT") {
        $NULL='NUL';
        $vartmp_dir=getenv('TEMP');
        #$convert="wconvert";
    }
    $cache_dir=$DBInfo->upload_dir."/SFD";
    $cache_url=!empty($DBInfo->upload_url) ? $DBInfo->upload_url.'/SFD' :
    $DBInfo->url_prefix.'/'.$cache_dir;

    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    $sfd_source=$value;

    # print "<pre>$sfd_source</pre>";

    $uniq=md5($sfd_source);

    $lines=explode("\n",$sfd_source);

    $f=0;
    $stat=0;
    $eps='';
    $oop='';
    foreach ($lines as $l) {
        if ($stat == 0 and preg_match('/^StartChar:\s+(.*)$/',$l)) {
            $stat=1;
        } else if ($stat == 1 and preg_match('/^Fore/',$l)) {
            $stat=2;
        } else if ($stat == 1 and preg_match('/^Width:\s+(\d+)$/',$l,$m)) {
            $width=$height=$m[1];
            $emscale=$EM/$width;
            $width=intval($emscale*$width);
            $height=intval($emscale*$height); # XXX

            $ascent=intval($height*0.8);
            $decent=intval($height*0.2);

            $date=date("Y-m-d H:i:s",time());
            $eps.= <<<HEAD
%!PS-Adobe-3.0 EPSF-3.0
%%Creator: Adobe Illustrator by MoniWiki
%%Title:
%%CreationDate: $date
%%BoundingBox: 0 0 $width $height
%%DocumentData: Clean7Bit
%%EndComments
%%BeginProlog
/bd { bind def } bind def
/incompound false def
/m { moveto } bd
/l { lineto } bd
/c { curveto } bd
/F { incompound not {fill} if } bd
/f { closepath F } bd
/S { stroke } bd
/*u { /incompound true def } bd
/*U { /incompound false def f} bd
/k { setcmykcolor } bd
/K { k } bd
%%EndProlog
%%BeginSetup
%%EndSetup
0.000000 0.000000 0.000000 1.000000 k
*u\n
HEAD;
        } else if ($stat == 2) {
            if (preg_match('/^\s*([\d+\s\.\-\+]+)\s+([clm])\s+\d+$/',$l,$m)) {
                $c=$m[1];
                $op=$m[2];
                $p=preg_split('/\s+/',$m[1]);
                if ($op == 'm' and $oop == 'l') $eps.= "f\n";
                $n=1;
                foreach ($p as $val) {
                    if ($n % 2 == 0) { $eps.= intval($emscale*($val+$decent))." ";}
                    else { $eps.= intval($emscale*$val)." ";}
                    $n++;
                }
                $eps.= "$op\n";
                $oop=$op;
                #print "$op ",$op{$op},"\n";
            } else if (preg_match('/^EndChar$/',$l)) {
                $stat=0;
                $eps.= "f\n*U\n";
                $eps.=<<<INFO
0 0 1 setrgbcolor
5 setlinewidth
0 $decent m
$width $decent l
stroke
0 0 0 setrgbcolor
0 0 m
$width 0 l
$width $height l
0 $height l
0 0 l
stroke
*U\n
INFO;
                $eps.="%%Trailer\n%%EOF\n";
                break;
            }
        }
    }

    if ($DBInfo->cache_public_dir) {
        $fc = new Cache_text('sfd', array('ext'=>'png', 'dir'=>$DBInfo->cache_public_dir));
        $pngname=$fc->getKey($uniq, false);
        $outpath_png= $DBInfo->cache_public_dir.'/'.$pngname;

        $png_url=
            $DBInfo->cache_public_url ? $DBInfo->cache_public_url.'/'.$pngname:
            $DBInfo->url_prefix.'/'.$outpath_png;
    } else {
        $outpath_png=$cache_dir.'/'.$uniq.'.png';
        $png_url=$cache_url.'/'.$uniq.'.png';

    }
    $outpath_eps="$vartmp_dir/$uniq.eps";

    if (!file_exists(dirname($outpath_png))) {
        umask(000);
        _mkdir_p(dirname($outpath_png),0777);
        umask(022);
    }

    if ($formatter->refresh || !file_exists($outpath_png)) {
        # write to eps file
        $ifp=fopen("$outpath_eps","w");
        fwrite($ifp,$eps);
        fclose($ifp);

        # convert
        $fp=popen("$CONVERT $outpath_eps $outpath_png".$formatter->NULL,'r');
        pclose($fp);

        # delete temporary files
        #unlink($outpath_eps);
    }
    return "<img class='tex' src='$png_url' alt='sfd' />";
}

// vim:et:sts=4:sw=4:
?>
