<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RandomBanner macro plugin for the MoniWiki
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

  $count=count($banner)-1;
  $number=min($number,$count);
  $selected=array_rand($banner,$number);
  if ($number==1) $selected=array($selected);
  $out='';
  foreach ($selected as $idx)
    $out.=$banner[$idx];

  return $out;
}

?>
