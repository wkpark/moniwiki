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
    $_fuzzy_factor2 = 0.55; # similiar pos parameter
    $_chunk_size = 128; # average strlen parameter of lines.
    $_howmany = 23; # this is not exact the bsearch then limit the search counter.
    $_debug = 0; # show debug info or not

    if (empty($key)) return null;
    if ($fz == 0) return null;
    if ($klen == 0)
        #$klen = mb_strlen($key,$encoding);
        $klen= strlen(utf8_decode($key));

    $ki=0;
    $pkey=mb_substr($key,0,$klen,$encoding);

    $offset = $fuzzyoffset;
    $myseek = $seek;

    $upper = $fz;
    $lower = 0;
    $fuzzy = $_fuzzy_factor;

    $f_offset = $min_offset = abs($offset);

    $scount = 0;
    while($scount < $_howmany) {
        $scount++;

        # check boundary
        $myseek += $offset;
        $myseek = $myseek > $fz ? $fz:($myseek < 0 ? 0:$myseek);
        fseek($fp,$myseek);

        $ll=fgets($fp,1024);
        $myseek0=ftell($fp);
        $l=fgets($fp,1024);
        if ($l=='') break;
        $mykey= strtok($l,' \t\n,:');
        $llen= strlen(utf8_decode($mykey));
        #$llen= mb_strlen($mykey,$encoding);

        $myseek=ftell($fp);

        if ($llen < $ki) {
            if ($match) {
                if ($_debug) print "**--<br />\n";
                $lower = $myseek0;
                break;
            }
            continue;
        }

        $len = $llen >= $klen ? $klen:$llen;
        $pmykey=mb_substr($mykey,0,$len,$encoding);

        $test= strcmp($pkey,$pmykey);
        if ($test == 0) {
            $test = 1;
            // very similar pattern can use smaller factor
            $fuzzy=$_fuzzy_factor*$_fuzzy_factor2/$klen;
            if ($klen <= $llen) $test = -1;
        }

        if ($test > 0) {
            $sign = 1;
            $lower = $myseek0;
        } else {
            $sign = -1;
            $upper = $myseek;
        }

        $f_offset = intval(($upper - $lower) * $fuzzy);
        #$f_offset = min($n_offset,$f_offset);

        if ($_debug > 50) print ' * '.($sign*$f_offset)."<br />\n";
        $min_offset = min($min_offset, $f_offset);
        if ($f_offset < $_chunk_size) $f_offset = $_chunk_size;

        $offset = $sign * $f_offset;
        if (($upper - $lower) < $f_offset) break;
    }
    if ($_debug > 50) {
        print "key=".$key.'/seek='.$lower.'/offset='.($upper - $lower)."<br />\n";
        fseek($fp,$lower);
        print "<pre>\n==== chunk ====\n".fread($fp,$upper - $lower)."</pre>\n";
    }
    return array($l,$lower,$upper,$scount);
}

function _file_match($fp,$key,$lower,$upper,$fsize,$klen=1,$match_prefix=true,$encoding='UTF-8') {
    static $cseek=0;
    $_debug=0;

    $count=0;

    if ($klen == 0)
        #$klen = mb_strlen($key,$encoding);
        $klen = strlen(utf8_decode($key));

    if ($klen == 1) $match_prefix=false;

    if (empty($key)) return '';
    #print $klen.':'.$lower.'/'.$upper."<br />\n";
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
        #print '==>'.$l;
        #$llen = mb_strlen($mykey,$encoding);
        $llen = strlen(utf8_decode($mykey));
        if ($llen < $ki) {
            if ($_debug) print '*pkey='.$pkey."<br />\n";
            break;
        }
        if ($ki > 0) $pmykey=mb_substr($mykey,0,$ki,$encoding);
        $cmykey=mb_substr($mykey,$ki,1,$encoding);

        if ($ki == $klen and $pkey == $pmykey) {
            if (!$match_prefix and $llen > $klen) break;
            #print '+'.$ki."<br />\n";
            $buf.=$l;
            $count++;
        } else if ($ckey == $cmykey and $pkey == $pmykey) {
            if ($ki < $klen) {
                $plast = $l;
                $ki++;
                $pkey.=$ckey;
                //print 'pkey='.$pkey."<br />\n";
                for ($ckey=mb_substr($key,$ki,1,$encoding);$ki<=$klen;$ckey=mb_substr($key,++$ki,1,$encoding)) {
                    if ($llen > $ki) {
                        $cmykey=mb_substr($mykey,$ki,1,$encoding);
                        if ($ckey == $cmykey) {
                            $pkey.=$ckey;
                            $plast = $l;
                            //print '++pkey='.$pkey."<br />\n";
                            continue;
                        }
                    }
                    break;
                }
                //print 'pkey='.$pkey."<br />\n";
                if ($ki == $klen) {
                    $match=1;
                    $buf.=$l;
                    if ($klen == $llen) $count++;
                }
                continue;
            }
            if ($ki == $klen) $match = true;
            #print '+pkey='.$pkey."<br />\n";
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
    if ($_debug) {
        if ($n>100) $n='<span style="color:red">'.$n.'</span>';
        print 'fgets='.$key.'/'.$nn.'/'.$n.'/'.$cseek."<br />\n";
    }
    if ($count) return array ($count, $buf, null);
    if (!empty($pkey)) {
        # not found but try to return similar patterns
        $seek -= strlen($l) + 1; // +1 mean do not read last "\n"
        fseek($fp,$seek);

        # backward seek dictionary
        $ll = '';
        $guess = array();
        $maxcheck = 0;
        while ( $seek > 0 ) {
            $rlen = $fsize - $seek;
            if ($rlen > 1024) { $rlen = 1024;}
            else if ($rlen <= 0) break;
            $seek -= $rlen;
            fseek($fp,$seek);

            $l = fread($fp, $rlen);
            if ($rlen != 1024) $l = "\n" . $l; // hack, for the first dict entry.
            while( ($p = strrpos($l,"\n") ) !== false) {
                $maxcheck++;
                $line = substr($l, $p+1) . $ll;
                #print '*'.$line."\n";
                $ll = '';
                $l = substr($l, 0, $p);
                $mykey= strtok($line,' \t\n,:');
                $cmykey = mb_substr($mykey, 0, $ki, $encoding); // XXX
                if ($cmykey != $pkey) break 2;
                $guess[] = $line;
                $ll = '';
                if ($maxcheck > 10) break 2;
            }
            $ll = $l.$ll;
        }
        $guess = array_reverse($guess);
        $buf = implode("\n",$guess);

        $lastmatch= strtok($last,' \t\n,:');
        $len = strlen($pkey);
        // XXX matching ratio + fuzzy factor etc.
        $match_ratio = $len / strlen($lastmatch);
        if ($_debug) {
            print $klen.' - '.$llen;
            $suffix = substr($lastmatch,$len);
            print 'matching ratio: '.$match_ratio.'/ suffix= "'.$suffix.'" '.$last."<br />\n";
            print 'pkey='.$pkey.','.$plast."<br />\n";
        }
        return array (0, $buf, $last);
    }
    return array (0, null, null);
}

// vim:et:sts=4:sw=4:
?>
