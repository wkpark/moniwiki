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
    $udb->checkUser(&$user);
  }

  if ($user->id == 'Anonymous')

  $login=_("Login:");
  $pass=_("Password:");
  $button=_("Login");
  $join=_("Join");

  if ($user->id == 'Anonymous')
  return <<<LOGIN
<form method='post' action='$urlpage'>
<input type="hidden" name="action" value="userform" />
<table border='0'>
<tr><td align='right'><font size='-1'>$login</font></td><td><input name='login_id' size='10' /></td></tr>
<tr><td align='right'><font size='-1'>$pass</font></td><td><input name='login_passwd' type='password' size='10' /></td></tr>
<tr><td align='right'><a href='$url'>$join</a></td><td><input type='submit' value='$button' /></td></tr>
</table>
</form>
LOGIN;

  $button=_("Logout");
  $option=_("UserPreferences");
  return <<<LOGOUT
<form method='post' action='$url'>
<input type="hidden" name="action" value="userform" />
<a href='$url'>$option</a> or <input type='submit' name="logout" value="$button"/>
</form>
LOGOUT;
}

// vim:et:ts=2:
?>
