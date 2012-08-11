<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a fiximgpath postfilter plugin for the MoniWiki
//
// $Id: fiximgpath.php,v 1.1 2006/12/15 14:42:58 wkpark Exp $

function postfilter_fiximgpath($formatter,$value,$options=array()) {
    global $DBInfo;

    $prefix=qualifiedUrl('');
    $chunks=preg_split('/(<[^>]+>)/',$value,-1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i=0,$sz=count($chunks); $i<$sz; $i++) {
        if (preg_match('/^<img /',$chunks[$i])) {
            $dumm=preg_replace('/<(img .*)src=(\'|\")\/([^\\2]+)\\2/i',"<$1"."src=$2".$prefix."$3$2",$chunks[$i]);
            $chunks[$i]=$dumm;
        }
    }

    return implode('',$chunks);
}

// vim:et:sts=4:
?>
