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
    $pnut='&#x2039; ';
    if ($prev) $pnut.=$formatter->link_repl($prev)." | ";
    $pnut.=$formatter->link_repl($index);
    if ($next) $pnut.=" | ".$formatter->link_repl($next);
    $pnut.=' &#x203a;';
  }
  return $pnut;
}

// vim:noet:sts=2:
?>
