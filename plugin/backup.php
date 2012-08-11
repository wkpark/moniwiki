<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a backup action plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id: backup.php,v 1.3 2006/07/07 12:51:31 wkpark Exp $
// vim:et:ts=2:

function do_post_backup($formatter,$options) {
  global $DBInfo;
  $date=date("Ymd");
  umask(02);

  if ($options['ticket']) {
    $tar=$DBInfo->upload_dir."/backup_$date.tgz";
    $dummy=0;
    while (file_exists($tar)) {
      $dummy=$dummy+1;
      $new="backup_$date"."_".$dummy.".tgz"; // rename file
      $tar=$DBInfo->upload_dir."/".$new;
      $file_path= $dir."/".$upfilename;
    }

    # XXX
    $dest_files = " ".$DBInfo->text_dir;
    echo $DBInfo->user_dir;
    $dest_files.= " ".$DBInfo->user_dir;
    $dest_files.= " ".$DBInfo->editlog_name;
    if (file_exists($DBInfo->data_dir."/counter.db"))
        $dest_files .= " ".$DBInfo->data_dir."/counter.db";
    $verbose_option = $options['verbose'] ? 'v' : '';
    $cmd="tar c{$verbose_option}pzf $tar $dest_files";

    $formatter->send_header("",$options);

    $formatter->errlog();
    $fp=popen($cmd.' > '.$formatter->mylog,'r');
    if (is_resource($fp)) {
      pclose($fp);
      $options['msg']=_("Your wiki is backuped successfully");
      $formatter->send_title("","",$options);
      print '<pre class="errlog">';
      print $cmd."\n";
      print $formatter->get_errlog();
      print "</pre>";
    } else {
      $options['msg']=_("Backup failed !");
      $formatter->send_title("","",$options);
    }
    $formatter->send_footer("",$options);
  } else {
    $title = _("Did you want to Backup your wiki ?");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);

    $out="<form method='post' >\n";
    $out.="<input type='hidden' name='action' value='backup' />\n";
    if ($DBInfo->security->is_protected("backup",$options))
      $out.="Password: <input type='password' name='passwd' size='10' />\n";
    $out.="<input type='hidden' name='ticket' value='hello' /> \n";
    $out.="<input type='checkbox' name='verbose' checked='checked' />verbosely\n";
    $out.="<input type='submit' value='Backup now' /></form>\n";

    print $out;

    $formatter->send_footer("",$options);
  }
  return;
}

function macro_backup($formatter,$value) {
  
}

?>
