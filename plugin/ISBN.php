<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a ISBN macro plugin for the MoniWiki
//
// $Id: ISBN.php,v 1.15 2010/09/07 14:03:08 wkpark Exp $

function macro_ISBN($formatter,$value="") {
  global $DBInfo;

  // http://www.isbn-international.org/en/identifiers/allidentifiers.html
  $default_map=array('89'=>'Aladdin');

  $ISBN_MAP="IsbnMap";
  $DEFAULT=<<<EOS
Amazon http://www.amazon.com/exec/obidos/ISBN= http://images.amazon.com/images/P/\$ISBN.01.MZZZZZZZ.gif
Aladdin http://www.aladdin.co.kr/shop/wproduct.aspx?ISBN= http://image.aladdin.co.kr/cover/cover/\$ISBN_1.gif @(http://image\..*/cover/(?:[^\s_/]*\$ISBN_\d\.(?:jpe?g|gif)))@
Gang http://kangcom.com/common/qsearch/search.asp?s_flag=T&s_text= http://kangcom.com/l_pic/\$ISBN.jpg @bookinfo\.asp\?sku=(\d+)"@\n
EOS;

  $DEFAULT_ISBN="Amazon";
  $re_isbn="/^([0-9\-]+[xX]?)(?:,\s*)?(([A-Z][A-Za-z]*)?(?:,)?(.*))?/x";

  if ($value!='') {
     $test=preg_match($re_isbn,$value,$match);
     if ($test === false)
        return "<p><strong class=\"error\">Invalid ISBN \"%value\"</strong></p>";
  }

  $list= $DEFAULT;
  $map= new WikiPage($ISBN_MAP);
  if ($map->exists()) $list.=$map->get_raw_body();

  $lists=explode("\n",$list);
  $ISBN_list=array();
  foreach ($lists as $line) {
     if (!$line or !preg_match("/^[A-Z]/",$line[0])) continue;
     $dum=explode(" ",rtrim($line));
     $re='';
     $sz=sizeof($dum);
     if (!preg_match('/^(http|ftp)/',$dum[1])) continue;
     if ($sz == 2) {
        $dum[]=$ISBN_list[$DEFAULT_ISBN][1];
     } else if ($sz!=3) {
        if ($sz == 4) {
          if (($p=strpos(substr($dum[3],1),$dum[3][0]))!==false) {
             $retest=substr($dum[3],0,$p+2);
          } else {
             $retest=$dum[3];
          }
          if (preg_match($retest,'')!==false) $re=$dum[3];
        }
        else continue;
     }

     $ISBN_list[$dum[0]]=array($dum[1],$dum[2],$re);
  }

  if ($value=='') {
    $out="<ul>";
    foreach ($ISBN_list as $interwiki=>$v) {
      $href=$ISBN_list[$interwiki][0];
      if (strpos($href,'$ISBN') === false)
        $url=$href.'0738206679';
      else {
        $url=str_replace('$ISBN','0738206679',$href);
      }
      $icon=$DBInfo->imgs_url_interwiki.strtolower($interwiki).'-16.png';
      $sx=16;$sy=16;
      if ($DBInfo->intericon[$interwiki]) {
        $icon=$DBInfo->intericon[$interwiki][2];
        $sx=$DBInfo->intericon[$interwiki][0];
        $sy=$DBInfo->intericon[$interwiki][1];
      }
      $out.="<li><img src='$icon' width='$sx' height='$sy' ".
        "align='middle' alt='$interwiki:' /><a href='$url'>$interwiki</a>: ".
        "<tt class='link'>$href</tt></li>";
    }
    $out.="</ul>\n";
    return $out;
  }

  $isbn2=$match[1];
  $isbn=str_replace('-','',$isbn2);

  #print_r($match);
  if ($match[3]) {
    if (strtolower($match[2][0])=='k') $lang='Aladdin';
    else $lang=$match[3];
  } else {
    $cl = strlen($isbn);
    if ($cl == 13)
      $lang_code=substr($isbn,3,2); // 978 89
    else
      $lang_code=substr($isbn,0,2); // 89
    if (!empty($default_map[$lang_code]))
      $lang=$default_map[$lang_code];
    else
      $lang=$DEFAULT_ISBN;
  }

  $attr='';
  $ext='';
  if ($match[2]) {
    $args=explode(',',$match[2]);
    foreach ($args as $arg) {
      $arg=trim($arg);
      if ($arg == 'noimg') $noimg=1;
      else if (strtolower($arg)=='k') $lang='Aladdin';
      else {
        $name=strtok($arg,'=');
        $val=strtok(' ');
        if ($val) $attr.=$name.'="'.$val.'" '; #XXX
        if ($name == 'align') $attr.='class="img'.ucfirst($val).'" ';
        if ($name == 'img') $ext=$val;
      }
    }
  }

  if ($ISBN_list[$lang]) {
     $booklink=$ISBN_list[$lang][0];
     $imglink=$ISBN_list[$lang][1];
     $imgre=$ISBN_list[$lang][2];
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

  if (empty($noimg) and $imgre and get_cfg_var('allow_url_fopen')) {
     if (($p=strpos(substr($imgre,1),$imgre[0]))!==false) {
        $imgrepl=substr($imgre,$p+2);
        $imgre=substr($imgre,0,$p+2);
        if ($imgrepl=='@') $imgrepl='';
        $imgre=str_replace('$ISBN',$isbn,$imgre);
     }
     $md5sum=md5($booklink);
     // check cache
     $bcache=new Cache_text('isbn');
     if (empty($formatter->refresh) and $bcache->exists($md5sum)) {
        $imgname=trim($bcache->fetch($md5sum));

        $fetch_ok=1;
     } else {
        // fetch the bookinfo page and grep the imagname of the book.
        $fd=fopen($booklink,'r');
        if (is_resource($fd)) {
           while(!feof($fd)) {
              $line=fgets($fd,1024);
              preg_match($imgre,$line,$match);
              if (!empty($match[1])) {
                 $bcache->update($md5sum,$match[1]);
                 $imgname = $match[1];

                 $fetch_ok=1;
                 break;
              }
           }
           fclose($fd);
        }
     }
     if ($fetch_ok) {
        if ($imgrepl)
           $imglink = preg_replace('@'.$imgrepl.'@', $imgname, $imglink);
        else if (!preg_match('/^https?:/', $imgname))
           $imglink = str_replace('$ISBN', $imgname, $imglink);
        else
           $imglink = $imgname;
     }

     if (!empty($fetch_ok) and !empty($DBInfo->isbn_img_download)) {
        # some sites such as the IMDB check the referer and
        # do not permit to show any of its images
        # the $isbn_img_download option is needed to show such images
        preg_match('/^(.*)\.(jpeg|jpg|gif|png)$/i',$imglink,$m);
        if (!empty($m[1]) and isset($m[2])) {
           $myimglink=md5($m[1]).'.'.$m[2];
        }

        if (isset($m[2])) {
           # skip XXX
        } else if (file_exists($DBInfo->upload_dir.'/isbn/'.$myimglink)) {
           $mlink=$formatter->macro_repl('attachment','isbn:'.$myimglink,1);
           $imglink=qualifiedUrl($DBInfo->url_prefix.'/'.$mlink);
        } else {
           $fd=fopen($imglink,'r');
           if (is_resource($fd)) {
              $myimg='';
              while(!feof($fd)) {
                 $myimg.=fread($fd,1024);
              }
              fclose($fd);
              if (!is_dir($DBInfo->upload_dir.'/isbn/')) {
                 umask(000);
                 mkdir($DBInfo->upload_dir.'/isbn/',0777);
                 umask($DBInfo->umask);
              }
              $fd=fopen($DBInfo->upload_dir.'/isbn/'.$myimglink,'w');
              if (is_resource($fd)) {
                 fwrite($fd,$myimg);
                 fclose($fd);
              }
           }
        }
     }
  }

  if (empty($fetch_ok)) {
     if (strpos($imglink, '$ISBN') === false)
        $imglink.=$isbn;
     else {
        if (strpos($imglink, '$ISBN2') === false)
           $imglink=str_replace('$ISBN', $isbn, $imglink);
        else
           $imglink=str_replace('$ISBN2', $isbn2, $imglink);
        if ($ext)
           $imglink=preg_replace('/\.(gif|jpeg|jpg|png|bmp)$/i', $ext, $imglink);
     }
  }

  if (!empty($noimg)) {
    $icon=$DBInfo->imgs_url_interwiki.strtolower($lang).'-16.png';
    $sx=16;$sy=16;
    if (!empty($DBInfo->intericon[$lang])) {
      $icon=$DBInfo->intericon[$lang][2];
      $sx=$DBInfo->intericon[$lang][0];
      $sy=$DBInfo->intericon[$lang][1];
    }
    return "<img src='$icon' alt='$lang:' align='middle' width='$sx' height='$sy' title='$lang' />"."[<a href='$booklink'>ISBN-$isbn2</a>]";
  } else
     return "<a href='$booklink'><img src='$imglink' border='1' title='$lang".
       ":ISBN-$isbn' alt='[ISBN-$isbn2]' class='isbn' $attr /></a>";
}

?>
