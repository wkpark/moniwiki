<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a InterWiki plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-04-12
// Name: InterWiki plugin
// Description: show InterWikis
// URL: MoniWiki:InterWikiPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[InterWiki]]
//

function macro_InterWiki($formatter,$value,$options=array()) {
    global $DBInfo;

    while (!isset($DBInfo->interwiki) or !empty($options['init'])) {
        $cf = new Cache_text('settings', array('depth'=>0));

        // check intermap and shared_intermap
        // you can update interwiki maps by touch $intermap or edit $shared_intermap
        if (empty($formatter->refresh) and ($info = $cf->fetch('interwiki')) !== false) {
            $info = $cf->fetch('interwiki');
            $DBInfo->interwiki=$info['interwiki'];
            $DBInfo->interwikirule=$info['interwikirule'];
            $DBInfo->intericon=$info['intericon'];
            break;
        }

        $deps = array();
        $interwiki=array();
        # intitialize interwiki map
        $map = array();
        if (isset($DBInfo->intermap[0]) && file_exists($DBInfo->intermap)) {
            $map = file($DBInfo->intermap);
            $deps[] = $DBInfo->intermap;
        }
        if (!empty($DBInfo->sistermap) and file_exists($DBInfo->sistermap))
            $map=array_merge($map,file($DBInfo->sistermap));

        # read shared intermap
        if (file_exists($DBInfo->shared_intermap)) {
            $map=array_merge($map,file($DBInfo->shared_intermap));
            $deps[] = $DBInfo->shared_intermap;
        }

        $interwikirule = '';
        for ($i=0,$sz=sizeof($map);$i<$sz;$i++) {
            $line=rtrim($map[$i]);
            if (!$line || $line[0]=="#" || $line[0]==" ") continue;
            if (preg_match("/^[A-Z]+/",$line)) {
                $wiki=strtok($line,' ');$url=strtok(' ');
                $dumm=trim(strtok(''));
                if (preg_match('/^(http|ftp|attachment):/',$dumm,$match)) {
                    $icon=strtok($dumm,' ');
                    if ($icon[0]=='a') {
                        $url=$formatter->macro_repl('Attachment',substr($icon,11),1);
                        $icon=qualifiedUrl($DBInfo->url_prefix.'/'.$url);
                    }
                    preg_match('/^(\d+)(x(\d+))?\b/',strtok(''),$msz);
                    $sx=$msz[1];$sy=$msz[3];
                    $sx=$sx ? $sx:16; $sy=$sy ? $sy:16;
                    $intericon[$wiki]=array($sx,$sy,trim($icon));
                }
                $interwiki[$wiki]=trim($url);
                $interwikirule.="$wiki|";
            }
        }
        $interwikirule.="Self";
        $interwiki['Self']=get_scriptname().$DBInfo->query_prefix;

        # set default TwinPages interwiki
        if (empty($interwiki['TwinPages']))
            $interwiki['TwinPages']=(($DBInfo->query_prefix == '?') ? '&amp;':'?').
                'action=twinpages&amp;value=';

        # read shared intericons
        $map=array();
        if (!empty($DBInfo->shared_intericon) and file_exists($DBInfo->shared_intericon))
            $map=array_merge($map,file($DBInfo->shared_intericon));

        $intericon = array();
        for ($i=0,$isz=sizeof($map);$i<$isz;$i++) {
            $line=rtrim($map[$i]);
            if (!$line || $line[0]=="#" || $line[0]==" ") continue;
            if (preg_match("/^[A-Z]+/",$line)) {
                $wiki=strtok($line,' ');$icon=trim(strtok(' '));
                if (!preg_match('/^(http|ftp|attachment):/',$icon,$match)) continue;
                preg_match('/^(\d+)(x(\d+))?\b/',strtok(''),$sz);
                $sx=$sz[1];$sy=$sz[3];
                $sx=$sx ? $sx:16; $sy=$sy ? $sy:16;
                if ($icon[0]=='a') {
                    $url=$formatter->macro_repl('Attachment',substr($icon,11),1);
                    $icon=qualifiedUrl($DBInfo->url_prefix.'/'.$url);
                }
                $intericon[$wiki]=array($sx,$sy,trim($icon));
            }
        }
        $DBInfo->interwiki=$interwiki;
        $DBInfo->interwikirule=$interwikirule;
        $DBInfo->intericon=$intericon;
        $interinfo=
            array('interwiki'=>$interwiki,'interwikirule'=>$interwikirule,'intericon'=>$intericon);
        $cf->update('interwiki', $interinfo, 0, array('deps'=>$deps));
        break;
    }
    if (!empty($options['init'])) return;

    $out="<table border='0' cellspacing='2' cellpadding='0'>";
    foreach (array_keys($DBInfo->interwiki) as $wiki) {
        $href=$DBInfo->interwiki[$wiki];
        if (strpos($href,'$PAGE') === false)
            $url=$href.'RecentChanges';
        else {
            $url=str_replace('$PAGE','index',$href);
            #$href=$url;
        }
        $icon=$DBInfo->imgs_url_interwiki.strtolower($wiki).'-16.png';
        $sx=16;$sy=16;
        if (!empty($DBInfo->intericon[$wiki])) {
            $icon=$DBInfo->intericon[$wiki][2];
            $sx=$DBInfo->intericon[$wiki][0];
            $sy=$DBInfo->intericon[$wiki][1];
        }
        $out.="<tr><td><tt><img src='$icon' width='$sx' height='$sy' ".
            "class='interwiki' alt='$wiki:' /><a href='$url'>$wiki</a></tt></td>";
        $out.="<td><tt><a href='$href'>$href</a></tt></td></tr>\n";
    }
    $out.="</table>\n";
    return $out;
}

// vim:et:sts=4:sw=4:
