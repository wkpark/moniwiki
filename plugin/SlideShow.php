<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a SlideShow plugin for the MoniWiki
//
// Usage: [[SlideShow(pagename)]]
//
// $Id$

function macro_SlideShow($formatter,$value='',$options=array()) {
    global $DBInfo;

    if ($value) {
        if (!$DBInfo->hasPage($value))
            return '[[SlideShow('._("No page found").')]]';
        $pg=$DBInfo->getPage($value);
        $sections=_get_sections($pg->get_raw_body(),2);
        $urlname=_urlencode($value);
    } else {
        $sections=_get_sections($formatter->page->get_raw_body(),2);
        $urlname=$formatter->page->urlname;
    }

    if ($options['p']) $sect=$options['p'];
    else $sect=1;

    $act=$options['action'] ? $options['action']:'SlideShow';

    $iconset='bluecurve';
    $icon_dir=$DBInfo->imgs_dir.'/plugin/SlideShow/'.$iconset.'/';

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

        list($e_title,$dumm)=explode("\n",$sections[$sz-1]);
        preg_match('/^\s*==\s*(.*)\s*==\s?$/',$e_title,$match);
        if ($match[1])
            $e_title=$match[1];
        else
            $e_title='';
    }
    if (!$options['action'] or $sect > 1){
        list($s_title,$dumm)=explode("\n",$sections[1]);
        preg_match('/^\s*==\s*(.*)\s*==\s?$/',$s_title,$match);
        if ($match[1])
            $s_title=$match[1];
        else
            $s_title='';
    }
    if ($sect-1 >= 1) {
        list($p_title,$dumm)=explode("\n",$sections[$sect-1]);
        preg_match('/^\s*==\s*(.*)\s*==\s?$/',$p_title,$match);
        if ($match[1])
            $p_title=$match[1];
        else
            $p_title='';
    }
    // make link icons
    if ($s_title!='' or !$options['action']) {
        $slink= $formatter->link_url($urlname,'?action='.$act.
            '&amp;p=1');
        $icon=$options['action'] ? 'start':'next';
        $start= '<a href="'.$slink.'" title="'._("Start:").' '.$s_title.'">'.
            '<img src="'.$icon_dir.$icon.'.png'.'" border="0" alt="<|" /></a>';
    }
    if ($e_title!='' and $options['action']) {
        $elink= $formatter->link_url($urlname,'?action='.$act.
            '&amp;p='.($sz-1));
        $end= '<a href="'.$elink.'" title="'._("End:").' '.$e_title.'">'.
            '<img src="'.$icon_dir.'end.png'.'" border="0" alt="|>" /></a>';
    }
    if ($n_title!='' and $options['action']) {
        $nlink= $formatter->link_url($urlname,'?action='.$act.
            '&amp;p='.($sect+1));
        $next= '<a href="'.$nlink.'" title="'._("Next:").' '.$n_title.'">'.
            '<img src="'.$icon_dir.'next.png'.'" border="0" alt=">" /></a>';
    }
    if ($p_title!='') {
        $plink= $formatter->link_url($urlname,'?action='.$act.
            '&amp;p='.($sect-1));
        $prev= '<a href="'.$plink.'" title="'._("Prev:").' '.$p_title.'">'.
            '<img src="'.$icon_dir.'prev.png'.'" border="0" alt="<" /></a>';
    }
    if ($options['action']) {
        $return= $formatter->link_tag($urlname,'?action=show',_("Return"));
        return array($sections,"$start$prev$next$end$return\n");
    }
    return "$start$prev$next$end\n";

}

function do_slideshow($formatter,$options=array()) {
    global $DBInfo;

    $options['css_url']=$DBInfo->url_prefix."/css/slide.css";
    $formatter->send_header("",$options);
    print "<div id='wikiContent'>";

    list($sections,$btn)=macro_SlideShow($formatter,$formatter->page->name,
        $options);
    print '<div class="slideNav">'.$btn.'</div>';

    if ($options['p']) $sect=$options['p'];
    else $sect=1;


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
    print "</div></div>";

    print '<div class="slideNav">'.$btn.'</div>';

    // render extra macros
    $macros=array('FootNote');
    if ($macros) {
        if (!is_array($macros)) {
            print '<div id="wikiExtra">'."\n";
            print $formatter->macro_repl($macros);
            print '</div>'."\n";
        } else {
            print '<div id="wikiExtra">'."\n";
            foreach ($macros as $macro)
                print $formatter->macro_repl($macro);
            print '</div>'."\n";
        }
    }

    print "\n</body>\n</html>\n";
    return;
}

// vim:et:sts=4:
?>
