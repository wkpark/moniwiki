<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// dot action plugin for the MoniWiki
//
// $Id$

function do_dot($formatter,$options) {
  define(DEPTH,3);
  define(LEAFCOUNT,3);

  function getLeafs($pagename,$node,$count=LEAFCOUNT) {
    $p= new WikiPage($pagename);
    $f= new Formatter($p);
    $links=$f->get_pagelinks();
    $links=explode("\n",$links);
    foreach ($links as $page) {
      if (!$node[$page] && $page) {
        $p= new WikiPage($page);
        $f= new Formatter($p);
        $leafs=$f->get_pagelinks();
        if ($leafs) {
          $leafs=explode("\n",$leafs);
          #$tree[$page]=$leafs;
          $nodelink[$page]=sizeof($leafs);
        }
      }
    }
    if (sizeof($nodelink) > $count) {
      arsort($nodelink);
      $nodelink=array_slice($nodelink,0,$count);
    }
    if ($nodelink)
      $node[$pagename]=array_keys($nodelink);
  }

  function makeTree($pagename,$node,$depth=DEPTH,$count=LEAFCOUNT) {
    if ($depth > 0) {
      $depth--;
      getLeafs($pagename,&$node,$count);
      if ($node[$pagename])
        foreach($node[$pagename] as $leaf)
          makeTree($leaf,&$node,$depth,$count);
    }
  }

  #getLeafs($options[page],&$node);
  if ($options[w]) $count=$options[w];
  else $count=LEAFCOUNT;
  if ($options[d]) $depth=$options[d];
  else $depth=DEPTH;
  makeTree($options[page],&$node,$depth,$count);

  header("Content-Type: text/plain");
  $visualtour=$formatter->link_url("VisualTour");
  $pageurl=$formatter->link_url("\\N");
  print <<<HEAD
digraph G {
  URL="$visualtour"
  node [URL="$pageurl", 
fontcolor=black, fontsize=10]\n
HEAD;
  while (list($leafname,$leaf) = @each ($node)) {
      foreach ($node[$leafname] as $leaf) {
        print "\"$leafname\" ->\"$leaf\";\n";
      }
    }
  print "};\n";
}

?>
