<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a WordIndex plugin for the MoniWiki
//
// Usage: [[WordIndex]]
//
// $Id$
// vim:et:ts=2:

function macro_WordIndex($formatter,$value) {
  global $DBInfo;

  $all_pages= $DBInfo->getPageLists();

  if ($DBInfo->use_titlecache) {
    $cache=new Cache_text('title');
  }

  foreach ($all_pages as $page) {
    if ($DBInfo->use_titlecache and $cache->exists($page))
      $title=$cache->fetch($page);
    else
      $title=$page;
    $tmp=preg_replace("/[\?!$%\.\^;&\*()_\+\|\[\] \-~\/]/"," ",$title);
    $tmp=preg_replace("/((?<=[A-Za-z0-9])[A-Z][a-z0-9])/"," \\1",ucwords($tmp));
    $words=preg_split("/\s+/",$tmp);
    foreach ($words as $word) {
      $word=ltrim($word);
      if (!$word) continue;
      if ($dict[$word])
        $dict[$word][]=$page;
      else
        $dict[$word]=array($page);
    }
  }
  #ksort($dict);
  #ksort($dict,SORT_STRING);
  #uksort($dict, "strnatcasecmp");
  uksort($dict, "strcasecmp");

  $key=-1;
  $out="";
  $keys=array();
  foreach ($dict as $word=>$pages) {
    $pkey=get_key("$word");
#   $key=strtoupper($page[0]);
    if ($key != $pkey) {
      if ($key !=-1) $out.="</ul>";
      $key=$pkey;
      $keys[]=$key;
      $out.= "<a name='$key' /><h3><a href='#top'>$key</a></h3>\n";
    }

    $out.= "<h4>$word</h4>\n";
    $out.= "<ul>\n";
    foreach ($pages as $page)
      $out.= '<li>' . $formatter->word_repl('"'.$page.'"')."</li>\n";
    $out.= "</ul>\n";
  }

  $index="";
  foreach ($keys as $key) {
    $name=$key;
    if ($key == 'Others') $name=_("Others");
    $index.= "| <a href='#$key'>$name</a> ";
  }
  $index[0]=" ";

  return "<center><a name='top' />$index</center>\n$out";
}

?>
