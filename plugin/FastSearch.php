<?php
// from http://www.heddley.com/edd/php/search.html
// code itself http://www.heddley.com/edd/php/indexer.tar.gz
//
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// indexer.tar.gz is modified to attach with MoniWiki
// the indexer engine is a perl program, slightly modified by wkpark
// the lookup script also imported adn modified.
//
// a FasetSearch plugin using a index.db for the MoniWiki
//
// Usage: [[FastSearch(string)]]
//
// $Id$

function macro_FastSearch($formatter,$value="",$opts=array()) {
  global $DBInfo;
  $theDB=$DBInfo->data_dir."/index.db";

  if (($dbindex=dba_open("$theDB", "r",$DBInfo->dba_type)) === false) {
    $opts['msg']="Couldn't open search database, sorry.";
    return;
  }

  $words=split(' ', strtolower($value));
  $res=array();
  for(reset($words); $word=current($words); next($words)) {
    $t=strlen($keys=dba_fetch($word,$dbindex));

#   print "'$word' (" . $t/2 . ") ";
    for($i=0; $i<$t;
    // unpack a big-endian short
    $res[ord(substr($keys, $i, 1))*256+ord(substr($keys, $i+1, 1))]++, $i+=2);
  }
  arsort($res);

  $pages=array();
  for(reset($res); $k=key($res); next($res)) {
    $key= dba_fetch("!?" . chr($k/256) . chr($k % 256),$dbindex);
    $pages[]=$key;
  }
  dba_close($dbindex);
#  print_r($pages);

  $needle=_preg_search_escape($value);
  $pattern = '/'.$needle.'/i';
#  if ($opts['case']) $pattern.="i";

  $hits=array();

  foreach ($pages as $key) {
    $page_name= $DBInfo->keyToPagename($key);
    $p = new WikiPage($page_name);
    if (!$p->exists()) continue;

    $body= $p->_get_raw_body();
    $count = preg_match_all($pattern, $body,$matches);
    if ($count) {
      $hits[$page_name] = $count;
      # search matching contexts
      $contexts[$page_name] = find_needle($body,$needle,$opts['context']);
    }
  }

  arsort($hits);

  $out.= "<ul>";
  reset($hits);
  $idx=1;
  while (list($page_name, $count) = each($hits)) {
    $out.= '<li>'.$formatter->link_tag(_rawurlencode($page_name),
          "?action=highlight&amp;value=$value",
          $page_name,"tabindex='$idx'");
    $out.= ' . . . . ' . $count . (($count == 1) ? ' match' : ' matches');
    $out.= $contexts[$page_name];
    $out.= "</li>\n";
    $idx++;
  }
  $out.= "</ul>\n";

  $opts['hits']= count($hits);
  $opts['all']= count($pages);
  return $out;
}

?>
