<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BlogCategory macro plugin for the MoniWiki
//
// $Id$

function macro_BlogCategories($formatter,$value='') {
  global $DBInfo;

  if (!$DBInfo->hasPage($DBInfo->blog_category)) return '';
  $opts=explode(',',$value);
  if (in_array('norss',$opts)) $no_rss=1;

  $categories=array();
  $page=$DBInfo->getPage($DBInfo->blog_category);

  $raw=$page->get_raw_body();
  $temp= explode("\n",$raw);

  $link=$formatter->link_url($formatter->page->name,'?action=blogchanges&amp;category=CATEGORY');
  foreach ($temp as $line) {
    $line=str_replace('/','_2f',$line);
    if (preg_match('/^ \* ([^ :]+)(?=\s|$)/',$line,$match)) {
      $lnk=str_replace('CATEGORY',$match[1],$link);
      if (!$no_rss)
        $rss='&nbsp;<a href="'.str_replace('blogchanges','blogrss',$lnk).'">'.
          '<img src="'.$DBInfo->imgs_dir.'/tiny-xml.gif'.'" border="0" /></a>';
      $out.="<a href='$lnk'>$match[1]/</a>$rss<br/>";
    }    
  }

  return $out;
}
// vim:et:sts=2:
?>
