<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// RandomBanner plugin for the MoniWiki
//
// Usage: [[RandomBanner(PageName,number]]
//
// $Id$
// vim:et:ts=2:

function macro_RandomBanner($formatter,$value="") {
  global $DBInfo;

  $test=preg_match("/^([^ ,0-9]*)\s*,?\s*(\d+)?$/",$value,$match);
  if ($test) 
    $pagename=$match[1];$number=$match[2];
  if (!$pagename) $pagename='RandomBanner';
  if (!$number) $number=3;
  #print $pagename.";".$number;
  if ($DBInfo->hasPage($pagename)) {
    $page=$DBInfo->getPage($pagename);
    $body=$page->_get_raw_body();
  } else
    return "[[RandomBanner($value)]]";

  $banner=array();
  $lines=explode("\n",$body);
  foreach ($lines as $line) {
    if (substr($line,0,10)!= ' * http://') continue;
    $dummy=explode(" ",substr($line,3),3);
    if (preg_match(",^(http://|ftp://).*(gif|png|jpg|jpeg)$,",$dummy[1],$match)) {
      $banner[]="<a href='$dummy[0]'><img border='0' src='$dummy[1]' title='$dummy[2]'></a> ";
    }
  }

  $out="";
  $count=count($banner)-1;
  $number=min($number,$count);
  while($number > 0) {
    $idx=rand(0,$count);
    if ($selected[$idx]) continue;
    $out.=$banner[$idx];
    $selected[$idx]=1;$number--;
  }

  return $out;
}

?>
