<?php
// Copyright 2003 2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a bookmark action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

// internal use only
function macro_bookmark($formatter, $value = '', &$options) {
  global $DBInfo;
  global $_COOKIE;

  $user = &$DBInfo->user; # get cookie

  if (!$options['time']) {
     $bookmark = time();
  } else {
     $bookmark = $options['time'];
  }
  $ret = array();
  if (is_numeric($bookmark)) {
    if ($user->id == "Anonymous") {
      setcookie("MONI_BOOKMARK",$bookmark,time()+60*60*24*30,get_scriptname());
      # set the fake cookie
      $_COOKIE['MONI_BOOKMARK']=$bookmark;
      $user->bookmark=$bookmark;
      $ret['title'] = _('Bookmark Changed');
    } else {
      $user->info['bookmark']=$bookmark;
      $DBInfo->udb->saveUser($user);
      $ret['title'] = _('Bookmark Changed');
    }
  } else
    $ret['msg']=_("Invalid bookmark!");

  if (isset($options['ret']))
    $options['ret'] = $ret;
  
  return '';
}

function do_bookmark($formatter,$options) {
  $ret = array();
  $options['ret'] = &$ret;
  $formatter->macro_repl('Bookmark', '', $options);
  if (!empty($ret))
    $options = array_merge($options, $ret);
  $formatter->send_header("",$options);
  $formatter->send_title('', "",$options);
  if (empty($DBInfo->control_read) or $DBInfo->security->is_allowed('read',$options)) {
    $formatter->send_page();
  }
  $formatter->send_footer("",$options);
}

?>
