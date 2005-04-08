<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Attachment macro plugin for the MoniWiki
//
// Usage: [[Attachment(filename)]]
//
// $Id$

function macro_Attachment($formatter,$value,$option='') {
  global $DBInfo;

  $attr='';
  if ($DBInfo->force_download) $force_download=1;
  if ($DBInfo->download_action) $mydownload=$DBInfo->download_action;
  else $mydownload='download';

  $text='';
  if (($p=strpos($value,' ')) !== false) {
    // [attachment:my.ext hello]
    // [attachment:my.ext attachment:my.png]
    // [attachment:my.ext http://url/../my.png]
    $text=$ntext=substr($value,$p+1);
    $value=substr($value,0,$p);
    if (substr($text,0,11)=='attachment:') {
      $fname=substr($text,11);
      $ntext=macro_Attachment($formatter,$fname,1);
    }
    if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$ntext)) {
      $img_link='<img src="'.$ntext.'" alt="'.$text.'" border="0" />';
      if (!file_exists($ntext)) {
        $mydownload='UploadFile&amp;rename='.$fname;
        $text=sprintf(_("Upload new Attachment \"%s\""),$fname);
        $text=str_replace('"','\'',$text);
      }
      $ntext=qualifiedUrl($DBInfo->url_prefix.'/'.$ntext);
    }
  }

  if (($dummy=strpos($value,'?'))) {
    # for attachment: syntax
    parse_str(substr($value,$dummy+1),$attrs);
    $value=substr($value,0,$dummy);
    foreach ($attrs as $name=>$val) {
      if ($name=='action')
        $mydownload=$val;
      else
        $attr.="$name=\"$val\" ";
    }

    if ($attrs['align']) $attr.='class="img'.ucfirst($attrs['align']).'" ';
  } else if (($dummy=strpos($value,','))) {
    # for Attachment macro
    $args=explode(',',substr($value,$dummy+1));
    $value=substr($value,0,$dummy);
    foreach ($args as $arg)
      $attr.="$arg ";
  }

  if (($p=strpos($value,':')) !== false or ($p=strpos($value,'/')) !== false) {
    $subpage=substr($value,0,$p);
    $file=substr($value,$p+1);
    $value=$subpage.'/'.$file; # normalize page arg
    if ($subpage and $DBInfo->hasPage($subpage)) {
      $pagename=$subpage;
      $key=$DBInfo->pageToKeyname($subpage);
    } else {
      $pagename='';
      $key='';
    }
    $dir=$key ? $DBInfo->upload_dir.'/'.$key:$DBInfo->upload_dir;
  } else {
    $pagename=$formatter->page->name;
    $key=$DBInfo->pageToKeyname($formatter->page->name);
    $dir=$DBInfo->upload_dir.'/'.$key;
    $file=$value;
  }
  // check file name XXX
  if (!$file) return 'attachment:/';

  $upload_file=$dir.'/'.$file;
  if ($option == 1) return $upload_file;
  if (!$text) $text=$file;

  if (file_exists($upload_file)) {
    if (!$img_link && preg_match("/\.(png|gif|jpeg|jpg)$/i",$upload_file)) {
      if ($key != $pagename || $force_download)
        $url=$formatter->link_url(_urlencode($pagename),"?action=$mydownload&amp;value=$value");
      else
        $url=$DBInfo->url_prefix."/"._urlencode($upload_file);
      return "<span class=\"imgAttach\"><img src='$url' alt='$file' $attr/></span>";
    } else {
      $link=$formatter->link_url(_urlencode($pagename),"?action=$mydownload&amp;value=$value",$text);
      if ($img_link)
        return "<span class=\"attach\"><a href='$link'>$img_link</a></span>";

      return "<span class=\"attach\"><img align='middle' src='$DBInfo->imgs_dir_interwiki".'uploads-16.png\' /><a href="'.$link.'">'.$text.'</a></span>';
    }
  }
  if ($pagename == $formatter->page->name)
    return '<span class="attach">'.$formatter->link_to("?action=UploadFile&amp;rename=$file",sprintf(_("Upload new Attachment \"%s\""),$file)).'</span>';

  if (!$pagename) $pagename='UploadFile';
  return '<span class="attach">'.$formatter->link_tag($pagename,"?action=UploadFile&amp;rename=$file",sprintf(_("Upload new Attachment \"%s\" on the \"%s\""),$file, $pagename)).'</span>';
}

// vim:et:sts=2:
?>
