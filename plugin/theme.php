<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Theme plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$
//

function do_theme($formatter,$options) {
  global $DBInfo;
  if ($options[clear]) {
    if ($options[id]=='Anonymous') {
    header("Set-Cookie: MONI_THEME=dummy; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname());
    } else {
      # save profile
      $udb=new UserDB($DBInfo);
      $userinfo=$udb->getUser($options[id]);
      $userinfo->info[theme]="";
      $userinfo->info[css_url]="";
      $udb->saveUser($userinfo);
    }
    $msg="== "._("Theme cleared. Goto UserPreferences.")." ==";
  }
  else if ($options[theme]) {
    $themedir="theme/$options[theme]";
    if (file_exists($themedir."/header.php")) { # check
      $options[css_url]=$DBInfo->url_prefix."/$themedir/css/default.css";
      if ($options[save] and $options[id]=='Anonymous') {
        setcookie("MONI_THEME",$options[theme],time()+60*60*24*30,
                               get_scriptname());
        setcookie("MONI_CSS",$options[css_url],time()+60*60*24*30,
                               get_scriptname());
        $title="Theme is changed";
        $msg="OK";
      } else if ($options[save] and $options[id]!='Anonymous') {
        # save profile
        $udb=new UserDB($DBInfo);
        $userinfo=$udb->getUser($options[id]);
        $userinfo->info[theme]=$options[theme];
        $userinfo->info[css_url]=$options[css_url];
        $udb->saveUser($userinfo);
      } else {
        $title="";
        $msg=<<<FORM
<form method='post'>
<input type='hidden' name='action' value='theme' />
<input type='hidden' name='theme' value='$options[theme]' />
Did you want to apply this theme ? <input type='submit' name='save' value='OK' /> &nbsp;
</form>

FORM;
      }
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      print $msg;
      
      $formatter->send_footer("",$options);
      return;
    }
  } else
    $msg="== "._("Please select a theme properly.")." ==";
  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);
  $formatter->send_page($msg);
  $formatter->send_footer("",$options);
  return;
}

function macro_theme($formatter,$value) {
  global $DBInfo;
  $out="
<form method='get'>
<input type='hidden' name='action' value='theme' />
  <b>Supported theme lists</b>&nbsp;
<select name='theme'>
";
  $handle = opendir("theme");
  $themes=array();
  while ($file = readdir($handle)) {
     if ($file != '.' and $file !='..' and is_dir("theme/".$file))
        $themes[]= $file;
  }

  $out.="<option value=''>-- Select --</option>\n";
  foreach ($themes as $item)
     $out.="<option value='$item'>$item</option>\n";

  $out.="
    </select>&nbsp; &nbsp; &nbsp;
    <input type='submit' name='show' value='Show this theme' /> &nbsp;
    <input type='submit' name='clear' value='Clear theme cookie' /> &nbsp;
</form>
";
  return $out;
}

?>
