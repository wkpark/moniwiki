<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Attachment macro plugin for the MoniWiki
// vim:et:ts=2:
//
// Usage: [[Attachment(filename)]]
//
// $Id$

function macro_Attachment($formatter,$value) {
  global $DBInfo;
  if (($dummy=strtok($value,',:')) and $DBInfo->hasPage($dummy)) {
    $key=$DBInfo->pageToKeyname($dummy);
    $value=strtok('');
    $pagename=$dummy;
  } else {
    $pagename=$formatter->page->name;
    $key=$DBInfo->pageToKeyname($formatter->page->name);
  }

  if (($dummy=strpos($value,'?'))) {
    parse_str(substr($value,$dummy+1),$attrs);
    $value=substr($value,0,$dummy);
    foreach ($attrs as $name=>$val)
      $attr.="$name=\"$val\" ";

    if ($attrs['align']) $attr.='class="img'.ucfirst($attrs['align']).'" ';
  }
  $upload_file=$DBInfo->upload_dir."/$key/$value";

  if (file_exists($upload_file)) {
    if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$upload_file)) {
      if ($key != $pagename)
        $url=$formatter->link_url(_urlencode($pagename),"?action=download&amp;value=$value");
      else
        $url=$DBInfo->url_prefix."/".$upload_file;
      return "<span class=\"imgAttach\"><img src='$url' alt='$value' $attr/></span>";
    } else
      return "<span class=\"attach\"><img align='middle' src='$DBInfo->imgs_dir/uploads-16.png' />".
        $formatter->link_to("?action=download&amp;value=$value",$value).'</span>';
  }
  if ($pagename == $formatter->page->name)
    return '<span class="attach">'.$formatter->link_to("?action=UploadFile&amp;rename=$value",sprintf(_("Upload new Attachment \"%s\""),$value)).'</span>';

  $p=$DBInfo->getPage($pagename);
  $f=new Formatter($p);
    return '<span class="attach">'.$f->link_to("?action=UploadFile&amp;rename=$value",sprintf(_("Upload new Attachment \"%s\" on the \"%s\""),$value, $pagename)).'</span>';
}

?>
