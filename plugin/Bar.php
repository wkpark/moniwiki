<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple progress bar plugin for the MoniWiki
//
// Usage: [[Bar(10%)]] [[Bar(0.5)]]
//
// $Id: Bar.php,v 1.3 2005/08/08 06:44:42 wkpark Exp $

function macro_Bar($formatter,$value) {
    global $DBInfo;
    static $width;

    $imgdir=$DBInfo->imgs_dir;
    $iconset='blue';
    $full_width=1;
    $notext=0;

    # parse args
    $dum=explode(',',$value);
    $value=array_pop($dum); // last arg is percentage value.
    if (in_array('fullwidth',$dum)) $full_width=1;
    if (in_array('notext',$dum)) $notext=1;

    $dum=trim($value);
    # make percent value
    if (substr($dum,-1) == '%') {
        $val=substr($dum,0,-1);
        if ($val > 100.0) $val=100;
    } else {
        $p=strpos($dum,'/'); # parse 10/80
        if ($p !== false)
            $dum=((int)strtok($dum,'/'))/((int)strtok(''));

        if ($dum > 1.0) $val=100;
        else $val=$dum*100.0;
    }

    $ival=0;
    if ($val < 100.0) $ival=100.0 - $val;
    $width=300;
    if ($width) {
        $fval=(int)($val*$width/100.0).'px';
        $ival=(int)($ival*$width/100.0).'px';
    } else {
        $val.='%';
        $ival.='%';
    }
    $img="<div style='white-space: nowrap;'>";
    $img.="<div style='white-space: nowrap;left-padding:30px;'>";
    $img.="<img src='$imgdir/vote/$iconset/leftbar.gif' align='middle' />";
    $img.="<span style='white-space: nowrap'>";
    $img.="<img src='$imgdir/vote/$iconset/mainbar.gif' ".
        "height='14' width='$fval' align='middle' />";
    if ($full_width && $ival != 0) {
        $img.="<img src='$imgdir/vote/$iconset/b_mainbar.gif' ".
            " height='14' width='$ival' align='middle' /></span>";
        $img.="<img src='$imgdir/vote/$iconset/b_rightbar.gif' align='middle' />";
    } else {
        $img.="</span><img src='$imgdir/vote/$iconset/rightbar.gif' align='middle' />";
    }
    $state=((int)$val).'%';
    if (!$notext)
        $img.=' '.$state;
    $img.='</div></div>';
    return $img;
}

// vim:et:sts=4:sw=4:

?>
