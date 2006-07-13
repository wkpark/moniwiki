<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// dot action plugin for the MoniWiki
//
// $Id$

define(DEPTH,3);
define(LEAFCOUNT,2);
define(FONTSIZE,8);

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
          $nodelink[$page]=1;
        } else $nodelink[$page]=1;
      }
    }
    if (sizeof($nodelink) > $count) arsort($nodelink);
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
      }
    }
    return;
  }
}

function do_dot($formatter,$options) {
  global $DBInfo;

  #getLeafs($options[page],&$node);
  if ($options['w'] and $options['w'] < 5) $count=$options['w'];
  else $count=LEAFCOUNT;
  if ($options['d'] and $options['d'] < 6) $depth=$options['d'];
  else $depth=DEPTH;
  if ($options['f'] and $options['f'] < 12 and $options['f'] > 7 )
    $fontsize=$options['f'];
  else $fontsize=FONTSIZE;

  $fontsize= $DBInfo->dot_fontsize ? $DBInfo->dot_fontsize: $fontsize;

  $color=array();
  $tree=new LinkTree($options['arena']);
  $tree->makeTree($options['page'],$node,$color,$depth,$count);
  if (!$node) $node=array($options['page']=>array());
  #print_r($color);
  foreach ($color as $key=>$val) $color[$key]=$depth-$val;

  $color[$options['page']]=10;

  header("Content-Type: text/plain");
  #print_r($color);
  #print_r(array_keys($node));
  $visualtour=$formatter->link_url("VisualTour");
  $pageurl=qualifiedUrl($formatter->link_url("\\N","?action=visualtour"));

  $colref=array('gray71',
                'olivedrab1','olivedrab2','olivedrab3',
                '"#A4DDF4"','"#83D0ED"','"#63C0E3"',
                'gray53', 'gray40','gray30','yellow');
  $colidx=0;
  $out=<<<HEAD
digraph G {
  URL="$visualtour"
  node [URL="$pageurl", 
fontcolor=black, fontname=WEBDOTFONT, fontsize=$fontsize]\n
HEAD;

  $allnode=array_keys($node);
  while (list($leafname,$leaf) = @each ($node)) {
    if (!$leafs[($urlname=_rawurlencode($leafname))]) {
      $leafs[$leafname]=$urlname;
      $out.= '"'.$urlname."\" [label=\"$leafname\",".
             "style=filled,fillcolor=".$colref[$color[$leafname]]."];\n";
    }
    #print $leafname."\n";
    #print_r($node[$leafname]);
    $selected=array_intersect($node[$leafname],$allnode);
    foreach ($selected as $leaf) {
      if (!$leafs[($urlname=_rawurlencode($leaf))]) {
        $leafs[$leaf]=$urlname;
        $out.= '"'.$urlname."\" [label=\"$leaf\",".
               "style=filled,fillcolor=".$colref[$color[$leaf]]."];\n";
      }
      $out.= "\"".$leafs[$leafname]."\" ->\"".$leafs[$leaf]."\";\n";
    }
  }
  $out.= "};\n";

  if (strtoupper($DBInfo->charset) != 'UTF-8') {
    $new=iconv($DBInfo->charset,'UTF-8',$out);
    if ($new) print $new;
    return;
  }
  print $out;
  return;
}

// vim:et:sts=2
?>
