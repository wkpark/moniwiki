<?php
// Copyright 2003 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a format plugin to connect with processors for the MoniWiki
//
// $Id: format.php,v 1.7 2010/10/05 22:28:54 wkpark Exp $

function do_format($formatter,$options) {
  $mimes=array('text/plain'=>'html','text/xml'=>'text_xml');
  $mimetype=$options['mimetype'];
  $proc=!empty($options['proc']) ? $options['proc']:'';
  if (!$mimetype) $mimetype='text/plain';

  $pi=$formatter->page->get_instructions($dummy);
  if (!$formatter->wordrule) $formatter->set_wordrule($pi);
  if ($pi['#format']=='xsltproc') {
    $options['title']= _("It is a XML format !");
    do_invalid($formatter,$options);
    return;
  }
  if (!$formatter->page->exists()) {
    do_invalid($formatter,$options);
    return;
  } // Detect File type
  else if (empty($proc) and array_key_exists($mimetype,$mimes)) {
    header("Content-type: ".$mimetype);
    print $formatter->processor_repl($mimes[$mimetype],$formatter->page->get_raw_body(),$options);
  } else if (!empty($proc)) {
    #if (getProcessor($processor)) {
    #  do_invalid($formatter,$options);
    #  return;
    #}
    #header("Content-type: ".$mimetype);
    header("Content-type: text/plain");
    print $formatter->processor_repl($proc,$formatter->page->get_raw_body(),$options);
  } else {
    $processor=str_replace("/.","__",$mimetype);
    header("Content-type: text/plain");

    if (getProcessor($processor))
      print $formatter->processor_repl($processor,$formatter->page->get_raw_body(),$options);
    else {
      do_invalid($formatter,$options);
      return;
    }
  }

  return;
}
// vim:et:sts=2:sw=2:

?>
