<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BlogArchive macro plugin for the MoniWiki
//
// $Id$

function macro_BlogArchive($formatter,$value,$options=array()) {
  global $DBInfo;
  $handle = @opendir($DBInfo->cache_dir."/blogchanges");
  if (!$handle) return array();
  if (!$value) $value='BlogChanges';

  $year=date('Y');
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

  foreach ($archives as $archive) {
    $year=substr($archive,0,4);
    $date=substr($archive,4);
    $out.='<li>'.
      $formatter->link_tag($value,'?date='.$archive,$year.'-'.$date).'</li>';
  }
  return '<ul>'.$out.'</ul>';
}
?>
