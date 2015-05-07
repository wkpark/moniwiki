<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// css action plugin for the MoniWiki
//
// $Id: css.php,v 1.10 2010/04/17 12:07:26 wkpark Exp $

function do_css($formatter,$options) {
  global $DBInfo;
  global $HTTP_COOKIE_VARS;

  $title = '';
  if ($options['clear']) {
    if ($options['id']=='Anonymous') {
      header("Set-Cookie: MONI_CSS=dummy; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname());
      $options['css_url']="";
    } else {
      # save profile
      $udb=&$DBInfo->udb;
      $userinfo=$udb->getUser($options['id']);
      $userinfo->info['css_url']="";
      $udb->saveUser($userinfo);
    }
    if (!empty($options['theme'])) {
      $theme = $options['theme'];
      $options['css_url']=(!empty($DBInfo->themeurl) ? $DBInfo->themeurl:$DBInfo->url_prefix)."/theme/$theme/css/default.css";
    }
  } else if ($options['save'] && $options['id']=="Anonymous" && isset($options['user_css'])) {
    setcookie("MONI_CSS",$options['user_css'],time()+60*60*24*30,get_scriptname());
    # set the fake cookie
    #$HTTP_COOKIE_VARS['MONI_CSS']=$options['user_css'];
    $title="CSS Changed";
    $options['css_url']=$options['user_css'];
    $msg=_("Back to UserPreferences");
  } else if ($options['save'] && $options[id] != "Anonymous" && isset($options['user_css'])) {
    # save profile
    $udb=&$DBInfo->udb;
    $userinfo=$udb->getUser($options['id']);
    $userinfo->info['css_url']=$options['user_css'];
    $udb->saveUser($userinfo);
    $options['css_url']=$options['user_css'];
    $msg=_("Back to UserPreferences");
  } else {
    $title="";
    $options['css_url']=$options['user_css'];
    $want = _("Do you want to apply selected CSS ?");
    $btn = _("OK");
    $css_url = _html_escape($options['css_url']);
    $msg=<<<FORM
<form method='post'>
<input type='hidden' name='action' value='css' />
<input type='hidden' name='user_css' value="$css_url" />
$want <span class='button'><input type='submit' class='button' name='save' value='$btn' /></span> &nbsp;
</form>
FORM;
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    print $msg;

    $formatter->send_footer("",$options);
    return;
  }
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $formatter->send_page(_("Back to UserPreferences"));
  $formatter->send_footer("",$options);
}

function macro_Css($formatter="") {
  global $DBInfo;
  if ($DBInfo->theme_css) return _("CSS disabled !");
  $select = _("Supported CSS styles");
  $out="
<form method='post'>
<input type='hidden' name='action' value='css' />
  <b>$select</b>&nbsp;
<select name='user_css'>
";
  $handle = opendir($DBInfo->css_dir);
  $css=array();
  while ($file = readdir($handle)) {
     if (preg_match("/^[^_\.].*\.css$/i", $file,$match))
        $css[]= $file;
  }

  $out.="<option value=''>"._("-- Select --")."</option>\n";
  foreach ($css as $item)
     $out.="<option value='$DBInfo->url_prefix/$DBInfo->css_dir/$item'>$item</option>\n";

  $btn = _("Show selected style");
  $btn2 = _("Clear cookie");
  $out.="
    </select>&nbsp; &nbsp; &nbsp;
    <span class='button'><input type='submit' class='button' name='show' value='$btn' /></span> &nbsp;";

  $out.="
    <span class='button'><input type='submit' class='button' name='clear' value='$btn2' /></span> &nbsp;";

  $out.="</form>\n";
  return $out;
}

// vim:et:sts=2:
?>
