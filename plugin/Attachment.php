<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple Img macro plugin for the MoniWiki
// vim:et:ts=2:
//
// Usage: [[Img(http://blah.net/blah.png,100,50)]]
//
// $Id$

function macro_Attachment($formatter,$value) {
  global $DBInfo;
  $key=$DBInfo->pageToKeyname($formatter->page->name);
  $upload_file=$DBInfo->upload_dir."/$key/$value";

  if (file_exists($upload_file)) {
    $url=$formatter->link_url($formatter->page->urlname,"?action=download&amp;value=$value");
    if (preg_match("/\.(png|gif|jpeg|jpg)$/",$upload_file))
      return "<img src='$url' alt='$value' />";
    else
      return "<img align='middle' src='$DBInfo->imgs_dir/uploads-16.png' />".
        $formatter->link_to("?action=download&amp;value=$value",$value);
  }
  return $formatter->link_to("?action=UploadFile&amp;rename=$value",sprintf(_("Upload new Attachment \"%s\""),$value));
}

?>
