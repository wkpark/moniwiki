<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// dot action plugin for the MoniWiki
//
// $Id$

define(DEPTH,3);
define(LEAFCOUNT,2);
define(FONTSIZE,8);
define(FONTNAME,'WEBDOTFONT');

class LinkTree {
  var $cache=null;
  function LinkTree($arena='pagelinks') {
    if (!in_array($arena,array('pagelinks','backlinks','keywords','keylinks')))
      $arena = 'pagelinks';
    $this->cache=new Cache_text($arena);
  }

  function getLeafs($pagename,&$node,&$color,$depth,$count=LEAFCOUNT) {
    $links=unserialize($this->cache->fetch($pagename));
    #print_r($links);
    if (!is_array($links)) $links=array();
    foreach ($links as $page) {
      if (!$color[$page]) $color[$page]=$depth;
      if ($page) {
        if (!$node[$page]) {
          $leafs=$this->cache->fetch($page);
          if ($leafs) {
            $leafs=unserialize($leafs);
            # XXX 
            $nodelink[$page]=sizeof($leafs);
          } else
            $nodelink[$page]=-1; // XXX
        } else $nodelink[$page]=1;
      }
    }
    if (sizeof($nodelink) > $count) {
      arsort($nodelink);
      $nodelink=array_slice($nodelink,0,$count*2);
    }
    if ($nodelink) $node[$pagename]=array_keys($nodelink);
  }

  function makeTree($pagename,&$node,&$color,$depth=DEPTH,$count=LEAFCOUNT) {
    if ($depth > 0) {
      if (!$color[$pagename]) $color[$pagename]=$depth;
      #print $depth."\n";
      $depth--;
      $this->getLeafs($pagename,$node,$color,$depth,$count);
      if ($node[$pagename]) {
        # select 25% of links
        $slice= max((int) (sizeof($node[$pagename]) * 0.25),$count);
        #$slice= ($size > $count) ? $size: $count;
        $selected=array_slice($node[$pagename],0,$slice);
        foreach($selected as $leaf)
          $this->makeTree($leaf,$node,$color,$depth,$count);
      } else {
        $node[$pagename]=array(); // no links found
        $color[$pagename]=-9;
      }
    }
    return;
  }
}

function macro_Dot($formatter,$value='',$options=array()) {
  global $DBInfo;

  #getLeafs($options[page],&$node);
  if ($options['w'] and $options['w'] < 5) $count=$options['w'];
  else $count=LEAFCOUNT;
  if ($options['d'] and $options['d'] < 6) $depth=$options['d'];
  else $depth=DEPTH;
  if ($options['f'] and $options['f'] < 12 and $options['f'] > 7 )
    $fontsize=$options['f'];
  else $fontsize=FONTSIZE;

  if ($value and $DBInfo->hasPage($value)) {
    $pgname=$value;
  } else if ($DBInfo->hasPage($options['page'])) {
    $pgname=$options['page'];
  } else {
    return ''; // XXX
  }

  $fontsize= $DBInfo->dot_fontsize ? $DBInfo->dot_fontsize: $fontsize;
  $fontname= $DBInfo->dot_fontname ? $DBInfo->dot_fontname: FONTNAME;
  $dot_options=$DBInfo->dot_options ? $DBInfo->dot_options: '';

  $color=array();
  $tree=new LinkTree($options['arena']);
  $tree->makeTree($pgname,$node,$color,$depth,$count*2);
  if (!$node) $node=array($pgname=>array());
  #print_r($color);
  foreach ($color as $key=>$val) $color[$key]=$val>0 ?$depth-$val:-$val;

  $color[$pgname]=10;

  $myaction='visualtour';
  if (in_array($options['t'],array('visualtour','show')))
    $myaction=$options['t'];

  #print_r($color);
  #print_r(array_keys($node));
  $visualtour=$formatter->link_url("VisualTour");
  $pageurl=qualifiedUrl($formatter->link_url("\\N","?action=$myaction"));

  $colref=array('gray71',
                'olivedrab1','olivedrab2','olivedrab3',
                '"#A4DDF4"','"#83D0ED"','"#63C0E3"',
                'gray53', 'gray40','orangered','yellow');
  $fcolref=array('gray71',
                'olivedrab4','olivedrab4','olivedrab4',
                '"#A4DDF4"','"#83D0ED"','"#63C0E3"',
                'gray53', 'gray40','white','black');
  $colidx=0;
  $dot_head=<<<HEAD
digraph G {
  $dot_options
  ratio="compress"
  URL="$visualtour"
  node [URL="$pageurl", 
fontcolor=black, fontname=$fontname, fontsize=$fontsize]\n
HEAD;

  $allnode=array_keys($node);
  while (list($leafname,$leaf) = @each ($node)) {
    if (!$leafs[($urlname=_rawurlencode($leafname))]) {
      $leafs[$leafname]=$urlname;

      $extra='';
      if ($fcolref[$color[$leafname]])
        $extra=',fontcolor='.$fcolref[$color[$leafname]];
      $out.= '"'.$urlname."\" [label=\"$leafname\",".
             "style=filled,fillcolor=".$colref[$color[$leafname]]."$extra];\n";
    }
    #print $leafname."\n";
    #print_r($node[$leafname]);
    $selected=array_intersect($node[$leafname],$allnode);

    foreach ($selected as $leaf) {
      if (!$leafs[($urlname=_rawurlencode($leaf))]) {
        $leafs[$leaf]=$urlname;
        $extra='';
        if ($fcolref[$color[$leaf]])
          $extra=',fontcolor='.$fcolref[$color[$leaf]];
        $out.= '"'.$urlname."\" [label=\"$leaf\",".
               "style=filled,fillcolor=".$colref[$color[$leaf]]."$extra];\n";
      }
      $out.= "\"".$leafs[$leafname]."\" ->\"".$leafs[$leaf]."\";\n";
    }
  }
  $out.= "};\n";

  $out=$dot_head.$out;

  if (strtoupper($DBInfo->charset) != 'UTF-8') {
    $new=iconv($DBInfo->charset,'UTF-8',$out);
    if ($new) return $new;
  }
  return $out;
}

function do_dot($formatter,$options=array()) {
  header("Content-Type: text/plain");
  print macro_Dot($formatter,$options['page'],$options);
}

// vim:et:sts=2
?>
