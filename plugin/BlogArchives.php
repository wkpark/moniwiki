<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BlogArchives macro plugin for the MoniWiki
//
// [[BlogArchives]]
// [[BlogArchives("F Y")]]
// [[BlogArchives("F Y",list)]]
//
// $Id$

function macro_BlogArchives($formatter,$value,$options=array()) {
  global $DBInfo;

  $handle = @opendir($DBInfo->cache_dir."/blogchanges");
  if (!$handle) return array();

  preg_match("/^(?(?=')'([^']+)'|\"([^\"]+)\")?(\s*,?.*)$/",$value,$match);
  if ($match[1] or $match[2]) {
    $date_fmt=$match[1] ? $match[1]:$match[2];
  } else
    $date_fmt='Y-m';
  $opts=explode(',',$match[3]);
  if (in_array('list',$opts)) {
    $bra='<li>';
    $ket='</li>';
  } else {
    $bra='';
    $ket="<br/>\n";
  }

  $year=date('Y');
  // show only recent two years
  $rule="/^(($year|".($year-1).")\d{2})\d{2}_2e/";
  $archives=array();
  while ($file = readdir($handle)) {
    $fname=$DBInfo->cache_dir.'/blogchanges/'.$file;
    if (is_dir($fname)) continue;
    if (preg_match($rule,$file,$match)) {
      $archives[]=$match[1];
    }
  }
  closedir($handle);
  $archives= array_unique($archives);
  rsort($archives);

  $out='';
  foreach ($archives as $archive) {
    $year=substr($archive,0,4);
    $month=substr($archive,4);
    $datetext=date($date_fmt,mktime(0,0,0,$month,1,$year));
    $out.=$bra.
      $formatter->link_to('?action=blogchanges&amp;date='.$archive,$datetext).
      $ket;
  }
  if ($bra) return '<ul>'.$out.'</ul>';
  return $out;
}
?>
