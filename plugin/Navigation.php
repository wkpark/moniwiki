<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Navigation plugin for the MoniWiki
//
// Usage: [[Navigation(IndexPage)]]
//
// $Id$

function macro_Navigation($formatter,$value) {
  global $DBInfo;

  if (!$value or !$DBInfo->hasPage($value))
    return '[[Index('._("No Index page found").')]]';

  preg_match('/([^,]+),?\s*,?(.*)/',$value,$match);
  $opts=explode(',',$match[2]);
  $value=$match[1];
  $use_action=0;
  if (in_array('action',$opts)) $use_action=1;

  $pg=$DBInfo->getPage($value);
  $lines=explode("\n",$pg->get_raw_body());

  $group='';
  $current=$formatter->page->name;
  if ($formatter->group)
    $current=preg_replace('/~/','.',$formatter->page->name,1);
  if (strpos($value,'~')) {
    $group=strtok($value,'~').'~';
    $page=strtok('');
  }

#  print $current;

  $indices=array();
  $count=0;
  foreach ($lines as $line) {
    if (preg_match("/^\s+(\*|\d+\.)\s+(?<!\!)($formatter->wordrule)/",$line,$match)) {
      $word=$match[2];
      if ($word[0]=='[') $word=substr($word,1,-1);
      if ($word[0]=='"') $word=substr($word,1,-1);

      list($index,$text,$dummy)= normalize_word($word,$group,$page);
      if ($group) $indices[]=preg_replace('/~/','.',$index,1);
      else $indices[]=$index;
      $count++;
    }
  }

  #print_r($indices);
  if ($count > 1) {
    $prev='';
    if ($group) $index=preg_replace('/~/','.',$value,1);
    else $index=$value;
    $next=$indices[0];
  }

  for ($i=0;$i<$count;$i++) {
    #print $indices[$i];
    #print ':'.$formatter->page->name;
    if ($indices[$i]==$current) {
      if ($i > 0) $prev=$indices[$i-1];
      if ($i < ($count - 1)) {
	$next=$indices[$i+1];
      } else {
        $next = '';
      }
    }
  }

  if ($count > 1) {
    if ($use_action) {
      $save=$formatter->query_string;
      $query='?action=navigation&amp;value='.$value;
      $formatter->query_string=$query;
    }
    $pnut='&laquo; ';
    if ($prev) $pnut.=$formatter->link_repl($prev);
    if ($use_action) $formatter->query_string=$save;
    $pnut.=" | ".$formatter->link_repl($index)." | ";
    if ($use_action) $formatter->query_string=$query;
    if ($next) $pnut.=$formatter->link_repl($next);
    $pnut.=' &raquo;';
    if ($use_action) $formatter->query_string=$save;
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
