<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a bookmark action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function do_bookmark($formatter,$options) {
  global $DBInfo;
  global $_COOKIE;

  $user=&$DBInfo->user; # get cookie

  if (!$options['time']) {
     $bookmark=time();
  } else {
     $bookmark=$options['time'];
  }
  if (0 === strcmp($bookmark , (int)$bookmark)) {
    if ($user->id == "Anonymous") {
      setcookie("MONI_BOOKMARK",$bookmark,time()+60*60*24*30,get_scriptname());
      # set the fake cookie
      $_COOKIE['MONI_BOOKMARK']=$bookmark;
      $user->bookmark=$bookmark;
      $options['msg'] = 'Bookmark Changed';
    } else {
      $user->info['bookmark']=$bookmark;
      $DBInfo->udb->saveUser($user);
      $options['msg'] = 'Bookmark Changed';
    }
  } else
    $options['msg']="Invalid bookmark!";
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  if (!$DBInfo->control_read or $DBInfo->security->is_allowed('read',$options)) {
    $formatter->send_page();
  }
  $formatter->send_footer("",$options);
}

?>
