<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple textfile dictionary module for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-05-03
// Name: TextDictModule
// Description: A Simple Text-based Dictionary Module
// URL: MoniWiki:TextDictModule etc.
// Version: $Revision$
// License: GPL
//
// $Id$
//

function _fuzzy_bsearch_file($fp, $key, $seek, $fuzzyoffset=0, $klen=0,$fz=0,$encoding='UTF-8') {
    # adjustable parameters
    $_fuzzy_factor = 0.65; # mid parameter: in case of binary-search: 0.5
    $_chunk_size = 40; # average strlen parameter of lines.
    $_howmany = 20; # this is not exact the bsearch then limit the search counter.
    $_debug = 1; # show debug info or not

    if (empty($key)) return null;
    if ($fz == 0) return null;
    if ($klen == 0) $klen = mb_strlen($key,$encoding);

    $ki=0;
    $pkey=mb_substr($key,0,$klen,$encoding);

    $offset = $fuzzyoffset;
    $myseek = $seek;

    $upper = $fz;
    $lower = 0;

    $f_offset = $min_offset = abs($offset);

    $scount = 0;
    while($scount < $_howmany) {
        $scount++;

        # check boundary
        $myseek += $offset;
        $myseek = $myseek > $fz ? $fz:($myseek < 0 ? 0:$myseek);
        fseek($fp,$myseek);

        if ($rlen > 1024) { $rlen=1024;}

        $ll=fgets($fp,1024);
        $l=fgets($fp,1024);

        $mykey= strtok($l,' \t\n,:');
        $llen= mb_strlen($mykey,$encoding);

        $lz=strlen($l.$ll);

        $myseek+=$lz;

        if ($llen < $ki) {
            if ($match) {
                if ($_debug) print '**--<br />';
                $lower = $myseek - strlen($l);
                break;
            }
            continue;
        }

        $len = $llen >= $klen ? $klen:$llen;
        $pmykey=mb_substr($mykey,0,$len,$encoding);

        $test= strcasecmp($pkey,$pmykey);
        if ($test == 0) {
            $test = 1;
            // very similar pattern can use smaller factor
            $_fuzzy_factor=0.5*0.8/$klen;
            if ($klen <= $llen) $test = -1;
        }

        if ($test > 0) {
            //print "&gt;".$l;
            $sign = 1;
            $lower = $myseek - strlen($l);
        } else {
            //print "&lt;".$l;
            $sign = -1;
            $upper = $myseek;
        }

        $n_offset = intval(($upper - $lower) * $_fuzzy_factor);
        $f_offset = min($n_offset,$f_offset);

        if ($_debug > 50) print ' * '.($sign*$f_offset)."<br />\n";
        if ($f_offset > $min_offset * 1.2) break;
        $min_offset = min($min_offset, $f_offset);
        if ($f_offset < $_chunk_size) $f_offset = $_chunk_size;

        $offset = $sign * $f_offset;
    }
    if ($_debug) print "key=".$key.'/seek='.$lower.'/offset='.($upper - $lower).'<br />';
    fseek($fp,$lower);
    if ($_debug) print "<pre>==== chunk ====\n".fread($fp,$upper - $lower).'</pre>';
    return array($l,$lower,$upper,$scount);
}

function _file_match($fp,$key,$lower,$upper,$fsize,$klen=1,$match_prefix=true,$encoding='UTF-8') {
    static $cseek=0;
    $_debug=1;

    $count=0;

    if ($klen == 0)
        $klen = mb_strlen($key,$encoding);

    if ($klen == 1) $match_prefix=false;

    if (empty($key)) return '';
    #print $klen.':'.$lower.'/'.$upper.'<br />';
    //if ($lower > $upper) print 'bbbbbboooo';

    $ki=0;
    $ckey=mb_substr($key,$ki,1,$encoding);
    $pkey='';
    $pmykey=null;

    $buf='';
    $l='';
    $seek=$lower;
    fseek($fp,$seek);
    $match=0;
    $n=$nn=0;
    while(($seek < $upper or $match) and !feof($fp) and $ki <= $klen) {
        $n++;
        $last = $l;
        $l = fgets($fp,1024);
        $seek +=strlen($l);

        if ($l{0} == '#') continue;
        $mykey= strtok($l,' \t\n,:');
        $llen= mb_strlen($mykey,$encoding);
        if ($llen < $ki) {
            print '*pkey='.$pkey.'<br />';
            if ($match) break;
            continue;
        }
        if ($ki > 0) $pmykey=mb_substr($mykey,0,$ki,$encoding);
        $cmykey=mb_substr($mykey,$ki,1,$encoding);

        if ($ki == $klen and $pkey == $pmykey) {
            if (!$match_prefix and $llen > $klen) break;
            //print '+'.$ki.'<br />';
            $buf.=$l;
            $count++;
        } else if ($ckey == $cmykey and $pkey == $pmykey) {
            if ($ki < $klen) {
                $ki++;
                $pkey.=$ckey;
                //print 'pkey='.$pkey.'<br />';
                for ($ckey=mb_substr($key,$ki,1,$encoding);$ki<=$klen;$ckey=mb_substr($key,++$ki,1,$encoding)) {
                    if ($llen > $ki) {
                        $cmykey=mb_substr($mykey,$ki,1,$encoding);
                        if ($ckey == $cmykey) {
                            $pkey.=$ckey;
                            //print '++pkey='.$pkey.'<br />';
                            continue;
                        }
                    }
                    break;
                }
                //print 'pkey='.$pkey.'<br />';
                if ($ki == $klen) {
                    $match=1;
                    $buf.=$l;
                    $count++;
                }
                continue;
            }
            if ($ki == $klen) $match = true;
            print '+pkey='.$pkey.'<br />';
            if ($ki == $klen) $buf.=$l;
        } else if ($pkey != $pmykey) {
            break;
        } else if ($ckey > $cmykey) {
            $nn++;
            continue;
        } else {
            break;
        }
    }
    $cseek+=$n;
    if ($n>100) $n='<span style="color:red">'.$n.'</span>';
    if ($_debug) print 'fgets='.$key.'/'.$nn.'/'.$n.'/'.$cseek."<br />";
    if ($count == 0 and !empty($pkey))
        return array ($count, $pkey, $last);
    return array ($count, $buf, null);
}

// vim:et:sts=4:sw=4:
?>
