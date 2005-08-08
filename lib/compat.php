<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// $Id$
//
function utf8_mb_encode($str) {
    # mb_encode_numericentity() like function
    # to make UTF-8 encoded str to numeric entities 
    $len=strlen($str);

    $out='';
    for ($i=0;$i<$len;$i++) {
        if ((ord($str[$i]) & 0xF0) == 0xE0) { # Now only 3-byte UTF-8 supported
            $uni1=((ord($str[$i]) & 0x0f) <<4) | (($str[$i+1] & 0x7f) >>2);
            $uni2=((ord($str[$i+1]) & 0x7f) <<6) | (ord($str[$i+2]) & 0x7f);
            $uni='&#x'.(dechex(($uni1<<8)+$uni2)).';';
            $out.=$uni;
            $i+=2;
        } else
            $out.=$str[$i];
    }

    return $out;
}

// vim:sts=4:et:sw=4
?>
