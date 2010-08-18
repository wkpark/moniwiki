<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Login plugin for the MoniWiki
//
// Usage: [[Login]]
//
// $Id$

function macro_login($formatter,$value="",$options="") {
  global $DBInfo;

  $url=$formatter->link_url('UserPreferences');
  $urlpage=$formatter->link_url($formatter->page->urlname);

  $user=&$DBInfo->user; # get from COOKIE VARS

  $jscript='';
  $onsubmit = '';
  $passwd_hidden = '';
  if ($user->id == 'Anonymous' and !empty($DBInfo->use_safelogin)) {
    $onsubmit=' onsubmit="javascript:_chall.value=challenge.value;password.value=hex_hmac_md5(challenge.value, hex_md5(password.value))"';
    $jscript.="<script src='$DBInfo->url_prefix/local/md5.js'></script>";
    $time_seed=time();
    $chall=md5(base64_encode(getTicket($time_seed,$_SERVER['REMOTE_ADDR'],10)));
    $passwd_hidden="<input type='hidden' name='_seed' value='$time_seed' />";
    $passwd_hidden.="<input type='hidden' name='challenge' value='$chall' />";
    $passwd_hidden.="<input type='hidden' name='_chall' />\n";
  }

  $id=_("ID");
  $pass=_("Password");
  $join=_("Join");
  $login=_("Login");
  if (!empty($formatter->lang))
    $lang = ' lang="'.substr($formatter->lang, 0, 2).'"';

  if ($user->id == 'Anonymous')
  return <<<LOGIN
<div id='wikiLogin'$lang>$jscript
<form method='post' action='$urlpage' $onsubmit>
<div>
<input type="hidden" name="action" value="userform" />
<table border='0' cellpadding='2' cellspacing='0'>
<tr><td align='right'>$id</td><td><input name='login_id' size='10' /></td></tr>
<tr><td align='right'>$pass</td><td><input name='password' type='password' size='10' /></td></tr>
<tr><td align='right'><a href='$url'>$join</a></td><td><span class='button'><input type='submit' class='button' value='$login' /></span>$passwd_hidden</td></tr>
</table>
</div>
</form>
</div>
LOGIN;

  $button=_("Logout");
  $option=_("UserPreferences");
  $msg = sprintf(_("%s or %s"), "<a href='$url'>$option</a>",
    "<span class='button'><input type='submit' class='button' name='logout' value='$button' /></span>");
  return <<<LOGOUT
<div id='wikiLogin'>
<form method='post' action='$urlpage'>
<input type="hidden" name="action" value="userform" />
$msg
</form>
</div>
LOGOUT;
}

function do_login($formatter,$options) {
  global $DBInfo;

  $user=&$DBInfo->user; # get from COOKIE VARS
  if ($user->id != 'Anonymous') {
    $options['logout']=1;
    $url=$formatter->link_url($formatter->page->urlname,'?action=userform&logout=1');
    $formatter->send_header(array('Status: 302','Location: '.$url),$options);
    return;
  }

  $formatter->send_header("",$options);
  $formatter->send_title('','',$options);

  print macro_Login($formatter,'',$options);
  $formatter->send_footer($args,$options);
}

// vim:et:sts=2:
?>
