<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a ISBN macro plugin for the MoniWiki
//
// $Id$

function macro_ISBN($formatter="",$value="") {
  $ISBN_MAP="IsbnMap";
  $DEFAULT=<<<EOS
Amazon http://www.amazon.com/exec/obidos/ISBN= http://images.amazon.com/images/P/\$ISBN.01.MZZZZZZZ.gif
Aladdin http://www.aladdin.co.kr/catalog/book.asp?ISBN= http://image.aladdin.co.kr/cover/cover/\$ISBN_1.\$EXT?jpg\n
EOS;

  $DEFAULT_ISBN="Amazon";
  $re_isbn="/([0-9\-]+[xX]?)(?:,)?(([A-Z][A-Za-z]*)?(?:,)?(.*))?/x";

  $test=preg_match($re_isbn,$value,$match);
  if ($test === false)
     return "<p><strong class=\"error\">Invalid ISBN \"%value\"</strong></p>";

  $isbn2=$match[1];
  $isbn=str_replace('-','',$isbn2);

  #print_r($match);
  if ($match[3]) {
    if (strtolower($match[2][0])=='k') $lang='Aladdin';
    else $lang=$match[3];
  } else $lang=$DEFAULT_ISBN;

  $attr='';
  $ext='';
  if ($match[2]) {
    $args=explode(',',$match[2]);
    foreach ($args as $arg) {
      if ($arg == 'noimg') $noimg=1;
      else if (strtolower($arg)=='k') $lang='Aladdin';
      else {
        $name=strtok($arg,'=');
        $val=strtok(' ');
        $attr.=$name.'="'.$val.'" ';
        if ($name == 'align') $attr.='class="img'.ucfirst($val).'" ';
        if ($name == 'img') $ext=$val;
      }
    }
  }

  $list= $DEFAULT;
  $map= new WikiPage($ISBN_MAP);
  if ($map->exists()) $list.=$map->get_raw_body();

  $lists=explode("\n",$list);
  $ISBN_list=array();
  foreach ($lists as $line) {
     if (!$line or !preg_match("/^[A-Z]/",$line[0])) continue;
     $dum=explode(" ",rtrim($line));
     if (sizeof($dum) == 2)
        $dum[]=$ISBN_list[$DEFAULT_ISBN][1];
     else if (sizeof($dum) !=3) continue;

     $ISBN_list[$dum[0]]=array($dum[1],$dum[2]);
  }

  if ($ISBN_list[$lang]) {
     $booklink=$ISBN_list[$lang][0];
     $imglink=$ISBN_list[$lang][1];
  } else {
     $booklink=$ISBN_list[$DEFAULT_ISBN][0];
     $imglink=$ISBN_list[$DEFAULT_ISBN][1];
  }

  if (strpos($booklink,'$ISBN') === false)
     $booklink.=$isbn;
  else {
     if (strpos($booklink,'$ISBN2') === false)
        $booklink=str_replace('$ISBN',$isbn,$booklink);
     else
        $booklink=str_replace('$ISBN2',$isbn2,$booklink);
  }

  if (strpos($imglink, '$ISBN') === false)
        $imglink.=$isbn;
  else {
     if (strpos($imglink, '$ISBN2') === false)
        $imglink=str_replace('$ISBN', $isbn, $imglink);
     else
        $imglink=str_replace('$ISBN2', $isbn2, $imglink);
     if ($ext)
        $imglink=str_replace('$EXT', $ext, $imglink);
     else
        $imglink=str_replace('$EXT?', '', $imglink);
  }

  if ($noimg)
     return $formatter->icon['www']."[<a href='$booklink'>ISBN-$isbn2</a>]";
  else
     return "<a href='$booklink'><img src='$imglink' border='1' title='$lang".
       ":ISBN-$isbn' alt='[ISBN-$isbn2]' class='isbn' $attr /></a>";
}

?>
