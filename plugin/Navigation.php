<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Navigation plugin for the MoniWiki
//
// Usage: [[Navigation(IndexPage)]]
//
// $Id$

function normalize_word($page,$word) {
  if ($word[0]=='/') { # SubPage
    $word=$page.$word;
  } else if ($word[0]=='.' and preg_match('/^(\.{1,2})\//',$word,$match)) {
    if ($match[1] == '..') {
      $pos=strrpos($page,"/");
      if ($pos > 0) $upper=substr($page,0,$pos);
      if ($upper) {
        $word=substr($word,2);
        if ($word == '/') $word=$upper;
        else $word=$upper.$word;
      }
    } else {
      $word=substr($word,1);
      if ($word == '/') $word='';
      $word=$page.$word;
    }
  }
  return $word;
}

function macro_Navigation($formatter,$value) {
  global $DBInfo;

  if (!$value or !$DBInfo->hasPage($value))
    return '[[Index('._("No Index page found").')]]';

  $action=1;

  $pg=$DBInfo->getPage($value);
  $lines=explode("\n",$pg->get_raw_body());

  $indices=array();
  $count=0;
  foreach ($lines as $line) {
    if (preg_match("/^\s+(\*|\d+\.)\s+(?<!\!)($formatter->wordrule)/",$line,$match)) {
      $indices[]=normalize_word($value,$match[2]);
      $count++;
    }
  }
  if ($count > 1) {
    $prev='';
    $index=$value;
    $next=$indices[0];
  }

  for ($i=0;$i<$count;$i++) {
    #print $indices[$i];
    #print ':'.$formatter->page->name;
    if ($indices[$i]==$formatter->page->name) {
      if ($i > 0) $prev=$indices[$i-1];
      if ($i < ($count - 1)) {
	$next=$indices[$i+1];
      } else {
        $next = '';
      }
    }
  }

  if ($count > 1) {
    if ($action) {
      $save=$formatter->query_string;
      $query='?action=navigation&amp;value='.$value;
      $formatter->query_string=$query;
    }
    $pnut='&laquo; ';
    if ($prev) $pnut.=$formatter->link_repl($prev);
    if ($action) $formatter->query_string=$save;
    $pnut.=" | ".$formatter->link_repl($index)." | ";
    if ($action) $formatter->query_string=$query;
    if ($next) $pnut.=$formatter->link_repl($next);
    $pnut.=' &raquo;';
    if ($action) $formatter->query_string=$save;
  }
  return $pnut;
}

function do_navigation($formatter,$options) {
  $pnut=macro_Navigation($formatter,$options['value']);
  $formatter->send_header('',$options);
  $formatter->send_title($title,$formatter->link_url("FindPage"),$options);
  print "<div class='wikiNavigation'>\n";
  print $pnut;
  print "</div>\n";
  $formatter->send_page();
  print "<div class='wikiNavigation'>\n";
  print $pnut;
  print "</div>\n";
  $formatter->send_footer('',$options);
}

// vim:noet:sts=2:
?>
