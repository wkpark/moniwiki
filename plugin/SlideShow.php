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

    $depth=2; // default depth
    if ($options['d']) $depth=intval($options['d']);
    $args=explode(',',$value);
    $sz=sizeof($args);
    for($i=0,$sz=sizeof($args);$i<$sz;$i++) {
        if (($p=strpos($args[$i],'='))!==false) {
            $k=substr($args[$i],0,$p);
            $v=substr($args[$i],$p+1);
            if ($k=='depth' or $k=='dep') $depth=intval($v);
        } else {
            $pgname=$args[$i];
        }
    }
    if ($pgname) {
        if (!$DBInfo->hasPage($pgname))
            return '[[SlideShow('._("No page found").')]]';
        $pg=$DBInfo->getPage($pgname);
        $sections=_get_sections($pg->get_raw_body(),$depth);
        $urlname=_urlencode($pgname);
    } else {
        $sections=_get_sections($formatter->page->get_raw_body(),$depth);
        $urlname=$formatter->page->urlname;
    }

    if ($options['p']) {
        list($sect,$dum)=explode('/',$options['p']);
        $sect=abs(intval($sect));
        $sect= $sect ? $sect:1;
    } else $sect=1;

    $act=$options['action'] ? $options['action']:'SlideShow';

    $iconset='bluecurve';
    $icon_dir=$formatter->imgs_dir.'/plugin/SlideShow/'.$iconset.'/';

    // get head title section
    if ($depth==2) {
        list($secthead,$dumm)=explode("\n",$sections[0]);
        preg_match('/^\s*=\s*([^=].*[^=])\s*=\s?$/',$secthead,$match);
        $secthead=rtrim($sections[0]);
        if ($match[1]) $title=$match[1];
    } else {
        $dep='&amp;d='.$depth;
    }
    $sz=sizeof($sections); // $sections[0]
    $sz--;
    //if (trim($sections[$sz-1])=='') $sz--;
    //print $sections[0];
    //print_r($sections);

    if ($sect > $sz) $sect=$sz;

    // get prev,next subtitle
    if ($sz > ($sect)) {
        list($n_title,$dumm)=explode("\n",$sections[$sect+1]);
        preg_match("/^\s*={".$depth.'}\s*(.*)\s*={'.$depth.'}\s?$/',$n_title,$match);
        if ($match[1])
            $n_title=$match[1];
        else
            $n_title='';

        list($e_title,$dumm)=explode("\n",$sections[$sz]);
        preg_match("/^[ ]*={".$depth."}\s+(.*)\s+={".$depth."}\s?/",$e_title,$match);
        if ($match[1])
            $e_title=$match[1];
        else
            $e_title='';
    }
    if (!$options['action'] or $sect > 1){
        list($s_title,$dumm)=explode("\n",$sections[1]);
        preg_match("/^\s*={".$depth."}\s*(.*)\s*={".$depth."}\s?$/",$s_title,$match);
        if ($match[1])
            $s_title=$match[1];
        else
            $s_title='';
    }
    if ($sect >= 1) {
        list($p_title,$dumm)=explode("\n",$sections[$sect-1]);
        preg_match('/^\s*={'.$depth.'}\s*(.*)\s*={'.$depth.'}\s?$/',$p_title,$match);
        if ($match[1])
            $p_title=$match[1];
        else
            $p_title='';
    }
    // make link icons
    if ($s_title!='' or !$options['action']) {
        $slink= $formatter->link_url($urlname,'?action='.$act.
            $dep.'&amp;p=1');
        $icon=$options['action'] ? 'start':'next';
        $start= '<a href="'.$slink.'" title="'._("Start:").' '.$s_title.'">'.
            '<img src="'.$icon_dir.$icon.'.png'.'" style="border:0" alt="&lt;|" /></a>';
    } else {
        $start= 
            '<img src="'.$icon_dir.'start_off.png'.'" style="border:0" alt="&lt;|" /></a>';
    }
    if ($e_title!='' and $options['action']) {
        $elink= $formatter->link_url($urlname,'?action='.$act.
            $dep.'&amp;p='.$sz);
        $end= '<a href="'.$elink.'" title="'._("End:").' '.$e_title.'">'.
            '<img src="'.$icon_dir.'end.png'.'" style="border:0" alt="|>" /></a>';
    } else {
        $end= 
            '<img src="'.$icon_dir.'end_off.png'.'" style="border:0" alt="|>" /></a>';
    }
    if ($n_title!='' and $options['action']) {
        $np=$sect+1;
        $nlink= $formatter->link_url($urlname,'?action='.$act.
            $dep.'&amp;p='.($sect+1));
        $next= '<a href="'.$nlink.'" title="'._("Next:").' '.$n_title.'">'.
            '<img src="'.$icon_dir.'next.png'.'" style="border:0" alt=">" /></a>';
    } else {
        $next= 
            '<img src="'.$icon_dir.'next_off.png'.'" style="border:0" alt=">" /></a>';
    }
    if ($p_title!='') {
        $pp=$sect-1;
        $plink= $formatter->link_url($urlname,'?action='.$act.
            $dep.'&amp;p='.($sect-1));
        $prev= '<a href="'.$plink.'" title="'._("Prev:").' '.$p_title.'">'.
            '<img src="'.$icon_dir.'prev.png'.'" style="border:0" alt="<" /></a>';
    } else {
        $prev= 
            '<img src="'.$icon_dir.'prev_off.png'.'" style="border:0" alt="<" /></a>';
    }
    $rlink= $formatter->link_url($urlname,'?action=show');
    $return= '<a href="'.$rlink.'" title="'._("Return").' '.$pgname.'">'.
        '<img src="'.$icon_dir.'up.png'.'" style="border:0" alt="^" /></a>';
    if ($options['action']) {
        $form0='<form method="post" onsubmit="return false" action="'.$rlink.'">';
        $form0.='<input type="hidden" name="d" value="'.$depth.'" />';
        $form0.='<input type="hidden" name="action" value="slideshow" />';
        $form='<span class="slideShow" style="vertical-align:bottom;">'.
            '<input style="text-align:center" type="text" name="p" value="'.
            $sect.'/'.$sz.'" onkeypress="slideshowhandler(event,this,'.
            "'$rlink','$pp','$np')".'" /></span>';
        $form1="</form>\n";
        return array($sections,"$form0$return$start$prev$form$next$end$form1\n");
    }
    return "$return$start";
}

function do_slideshow($formatter,$options=array()) {
    global $DBInfo;

    $js="<script type='text/javascript' "."
        src='$DBInfo->url_prefix/local/slideshow.js' ></script>";
    $options['css_url']=$DBInfo->url_prefix."/css/slide.css";
    $formatter->send_header("",$options);
    print "<div id='wikiContent'>";

    list($sections,$btn)=macro_SlideShow($formatter,$formatter->page->name,
        $options);
    print $js;
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
