<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a restore action plugin for the MoniWiki
//
// $Id$
// vim:et:ts=2:

function do_post_restore($formatter,$options) {
  global $DBInfo;
  $date=date("Ymd");
  umask(02);

  if ($options['ticket'] and $options['tar']) {
    $tar=$DBInfo->upload_dir."/".$options['tar'];

    if ($options['show'])
      $cmd="tar tzf $tar";
    else
      $cmd="tar tzf $tar";

    $title = sprintf(_("Restore %s"),$options['value']);
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);

    print "<pre class='wiki'>";
    print "$ $cmd\n";
    exec($cmd,$log);
    print(join("\n",$log));
    print "</pre>";

    $formatter->send_footer("",$options);
  } else if ($options['value']) {
    $title = sprintf(_("Restore %s ?"),$options['value']);

    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);

    $out="<form method='post' >\n";
    $out.="<input type='hidden' name='action' value='restore' />\n";
    if ($DBInfo->security->is_protected("restore",$options))
      $out.="Password: <input type='password' name='passwd' size='10' />\n";
    $out.="<input type='hidden' name='ticket' value='hello' /> \n";
    $out.="<input type='hidden' name='tar' value='$options[value]' /> \n";
    $out.="<input type='checkbox' name='show' checked='checked' />show only\n";
    $out.="<input type='submit' value='Restore now' /></form>\n";
    print $out;

    $formatter->send_footer("",$options);
  } else {
    $title = _("Restore backuped data");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);

    $options['needle']="/^backup_\d{8}(_\d+)?\.tgz$/";
    $options['download']="restore";
    $options['nodir']=1;
    print macro_UploadedFiles($formatter,'UploadFile',$options);

    print $out;

    $formatter->send_footer("",$options);
  }
  return;
}

?>
