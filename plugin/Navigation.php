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

  $group='';#$formatter->group;
  $current=$formatter->page->name;
  if ($formatter->group)
    $current=$formatter->page->name;
  if (strpos($value,'~')) {
    $group=strtok($value,'~').'~';
    $page=strtok('');
  } else
    $page=$value;

#  print $current;

  $indices=array();
  $count=0;
  foreach ($lines as $line) {
    if (preg_match("/^\s+(\*|\d+\.)\s+(?<!\!)($formatter->wordrule)/",$line,$match)) {
      $word=$match[2];
      if ($word[0]=='[') $word=substr($word,1,-1);
      if ($word[0]=='"') $word=substr($word,1,-1);

      list($index,$text,$dummy)= normalize_word($word,$group,$page);
      if ($group)
	$indices[]=$index;
      else $indices[]=$index;
      $count++;
    }
  }

  #print_r($indices);
  if ($count > 1) {
    $prev='';
    $index_text=$value;
    if ($group) {
      $index=$value;
      $index_text=substr($index,strlen($group));
    }
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
    if ($prev) {
      $prev_text=$prev;
      if (($p=strpos($prev,'~'))!==false)
        $prev_text=substr($prev,$p+1);
      $pnut.=$formatter->link_repl("[wiki:$prev $prev_text]"," accesskey=\",\" ");
    }
    if ($use_action) $formatter->query_string=$save;
    $pnut.=" | ".$formatter->link_repl("[wiki:$index $index_text]")." | ";
    if ($use_action) $formatter->query_string=$query;
    if ($next) {
      $next_text=$next;
      if (($p=strpos($next,'~'))!==false)
        $next_text=substr($next,$p+1);
      $pnut.=$formatter->link_repl("[wiki:$next $next_text]"," accesskey=\".\" ");
    }
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
