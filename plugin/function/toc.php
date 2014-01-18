<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// function plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-017
// Name: TableOfContent function
// Description: TableOfContent function
// URL: MoniWiki:FunctionPlugin
// Version: $Revision: 1.3 $
// License: GPL
//
// Usage: $toc = function_toc($formatter);
//
// $Id: toc.php,v 1.3 2010/08/28 13:05:17 wkpark Exp $

function function_toc($formatter) {
    $secdep = '';
    $simple = 1;

    $head_num=1;
    $head_dep=0;

    $body=$formatter->page->get_raw_body();
    $body=preg_replace("/\{\{\{.+?\}\}\}/s",'',$body);
    $lines=explode("\n",$body);

    $toc = array();
    foreach ($lines as $line) {
        preg_match("/(?<!=)(={1,$secdep})\s(#?)(.*)\s+\\1\s?$/",$line,$match);
        if (!$match) continue;

        $dep=strlen($match[1]);
        if ($dep > 4) $dep = 5;
        $head=str_replace("<","&lt;",$match[3]);
        # strip some basic wikitags
        # $formatter->baserepl,$head);
        #$head=preg_replace($formatter->baserule,"\\1",$head);
        # do not strip basic wikitags
        $head=preg_replace($formatter->baserule,$formatter->baserepl,$head);
        $head=preg_replace("/\[\[.*\]\]/","",$head);
        $head=preg_replace_callback("/(".$formatter->wordrule.")/",
            array(&$formatter, 'link_repl'), $head);

        if ($simple)
            $head=strip_tags($head);
            #$head=strip_tags($head,'<b><i><sub><sup><del><tt><u><strong>');

        if (!$depth_top) { $depth_top=$dep; $depth=1; }
        else {
            $depth=$dep - $depth_top + 1;
            if ($depth <= 0) $depth=1;
        }

        $num="".$head_num;
        $odepth=$head_dep;
        $open="";
        $close="";

        if ($match[2]) {
            # reset TOC numberings
            $dum=explode(".",$num);
            $i=sizeof($dum);
            for ($j=0;$j<$i;$j++) $dum[$j]=1;
            $dum[$i-1]=0;
            $num=join($dum,'.');
            if ($prefix) $prefix++;
            else $prefix=1;
        }

        if ($odepth && ($depth > $odepth)) {
            $num.='.1';
        } else if ($odepth) {
            $dum=explode('.',$num);
            $i=sizeof($dum)-1;
            while ($depth < $odepth && $i > 0) {
                unset($dum[$i]);
                $i--;
                $odepth--;
            }
            $dum[$i]++;
            $num=join($dum,'.');
        }
        $head_dep=$depth; # save old
        $head_num=$num;

        $toc["$num"]=$head;
    }

    return $toc;
}

// vim:et:sts=4:sw=4:
?>
