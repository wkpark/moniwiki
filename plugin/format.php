<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a format plugin to connect with processors for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function do_format($formatter,$options) {
  $mimes=array('text/plain'=>'html','text/xml'=>'text_xml');
  $mimetype=$options['mimetype'];
  if (!$mimetype) $mimetype='text/plain';

  $pi=$formatter->get_instructions();
  if ($pi['#format']=='xsltproc') {
    $options['title']= _("It is a XML format !");
    do_invalid($formatter,$options);
  }
  if (!$formatter->page->exists()) do_invalid($formatter,$options);

  // Detect File type
  else if (array_key_exists($mimetype,$mimes)) {
    header("Content-type: ".$mimetype);
    print $formatter->processor_repl($mimes[$mimetype],$formatter->page->get_raw_body());
  } else {
    $processor=str_replace("/.","__",$mimetype);
    header("Content-type: text/plain");

    if (getProcessor($processor))
      print $formatter->processor_repl($processor,$formatter->page->get_raw_body());
    else
      do_invalid($formatter,$options);
  }

  return;
}

?>
