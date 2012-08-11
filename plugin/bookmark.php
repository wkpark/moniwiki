<?php
// Copyright 2003 2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a bookmark action plugin for the MoniWiki
//
// $Id: bookmark.php,v 1.13 2010/10/02 01:48:43 wkpark Exp $

// internal use only
function macro_bookmark($formatter, $value = '', &$options) {
  global $DBInfo;
  global $_COOKIE;

  $user = &$DBInfo->user; # get cookie

  if (!isset($options['time'][0])) {
     $bookmark = time();
  } else {
     $bookmark = $options['time'];
  }
  $ret = array();

  if ($user->id == "Anonymous") {
    if (is_numeric($bookmark) and $bookmark > 0) {
      setcookie("MONI_BOOKMARK",$bookmark,time()+60*60*24*30,get_scriptname());
      $ret['title'] = _('Bookmark Changed');
    } else {
      setcookie("MONI_BOOKMARK", 0, 0, get_scriptname());
      $ret['title']=_("Bookmark Deleted !");
    }
    # set the fake cookie
    $_COOKIE['MONI_BOOKMARK']=$bookmark;
    $user->bookmark=$bookmark;
  } else {
    if (is_numeric($bookmark) and $bookmark > 0) {
      $ret['title'] = _('Bookmark Changed');
      $user->info['bookmark']=$bookmark;
      $user->bookmark=$bookmark;
    } else {
      $ret['title']=_("Bookmark Deleted !");
      $user->info['bookmark']= null;
    }
    $DBInfo->udb->saveUser($user);
    if (empty($user->info['bookmark']))
      unset($user->bookmark);
  }

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

// vim:et:sts=2:sw=2:
?>
