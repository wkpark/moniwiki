<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// dot action plugin for the MoniWiki
//
// $Id$

define(DEPTH,3);
define(LEAFCOUNT,2);
define(FONTSIZE,8);

  function getLeafs($pagename,&$node,&$color,$depth,$count=LEAFCOUNT) {
    $p= new WikiPage($pagename);
    $f= new Formatter($p);
    $links=$f->get_pagelinks();
    $links=explode("\n",$links);
    foreach ($links as $page) {
      if (!$color[$page]) $color[$page]=$depth;
      if ($page) {
        if (!$node[$page]) {
          $p= new WikiPage($page);
          $f= new Formatter($p);
          $leafs=$f->get_pagelinks();
          if ($leafs) {
            $leafs=explode("\n",$leafs);
            # XXX 
            $nodelink[$page]=$p->size();
          }
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
      getLeafs($pagename,$node,$color,$depth,$count);
      if ($node[$pagename]) {
        # select 25% of links
        $size= (int) (sizeof($node[$pagename]) * 0.25);
        $slice= ($size > $count) ? $size: $count;
        $selected=array_slice($node[$pagename],0,$slice);
        foreach($selected as $leaf)
          makeTree($leaf,$node,$color,$depth,$count);
      }
    }
    return;
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

  $color=array();
  makeTree($options['page'],$node,$color,$depth,$count);
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

?>
