<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple progress bar plugin for the MoniWiki
//
// Usage: [[Bar(10%)]] [[Bar(0.5)]]
//
// $Id$

function macro_Bar($formatter,$value) {
    global $DBInfo;

    $imgdir=$DBInfo->imgs_dir;
    $iconset='red';
    $full_width=0;
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
        if ($dum > 1.0) $val=100;
        else $val=$dum*100.0;
    }

    $ival=0;
    if ($val < 100.0) $ival=100.0 - $val;
    $img="<div style='white-space: nowrap'><img src='$imgdir/vote/$iconset/b_leftbar.gif' align='middle' />";
    $img.="<img src='$imgdir/vote/$iconset/b_mainbar.gif' ".
        "height='14' width='$val%' align='middle' />";
    if ($full_width && $ival != 0) {
        $img.="<img src='$imgdir/vote/$iconset/mainbar.gif' ".
            " height='14' width='$ival%' align='middle' />";
        $img.="<img src='$imgdir/vote/$iconset/rightbar.gif' align='middle' />";
    } else {
        $img.="<img src='$imgdir/vote/$iconset/b_rightbar.gif' align='middle' />";
    }
    $state=((int)$val).'%';
    if (!$notext)
        $img.=' '.$state.'</div>';

    return $img;
}

// vim:et:sts=4:sw=4:

?>
