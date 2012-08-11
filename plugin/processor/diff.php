<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a diff colorizer plugin for the MoniWiki
//
// Usage: {{{#!diff
// - hello world
// + Hello world
// }}}
// $Id: diff.php,v 1.5 2010/04/19 11:26:47 wkpark Exp $

function processor_diff($formatter,$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  #list($dummy, $type)=explode(' ',$line);
  $tmp = explode(' ',$line);
  $type = isset($tmp[1]) ? $tmp[1] : '';
  if (in_array($type,array('fancy','simple')))
    $options['type']=$type;
  else
    $options['type']='fancy';
  // add first two blank lines
  //$options['text']="\n\n".$value;
  $options['text']=$value;
  return $formatter->macro_repl('Diff','',$options);
}

?>
