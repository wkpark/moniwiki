<?php
// Copyright 2004 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Trail plugin for the MoniWiki
//
// Usage: [[Trail(IndexPage)]]
//
// $Id$

function macro_Trail($formatter,$value) {
  global $DBInfo;

  if (!$value or !$DBInfo->hasPage($value))
    return '[[Index('._("No Index page found").')]]';

  $pg=$DBInfo->getPage($value);
  $lines=explode("\n",$pg->get_raw_body());

  $indices=array();
  $count=0;
  foreach ($lines as $line) {
    if (preg_match("/^\s+(\*|\d+\.)\s+(?<!\!)($formatter->wordrule)/",$line,$match)) {
      $indices[]=$match[2];
      $count++;
    }
  }
  if ($count > 1) {
    $prev='';
    $index=$value;
    $next=$indices[0];
  }

  for ($i=0;$i<$count;$i++) {
    if ($indices[$i]==$formatter->page->name) {
      if ($i > 0) $prev=$indices[$i-1];
      if ($i < ($count - 1)) $next=$indices[$i+1];
    }
  }

  if ($count > 1) {
    $save=$formatter->query_string;
    $query='?action=trail&amp;value='.$value;
    $formatter->query_string=$query;
    $pnut='&#x2039; ';
    if ($prev) $pnut.=$formatter->link_repl($prev)." | ";
    $formatter->query_string=$save;
    $pnut.=$formatter->link_repl($index);
    $formatter->query_string=$query;
    if ($next) $pnut.=" | ".$formatter->link_repl($next);
    $pnut.=' &#x203a;';
    $formatter->query_string=$save;
  }
  return $pnut;
}

function do_trail($formatter,$options) {
  $pnut=macro_Trail($formatter,$options['value']);
  $formatter->send_header('',$options);
  $formatter->send_title($title,$formatter->link_url("FindPage"),$options);
  print "<div class='wikiTrailer'>\n";
  print $pnut;
  print "</div>\n";
  $formatter->send_page();
  print "<div class='wikiTrailer'>\n";
  print $pnut;
  print "</div>\n";
  $formatter->send_footer('',$options);
}

// vim:noet:sts=2:
?>
