<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// css action plugin for the MoniWiki
//
// $Id$

function do_css($formatter,$options) {
  global $DBInfo;
  global $HTTP_COOKIE_VARS;

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
      $options['css_url']=($DBInfo->themeurl ? $DBInfo->themeurl:$DBInfo->url_prefix)."/theme/$theme/css/default.css";
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
    $msg=<<<FORM
<form method='post'>
<input type='hidden' name='action' value='css' />
<input type='hidden' name='user_css' value='$options[css_url]' />
Did you want to apply this CSS ? <input type='submit' name='save' value='OK' /> &nbsp;
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
  $formatter->send_page("Back to UserPreferences");
  $formatter->send_footer("",$options);
}

function macro_Css($formatter="") {
  global $DBInfo;
  if ($DBInfo->theme_css) return _("CSS disabled !");
  $out="
<form method='post'>
<input type='hidden' name='action' value='css' />
  <b>Select a CSS</b>&nbsp;
<select name='user_css'>
";
  $handle = opendir($DBInfo->css_dir);
  $css=array();
  while ($file = readdir($handle)) {
     if (preg_match("/^[^_\.].*\.css$/i", $file,$match))
        $css[]= $file;
  }

  foreach ($css as $item)
     $out.="<option value='$DBInfo->url_prefix/$DBInfo->css_dir/$item'>$item</option>\n";

  $out.="
    </select>&nbsp; &nbsp; &nbsp;
    <input type='submit' name='show' value='Change CSS' /> &nbsp;";

  $out.="
    <input type='submit' name='clear' value='Clear cookie' /> &nbsp;";

  $out.="</form>\n";
  return $out;
}

// vim:et:sts=2:
?>
