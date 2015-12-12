<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a UserPreferences plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-04-12
// Name: UserPreferences macro plugin
// Description: show UserPreferences form
// URL: MoniWiki:UserPreferencesPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[UserPreferences]]
//

function macro_UserPreferences($formatter,$value,$options='') {
    global $DBInfo;

    if ($formatter->_macrocache and empty($options['call']))
        return $formatter->macro_cache_repl('UserPreferences', $value);
    $formatter->_dynamic_macros['@UserPreferences'] = 1;

    $use_any=0;
    if (!empty($DBInfo->use_textbrowsers)) {
        if (is_string($DBInfo->use_textbrowsers))
            $use_any= preg_match('/'.$DBInfo->use_textbrowsers.'/',
                $_SERVER['HTTP_USER_AGENT']) ? 1:0;
        else
            $use_any= preg_match('/Lynx|w3m|links/',
                $_SERVER['HTTP_USER_AGENT']) ? 1:0;
    }

    $user = $DBInfo->user; # get from COOKIE VARS

    // User class support login method
    $login_only = false;
    if (method_exists($user, 'login'))
        $login_only = true;

    $jscript='';
    if (!empty($DBInfo->use_safelogin)) {
        $onsubmit=' onsubmit="javascript:_chall.value=challenge.value;password.value=hex_hmac_md5(challenge.value, hex_md5(password.value))"';
        $jscript.="<script src='$DBInfo->url_prefix/local/md5.js'></script>";
        $time_seed=time();
        $chall=md5(base64_encode(getTicket($time_seed,$_SERVER['REMOTE_ADDR'],10)));
        $passwd_hidden="<input type='hidden' name='_seed' value='$time_seed' />";
        $passwd_hidden.="<input type='hidden' name='challenge' value='$chall' />";
        $passwd_hidden.="<input type='hidden' name='_chall' />\n";
        $pw_length=32;
    } else {
        $passwd_hidden = '';
        $onsubmit = '';
        $pw_length=20;
    }

    $passwd_btn=_("Password");
    $url = qualifiedUrl($formatter->link_url($formatter->page->urlname));
    $return_url = $url;

    if (!empty($DBInfo->use_ssl_login))
        $url = preg_replace('@^http://@', 'https://', $url);

    # setup form
    if ($user->id == 'Anonymous') {
        if (!empty($options['login_id'])) {
            $login_id = _html_escape($options['login_id']);
            $idform = $login_id."<input type='hidden' name='login_id' value=\"$login_id\" />";
        } else
            $idform="<input type='text' size='20' name='login_id' value='' />";
    } else {
        $idform=$user->id;
        if (!empty($user->info['idtype']) and $user->info['idtype']=='openid') {
            $idform='<img src="'.$DBInfo->imgs_dir_url.'/openid.png" alt="OpenID:" style="vertical-align:middle" />'.
                '<a href="'.$idform.'">'.$idform.'</a>';
        }
    }

    $button=_("Login");
    $openid_btn=_("OpenID");
    $openid_form='';
    if ($user->id == 'Anonymous' && !empty($DBInfo->use_openid)) {
        $openid_form=<<<OPENID
  <tr>
    <th>OpenID</th>
    <td>
      <input type="text" name="openid_url" value="" style="background:url($DBInfo->imgs_dir_url/openid.png) no-repeat; padding:2px;padding-left:24px; border-width:1px" />
	    <span class="button"><input type="submit" class="button" name="login" value="$button" /></span> &nbsp;
    </td>
  </tr>
OPENID;
    }
    $id_btn=_("ID");
    $sep="<tr><td colspan='2'><hr /></td></tr>\n";
    $sep0='';
    $login = '';
    if ($user->id == 'Anonymous' and !isset($options['login_id']) and $value!="simple") {
        if (isset($openid_form) and $value != 'openid') $sep0=$sep;
        if ($value != 'openid')
            $default_form=<<<MYFORM
  <tr><th>$id_btn&nbsp;</th><td>$idform</td></tr>
  <tr>
     <th>$passwd_btn&nbsp;</th><td><input type="password" size="15" maxlength="$pw_length" name="password" value="" /></td>
  </tr>
  <tr><td></td><td>
    $passwd_hidden
    <span class="button"><input type="submit" class="button" name="login" value="$button" /></span> &nbsp;
  </td></tr>
MYFORM;
        $login=<<<FORM
<div>
<form method="post" action="$url"$onsubmit>
<div>
<input type="hidden" name="action" value="userform" />
<input type="hidden" name="return_url" value="$return_url" />
<table border="0">
$openid_form
$sep0
$default_form
</table>
</div>
</form>
</div>
FORM;
        $openid_form='';
    }

    $logout = '';
    $joinagree = empty($DBInfo->use_agreement) || !empty($options['joinagreement']);

    if (!$login_only and $user->id == 'Anonymous') {
        if (isset($options['login_id']) or !empty($_GET['join']) or $value!="simple") {
            $passwd=!empty($options['password']) ? $options['password'] : '';
            $button=_("Make profile");
            if ($joinagree and empty($DBInfo->use_safelogin)) {
                $again="<b>"._("password again")."</b>&nbsp;<input type='password' size='15' maxlength='$pw_length' name='passwordagain' value='' /></td></tr>";
            }
            $email_btn=_("Mail");
            if (empty($options['agreement']) or !empty($options['joinagreement']))
                $extra=<<<EXTRA
                    <tr><th>$email_btn&nbsp;</th><td><input type="text" size="40" name="email" value="" /></td></tr>
EXTRA;
            if (!empty($DBInfo->use_agreement) and !empty($options['joinagreement']))
                $extra.= '<input type="hidden" name="joinagreement" value="1" />';
            if (!$use_any and !empty($DBInfo->use_ticket)) {
                $seed=md5(base64_encode(time()));
                $ticketimg=$formatter->link_url($formatter->page->urlname,'?action=ticket&amp;__seed='.$seed);
                $extra.=<<<EXTRA
  <tr><td><img src="$ticketimg" alt="captcha" />&nbsp;</td><td><input type="text" size="10" name="check" />
<input type="hidden" name="__seed" value="$seed" /></td></tr>
EXTRA;
            }
        } else {
            $button=_("Login or Join");
        }
    } else if ($user->id != 'Anonymous') {
        $button=_("Save");
        $css=!empty($user->info['css_url']) ? $user->info['css_url'] : '';
        $css = _html_escape($css);
        $email=!empty($user->info['email']) ? $user->info['email'] : '';
        $email = _html_escape($email);
        $nick=!empty($user->info['nick']) ? $user->info['nick'] : '';
        $nick = _html_escape($nick);
        $check_email_again = '';
        if (!empty($user->info['eticket'])) {
            list($dummy, $em) = explode('.', $user->info['eticket'], 2);
            if (!empty($em))
                $check_email_again = ' <input type="submit" name="button_check_email_again" value="'._("Resend confirmation mail").'" />';
        }

        $tz_offset=!empty($user->info['tz_offset']) ? $user->info['tz_offset'] : 0;
        if (!empty($user->info['password']))
            $again="<b>"._("New password")."</b>&nbsp;<input type='password' size='15' maxlength='$pw_length' name='passwordagain' value='' /></td></tr>";
        else
            $again='';

        if (preg_match("@^https?://@",$user->id)) {
            $nick_btn=_("Nickname");
            $nick=<<<NICK
  <tr><th>$nick_btn&nbsp;</th><td><input type="text" size="40" name="nick" value="$nick" /></td></tr>
NICK;
        }

        $tz_off=date('Z');
        $opts = '';
        for ($i=-47;$i<=47;$i++) {
            $val=1800*$i;
            $tz=gmdate("Y/m/d H:i",time()+$val);
            $hour=sprintf("%02d",abs((int)($val / 3600)));
            $z=$hour . (($val % 3600) ? ":30":":00");
            if ($val < 0) $z="-".$z;
            if ($tz_offset !== '' and $val== $tz_offset)
                $selected=" selected='selected'";
            else
                $selected="";

            $opts.="<option value='$z'$selected>$tz [$z]</option>\n";
        }

        $jscript.="<script src='$DBInfo->url_prefix/local/tz.js'></script>";
        $email_btn=_("Mail");
        $tz_btn=_("Time Zone");
        $extra=<<<EXTRA
$nick
  <tr><th>$email_btn&nbsp;</th><td><input type="text" size="40" name="email" value="$email" />$check_email_again</td></tr>
  <tr><th>$tz_btn&nbsp;</th><td><select name="timezone">
  $opts
  </select> <span class='button'><input type='button' class='button' value='Local timezone' onclick='javascript:setTimezone()' /></span></td></tr>
  <tr><td><b>CSS URL </b>&nbsp;</td><td><input type="text" size="40" name="user_css" value="$css" /><br />("None" for disabling CSS)</td></tr>
EXTRA;
        $logout="<span class='button'><input type='submit' class='button' name='logout' value='"._("logout")."' /></span> &nbsp;";

        $show_join_agreement = false;
        if (!empty($DBInfo->use_agreement)) {
            if ($user->info['join_agreement'] != 'agree')
                $show_join_agreement = true;
            if (!empty($DBInfo->agreement_version)) {
                if ($user->info['join_agreement_version'] != $DBInfo->agreement_version)
                    $show_join_agreement = true;
            }
        }

        if ($show_join_agreement) {
            $extra.= _joinagreement_form();
            $accept = _("Accept agreement");
            $extra.= <<<FORM
<div class='check-agreement'><p><input type='checkbox' name='joinagreement' />$accept</p>
FORM;
        }

    } else if ($user->id == 'Anonymous') {
        $button=_("Make profile");
        $email_btn=_("Mail");
    }
    $script = '';
    if ($tz_offset === '' and $jscript)
        $script=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
setTimezone();
/*]]>*/
</script>
EOF;

    $passwd = !empty($passwd) ? $passwd : '';
    $passwd_inp = '';
    if (($joinagree and empty($DBInfo->use_safelogin)) or $button==_("Save")) {
        if ($user->id == 'Anonymous' or !empty($user->info['password']))
            $passwd_inp=<<<PASS
  <tr>
     <th>$passwd_btn&nbsp;</th><td><input type="password" size="15" maxlength="$pw_length" name="password" value="$passwd" />
PASS;

    } else {
        $onsubmit='';
        $passwd_hidden='';
    }
    $emailpasswd = '';
    if (!$login_only && $button==_("Make profile")) {
        if (empty($options['agreement']) and !empty($DBInfo->use_sendmail)) {
            $button2=_("E-mail new password");
            $emailpasswd=
                "<span class='button'><input type=\"submit\" class='button' name=\"login\" value=\"$button2\" /></span>\n";

        } else if (isset($options['login_id']) and !empty($DBInfo->use_agreement) and empty($options['joinagreement'])) {
            $form = <<<FORM
<div>
<form method="post" action="$url">
<div>
<input type="hidden" name="action" value="userform" />

FORM;
            $form.= "<input type='hidden' name='login_id' ";
            if (isset($options['login_id'][0])) {
                $login_id = _html_escape($options['login_id']);
                $form.= "value=\"$login_id\"";
            }
            $form.= " />";

            $form.= _joinagreement_form();
            $accept = _("Accept agreement");
            $form.= <<<FORM
<div class='check-agreement'><p><input type='checkbox' name='joinagreement' />$accept</p>
<span class="button"><input type="submit" class="button" name="login" value="$button" /></span>
</div>
</div>
</form>
</div>

FORM;
            return $form;
        }
    }
    if ($user->id == 'Anonymous' && !empty($DBInfo->anonymous_friendly)) {
        $verifiedemail = isset($options['verifyemail']) ? $options['verifyemail'] :
                    (isset($user->verified_email) ? $user->verified_email : '');
        $button3 =_("Verify E-mail address");
        $button4 =_("Remove");
        $remove = '';
        if ($verifiedemail)
            $remove = "<span class='button'><input type='submit' class='button' name='emailreset' value='$button4' /></span>";
        $emailverify = <<<EOF
          $sep
          <tr><th>$email_btn&nbsp;</th><td><input type='text' size='40' name='verifyemail' value="$verifiedemail" /></td></tr>
          <tr><td></td><td>
          <span class='button'><input type="submit" class='button' name="verify" value="$button3" /></span>
          $remove
          </td></tr>
EOF;
    }
    $id_btn=_("ID");
    $sep1 = '';
    if (!empty($openid_form) or !empty($login)) $sep1=$sep;
        $all = <<<EOF
$login
$jscript
EOF;

    if (!$login_only || $user->id != 'Anonymous')
        $all.= <<<EOF
<div>
<form method="post" action="$url"$onsubmit>
<div>
<input type="hidden" name="action" value="userform" />
<table border="0">
$openid_form
$sep1
  <tr><th>$id_btn&nbsp;</th><td>$idform</td></tr>
    $passwd_inp
    $passwd_hidden
    $again
    $extra
  <tr><td></td><td>
    <span class="button"><input type="submit" class="button" name="login" value="$button" /></span> &nbsp;
    $emailpasswd
    $emailverify
    $logout
  </td></tr>
</table>
</div>
</form>
</div>
$script
EOF;
    else if ($login_only)
        $all.= <<<EOF
<div>
<form method="post" action="$url"$onsubmit>
<div>
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td></td><td>
    $emailverify
  </td></tr>
</table>
</div>
</form>
</div>
EOF;

    return $all;
}

// vim:et:sts=4:sw=4:
