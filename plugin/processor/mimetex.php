<?php
// Copyright 2003,2004 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Echo processor plugin for the MoniWiki
//
// $Id$

function processor_mimetex($formatter,$value) {
  global $DBInfo;
  $value=escapeshellarg($value);
  preg_match('/^\'\s*\$+([^\$]*)\$+\s*\'/',$value,$match);
  $tex=$match[1];
  return '<img src=\''.$DBInfo->url_prefix.'/mimetex.cgi?'.$tex.'\' alt=\''.
    str_replace('\'','&#039;',$tex).'\' />';
}
// vim:et:sts=2:sw=2:
?>
