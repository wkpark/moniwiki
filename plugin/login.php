<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Login plugin for the MoniWiki
//
// Usage: [[Login]]
//
// $Id: login.php,v 1.14 2010/08/18 16:58:10 wkpark Exp $

function macro_login($formatter,$value="",$options="") {
  global $DBInfo;

  $value = trim($value);
  $use_js = $value == 'js';
  if ($formatter->_macrocache and empty($options['call']) and !$use_js)
    return $formatter->macro_cache_repl('Login', $value);

  if (empty($options['call']) and !$use_js)
    $formatter->_dynamic_macros['@Login'] = 1;

  $url=$formatter->link_url('UserPreferences');
  $urlpage = qualifiedUrl($formatter->link_url($formatter->page->urlname));
  $return_url = $urlpage;

  if (!empty($DBInfo->use_ssl_login)) {
    $urlpage = preg_replace('@^http://@', 'https://', $urlpage);
  }

  $user=&$DBInfo->user; # get from COOKIE VARS

  $jscript='';
  $onsubmit = '';
  $passwd_hidden = '';
  if (!empty($DBInfo->use_safelogin)) {
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
  $join = '';
  if (empty($DBInfo->no_register))
    $join=_("Join");
  $login=_("Login");
  if (!empty($formatter->lang))
    $lang = ' lang="'.substr($formatter->lang, 0, 2).'"';

  $form = <<<LOGIN
<div class='wikiLogin'$lang>$jscript
<form method='post' action='$urlpage' $onsubmit>
<div>
<input type="hidden" name="action" value="userform" />
<input type="hidden" name="return_url" value="$return_url" />
<table border='0' cellpadding='2' cellspacing='0'>
<tr><td class='login-label'>$id</td><td><input type='text' name='login_id' size='10' /></td></tr>
<tr><td class='login-label'>$pass</td><td><input name='password' type='password' size='10' /></td></tr>
<tr><td class='login-label'><a href='$url'>$join</a></td><td class='submit'><span class='button'><input type='submit' class='button' value='$login' /></span>$passwd_hidden</td></tr>
</table>
</div>
</form>
</div>
LOGIN;

  $button=_("Logout");
  $option=_("UserPreferences");
  $msg = sprintf(_("%s or %s"), "<a href='$url'>$option</a>",
    "<span class='button'><input type='submit' class='button' name='logout' value='$button' /></span>");

  $attr = '';
  if ($use_js)
    $attr = ' style="display:none"';
  $logout = <<<LOGOUT
<div class='wikiLogout'$attr>
<form method='post' action='$urlpage'>
<input type="hidden" name="action" value="userform" />
$msg
</form>
</div>
LOGOUT;

  if ($use_js) {
    $mid = $formatter->mid++;
    $url = $formatter->link_url('', '?action=login/ajax');
    $js = <<<JS
<script type='text/javascript'>
/*<![CDATA[*/
(function() {
var oldOnload = window.onload;
window.onload = function(e) {
try { oldOnload(); } catch(e) {};
var url = "$url";
var status = HTTPGet(url);
if (status.substring(0, 4) == 'true') {
  var macro = document.getElementById("macro-$mid");
  var login = getElementsByClassName(macro, "wikiLogin")[0];
  var logout = getElementsByClassName(macro, "wikiLogout")[0];
  if (login) login.style.display = 'none';
  if (logout) logout.style.display = 'block';
}
};
})();
/*]]>*/
</script>
JS;
    return "<div id='macro-$mid'>".$form.$logout.'</div>'.$js;
  }

  if ($options['id'] != 'Anonymous')
    return $logout;

  return $form;
}

function ajax_login($formatter, $options) {
  $options['call'] = 1;
  header('Cache-Control: private, max-age=0, must-revalidate, post-check=0, pre-check=0');
  if ($options['id'] != 'Anonymous') {
    echo 'true';
    return;
  }
  echo 'false';
  return;
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
  if (isset($formatter->header_html)) {
    $options['.header'] = true;
    $formatter->send_title('', '', $options);
  }

  echo "<div class='popup'>";
  print macro_Login($formatter,'',$options);
  echo "</div></body></html>";
}

// vim:et:sts=2:
?>
