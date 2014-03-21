<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Tour plugin for the MoniWiki
//
// Usage: [[Tour]]
//
// $Id: Tour.php,v 1.11 2010/08/23 09:15:23 wkpark Exp $

if (!function_exists('do_dot'))
    if ($pn=getPlugin('dot')) include_once("plugin/$pn.php");

function do_tour($formatter,$options) {
    #header("Content-Type: text/plain");
    $formatter->send_header('',$options);
    $formatter->send_title('','', $options);

    if ($options['value']) $value=$options['value'];
    else $value=$options['page'];
    print macro_Tour($formatter,$value,$options);
    //$args['editable']=1;
    $args = false;
    $formatter->send_footer($args,$options);
}

define('TOUR_LEAFCOUNT',4);
define('TOUR_DEPTH',3);

function macro_Tour($formatter,$value,$options=array()) {
    global $DBInfo;

    $args=explode(',',$value);

    $value='';
    $arena='';
    foreach ($args as $arg) {
        $arg=trim($arg);
        if (($p=strpos($arg,'='))!==false) {
            $k=strtok($arg,'=');
            $v=strtok('');
            if ($k=='arena') $arena=$v;
        } else {
            $value=$arg;
        }
    }
    $query='';
    if (!empty($options['arena']) or $arena) {
        $options['arena']=!empty($arena) ? $arena:$options['arena'];
        $query='&amp;arena='.$options['arena'];
        $arena=$options['arena'];
    }

    $head='';
    if (!$value) $value=$formatter->page->name;
    #else if ($value != $formatter->page->name) XXX;

    $query2 = '';
    if ($arena == 'backlinks') {
        $head2=_("BackLinks");
        $link=$formatter->link_tag(_html_escape($value));
    } else if ($arena == 'keylinks' or $arena == 'keywords') {
        $query2='?action=fullsearch&amp;keywords=1';
        $head2=_("Keywords");
        if ($DBInfo->hasPage($value)) {
            $link=$value;
        } else {
            $link=$formatter->link_to('?action=fullsearch&amp;value='.$value,_html_escape($value));
        }
    }

    if ($head2)
        $head=sprintf(_(" from %s"),$link);
    
    if ($head)
        $head=sprintf(_("%s Tour %s"),$head2,$head);
    $head='<h2>'.$head.'</h2>';

    if (!empty($options['w']) and $options['w'] < 10) $count=$options['w'];
    else $count=TOUR_LEAFCOUNT;
    if (!empty($options['d']) and $options['d'] < 7) $depth=$options['d'];
    else $depth=TOUR_DEPTH;

    $color=array();

    $tree=new LinkTree($options['arena']);

    $tree->makeTree($value,$node,$color,$depth,$count);

    if (!$node) $node=array($value=>array());

    $allnode=array_keys($node);
    asort($allnode);

    $id=0;
    $outs=array();
    while (list($leafname,$leaf) = @each ($node)) {
        if (empty($leafs[$leafname])) {
            $urlname=_rawurlencode($leafname);
            $leafs[$leafname]=1;
            $url[$leafname]=$urlname;
        }
        $selected=array_intersect($node[$leafname],$allnode);
        asort($selected);
        foreach ($selected as $leaf) {
            if (empty($leafs[$leaf])) {
                $urlname=_rawurlencode($leaf);
                $url[$leaf]=$urlname;
                $id=$leafs[$leaf]=$leafs[$leafname]+1;
                if (!empty($outs[$id])) $outs[$id]=array();
                $outs[$id][]= $leaf;
            }
        }
    }
    if (isset($out[0]))
        unset($out[0]);
    if ($DBInfo->hasPage($url[$value])) {
        $pg=$url[$value];
        $extra='';
    } else {
        $pg=$formatter->page->name;
        $extra='&amp;value='.$url[$value];
    }
    $wide= $formatter->link_tag($pg,
       "?action=tour$query$extra&amp;w=".($count+1)."&amp;d=$depth",_("links"));
    $deep= $formatter->link_tag($pg,
       "?action=tour$query$extra&amp;w=$count&amp;d=".($depth+1),_("deeper"));
    $link='<h3>'.sprintf(_("More %s or more %s"),$wide,$deep).'</h3>';

    $pages = '';
    foreach ($allnode as $node) {
        if ($DBInfo->hasPage($url[$node])) {
            $pg=$url[$node];
            $extra='';
        } else {
            $pg=$formatter->page->name;
            $extra='&amp;value='.$url[$node];
        }
        $pages.='<li>'.$formatter->link_tag($pg,$query2.$extra,
            _html_escape($node))."</li>\n";
    }
    if ($arena == 'keywords' or $arena == 'keylinks')
        $title=
        '<h3>'.sprintf(_("Total %d related keywords"),sizeof($allnode)).'</h3>';
    else
        $title=
        '<h3>'.sprintf(_("Total %d related pages"),sizeof($allnode)).'</h3>';

    $out=array();
    $dep=1;
    foreach ($outs as $ls) {
        asort($ls);
        $temp='';
        foreach ($ls as $leaf) {
            if ($DBInfo->hasPage($url[$leaf])) {
                $pg=$url[$leaf];
                $extra='';
            } else {
                $pg=$formatter->page->name;
                $extra='&amp;value='.$url[$leaf];
            }
            $temp.= ' <li>'.$formatter->link_tag($pg,
                "?action=tour$query$extra",$leaf)."</li>\n";
        }
        $out[]="<ul class='depth-$dep'>".$temp.'</ul>';
        $dep++;
    }
    $ret=implode("\n",$out);
    $ret=$head.'<div class="tourLeft">'.$link.$ret.'</div>'.
        "<div class=\"tourRight\">$title<ol>$pages</ol></div>\n".
        "<div class=\"tourFoot\"></div>\n";

    return '<div class="wikiTour">'.$ret."</div>\n";
}

// vim:et:sts=4:
?>
