<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a pageview plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id: pageview.php,v 1.2 2006/01/03 13:27:57 wkpark Exp $

function do_pageview($formatter,$options) {
    global $DBInfo;
    $sections=_get_sections($formatter->page->get_raw_body(),2);

    if ($options['p']) $sect=$options['p'];
    else $sect=1;

    $act=$options['action'];

    // get head title section
    list($secthead,$dumm)=explode("\n",$sections[0]);
    preg_match('/^\s*=\s*([^=].*[^=])\s*=\s?$/',$secthead,$match);
    $secthead=rtrim($sections[0]);
    if ($match[1]) $title=$match[1];
    $sz=sizeof($sections);

    // get prev,next subtitle
    if ($sz > ($sect+1)) {
        list($n_title,$dumm)=explode("\n",$sections[$sect+1]);
        preg_match('/^\s*==\s*(.*)\s*==\s?$/',$n_title,$match);
        if ($match[1])
            $n_title=$match[1];
        else
            $n_title='';
    } else {
        list($o_title,$dumm)=explode("\n",$sections[1]);
        preg_match('/^\s*==\s*(.*)\s*==\s?$/',$o_title,$match);
        if ($match[1])
            $o_title=$match[1];
        else
            $o_title='';
    }
    if ($sect-1 >= 1) {
        list($p_title,$dumm)=explode("\n",$sections[$sect-1]);
        preg_match('/^\s*==\s*(.*)\s*==\s?$/',$p_title,$match);
        if ($match[1])
            $p_title=$match[1];
        else
            $p_title='';
    }

    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $save=$formatter->preview;
    $formatter->preview=1;
    // show the head title
    if ($secthead) $formatter->send_page($secthead);
    // section number ?
    //$formatter->head_num='1.'.$sect;
    //$formatter->head_num=$sect; // XXX
    //$formatter->head_dep=0;
    //$formatter->toc=1;
    // show selected section
    $formatter->send_page($sections[$sect]);
    $formatter->preview=$save;

    // make link tags
    if ($o_title!='') {
        $olink= $formatter->link_tag($formatter->page->urlname,'?action='.$act.
            '&amp;p=1',$o_title);
        $first= _("First:").' '.$olink;
    }
    if ($n_title!='') {
        $nlink= $formatter->link_tag($formatter->page->urlname,'?action='.$act.
            '&amp;p='.($sect+1),$n_title);
        $next= _("Next:").' '.$nlink;
        $nlink= "<div class='pageNext'>".$nlink." &raquo;</div>\n";
    }
    if ($p_title!='') {
        $plink= $formatter->link_tag($formatter->page->urlname,'?action='.$act.
            '&amp;p='.($sect-1),$p_title);
        $plink= "<div class='pagePrev'>&laquo; ".$plink."</div>\n";
    }
    print "$first$next\n<div class='pageNav'>".$plink.$nlink."</div>\n";

    // render extra_macros
    if ($DBInfo->extra_macros) {
        if (!is_array($DBInfo->extra_macros)) {
            print '<div id="wikiExtra">'."\n";
            print $formatter->macro_repl($DBInfo->extra_macros);
            print '</div>'."\n";
        } else {
            print '<div id="wikiExtra">'."\n";
            foreach ($DBInfo->extra_macros as $macro)
                print $formatter->macro_repl($macro);
            print '</div>'."\n";
        }
    }

    $formatter->send_footer("",$options);
    return;
}

// vim:et:sts=4:
?>
