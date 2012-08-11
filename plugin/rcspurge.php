<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rcspurge action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id: rcspurge.php,v 1.4 2006/07/07 12:59:57 wkpark Exp $

function do_rcspurge($formatter,$options) {
  global $DBInfo;

  # XXX 
  if (!$options['show'] and 
     $DBInfo->security->is_protected("rcspurge",$options) and
     !$DBInfo->security->is_valid_password($options['passwd'],$options)) {

    $title= sprintf('Invalid password to purge "%s" !', $options['page']);
    $formatter->send_header("",$options);
    $formatter->send_title($title);
    $formatter->send_footer();
    return;
  }
  if (!preg_match("/^[\d:;\.]+$/",$options['range'])) {
    $options['title']=_("Invalid rcspurge range");
    do_invalid($formatter,$options);
    return;
  }
     
  $title= sprintf(_("RCS purge \"%s\""),$options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  if ($options['range']) {
    $ranges=explode(';',$options['range']);
    foreach ($ranges as $range) {
       if (!trim($range)) continue;
       printf("<h3>range '%s' purged</h3>",$range);
       if ($options['show'])
         print "<tt>rcs -o$range ".$options['page']."</tt><br />";
       else {
         #print "<b>Not enabled now</b> <tt>rcs -o$range  data_dir/".$options[page]."</tt><br />";
         print "<tt>rcs -o$range ".$options['page']."</tt><br />";
         $fp=popen("rcs -o$range ".$formatter->page->filename.$formatter->NULL,'r');
         pclose($fp);
       }
    }
  } else {
    printf("<h3>No version selected to purge '%s'</h3>",$options['page']);
  }
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}

?>
