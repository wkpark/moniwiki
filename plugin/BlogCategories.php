<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BlogCategory macro plugin for the MoniWiki
//
// $Id$

function macro_BlogCategories($formatter,$value='') {
  global $DBInfo;

  $depth='';
  if (!$DBInfo->hasPage($DBInfo->blog_category)) return '';
  $opts=explode(',',$value);
  if (in_array('norss',$opts)) $no_rss=1;
  if (in_array('all',$opts)) $depth=',';

  $categories=array();
  $page=$DBInfo->getPage($DBInfo->blog_category);

  $raw=$page->get_raw_body();
  $raw=preg_replace("/(\{\{\{$)(.*)(\}\}\})/ms",'',$raw);
  $temp= explode("\n",$raw);

  $link=$formatter->link_url($formatter->page->name,'?action=blogchanges&amp;category=CATEGORY');
  foreach ($temp as $line) {
    #$line=str_replace('/','_2f',$line);
    if (preg_match('/^(\s{1'.$depth.'})\* ([^:]+)(?=\s|:|$)/',$line,$match)) {
      $text=rtrim($match[2]);
      $category=str_replace(array('[',']','"','\''),'',$text);
      $category=_rawurlencode($category);
      $lnk=str_replace('CATEGORY',$category,$link);
      if (!$no_rss)
        $rss='&nbsp;<a href="'.str_replace('blogchanges','blogrss',$lnk).'">'.
          '<img src="'.$DBInfo->imgs_dir.'/tiny-xml.gif'.'" border="0" /></a>';
      $dep=str_replace(' ','&nbsp;&nbsp;',$match[1]);
      $out.="$dep<a href='$lnk'>$text/</a>$rss<br/>";
    }
  }

  return $out;
}

function do_blogcategories($formatter,$options) {
  global $DBInfo;
  $formatter->send_header("",$options);
  $formatter->send_title("Blog Categories","",$options);
  $formatter->send_page('== ['.$DBInfo->blog_category.'] ==');
  print macro_BlogCategories($formatter,'all',$options);
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}
// vim:et:sts=2:
?>
