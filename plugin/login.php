<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
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

  $user=new User(); # get from COOKIE VARS
  if ($user->id != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $udb->checkUser($user);
  }

  $jscript='';
  if ($user->id == 'Anonymous' and $DBInfo->use_safelogin) {
    $onsubmit=' onsubmit="javascript:_chall.value=challenge.value;password.value=hex_hmac_md5(challenge.value, hex_md5(password.value))"';
    $jscript.="<script src='$DBInfo->url_prefix/local/md5.js'></script>";
    $time_seed=time();
    $chall=md5(base64_encode(getTicket($time_seed,$_SERVER['REMOTE_ADDR'],10)));
    $passwd_hidden="<input type='hidden' name='_seed' value='$time_seed' />";
    $passwd_hidden.="<input type='hidden' name='challenge' value='$chall' />";
    $passwd_hidden.="<input type='hidden' name='_chall' />\n";
  }

  $login=_("Login:");
  $pass=_("Password:");
  $join=_("Join");

  if ($user->id == 'Anonymous')
  return <<<LOGIN
<div id='wikiLogin'>$jscript
<form method='post' action='$urlpage' $onsubmit>
<input type="hidden" name="action" value="userform" />
<table border='0' cellpadding='2' cellspacing='0'>
<tr><td align='right'>$login</td><td><input name='login_id' size='10' /></td></tr>
<tr><td align='right'>$pass</td><td><input name='password' type='password' size='10' /></td></tr>
<tr><td align='right'><a href='$url'>$join</a></td><td><input type='submit' value='OK' />$passwd_hidden</td></tr>
</table>
</form>
</div>
LOGIN;

  $button=_("Logout");
  $option=_("UserPreferences");
  return <<<LOGOUT
<div id='wikiLogin'>
<form method='post' action='$urlpage'>
<input type="hidden" name="action" value="userform" />
<a href='$url'>$option</a> or <input type='submit' name="logout" value="$button"/>
</form>
</div>
LOGOUT;
}

// vim:et:sts=2:
?>
