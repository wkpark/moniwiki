<?php
// Copyright 2004-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BlogArchives macro plugin for the MoniWiki
//
// [[BlogArchives]]
// [[BlogArchives("F Y")]]
// [[BlogArchives("F Y",list)]]
//
// $Id: BlogArchives.php,v 1.3 2005/03/11 09:01:36 iolo Exp $

function macro_BlogArchives($formatter,$value,$options=array()) {
  global $DBInfo;

  $cache = new Cache_Text('blogchanges', array('hash'=>''));

  preg_match("/^(?(?=')'([^']+)'|\"([^\"]+)\")?(\s*,?.*)$/",$value,$match);
  if ($match[1] or $match[2]) {
    $date_fmt=$match[1] ? $match[1]:$match[2];
  } else
    $date_fmt='Y-m';
  $opts=explode(',',$match[3]);
  $opts = array_map('trim', $opts);
  if (in_array('list',$opts)) {
    $bra='<li>';
    $ket='</li>';
  } else {
    $bra='';
    $ket="<br/>\n";
  }

  $year=date('Y');
  // show only recent two years
  $rule="/^(($year|".($year-1).")\d{2})\d{2}/";
  $archives=array();

  $files = array();
  $cache->_caches($files);
  foreach ($files as $file) {
    if (preg_match($rule,$file,$match)) {
      $archives[]=$match[1];
    }
  }

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
