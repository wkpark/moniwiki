<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Theme plugin for the MoniWiki
//
// $Id: theme.php,v 1.11 2010/04/17 09:20:24 wkpark Exp $
//

function do_theme($formatter,$options) {
  global $DBInfo;

  $theme = '';
  if (preg_match('/^[a-zA-Z0-9_-]+$/', $options['theme']))
        $theme = $options['theme'];

  if ($options['clear']) {
    if ($options['id']=='Anonymous') {
      #header("Set-Cookie: MONI_THEME=dummy; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname());
      #header("Set-Cookie: MONI_CSS=dummy; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname());
      setcookie('MONI_THEME','dummy',time()-60*60*24*30,get_scriptname());
      setcookie('MONI_CSS','dummy',time()-60*60*24*30,get_scriptname());
      $cleared=1;
      //$options['css_url']='';
      //$options['theme']='';
    } else {
      # save profile
      $udb=$DBInfo->udb;
      $userinfo=$udb->getUser($options['id']);
      $userinfo->info['theme']="";
      $userinfo->info['css_url']="";
      $udb->saveUser($userinfo);
    }
    $msg="== "._("Theme cleared. Goto UserPreferences.")." ==";
  }
  else if (!empty($theme)) {
    $themedir=$formatter->themedir;
    if (file_exists($themedir."/header.php")) { # check
      $options['css_url']=$formatter->themeurl."/css/default.css";
      if ($options['save'] and $options['id']=='Anonymous') {
        setcookie("MONI_THEME",$theme,time()+60*60*24*30,
                               get_scriptname());
        setcookie("MONI_CSS",$options['css_url'],time()+60*60*24*30,
                               get_scriptname());
        $title=_("Theme is changed");
        $msg="Goto ".$formatter->link_repl("UserPreferences");
      } else if ($options['save'] and $options['id']!='Anonymous') {
        # save profile
        $udb=$DBInfo->udb;
        $userinfo=$udb->getUser($options['id']);
        $userinfo->info['theme'] = $theme;
        $userinfo->info['css_url']=$options['css_url'];
        $udb->saveUser($userinfo);
        $msg="Goto ".$formatter->link_repl("UserPreferences");
      } else {
        $title="";
        $want = _("Do you want to apply this theme ?");
        $btn = _("OK");
        $msg=<<<FORM
<form method='post'>
<input type='hidden' name='action' value='theme' />
<input type='hidden' name='theme' value="$theme" />
$want <input type='submit' name='save' value='$btn' /> &nbsp;
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
  if ($DBInfo->theme_css) return _("Theme disabled !");
  $msg = _("Supported themes");
  $out="
<form method='get'>
<input type='hidden' name='action' value='theme' />
  <b>$msg</b>&nbsp;
<select name='theme'>
";
  $themes=array();
  $path=!empty($DBInfo->themedir) ? $DBInfo->themedir: '.';
  $handle = @opendir("$path/theme");
  if (is_resource($handle)) {
    while ($file = readdir($handle)) {
      if (!in_array($file,array('.','..','RCS','CVS')) and is_dir("$path/theme/".$file) and
        file_exists($path.'/theme/'.$file.'/header.php'))
          $themes[]= $file;
    }
  }

  $out.="<option value=''>"._("-- Select --")."</option>\n";
  foreach ($themes as $item)
     $out.="<option value='$item'>$item</option>\n";
  $btn = _("Show selected theme");
  $btn2 = _("Clear cookie");
  $out.="
    </select>&nbsp; &nbsp; &nbsp;
    <span class='button'><input type='submit' class='button' name='show' value='$btn' /></span> &nbsp;
    <span class='button'><input type='submit' class='button' name='clear' value='$btn2' /></span> &nbsp;
</form>
";
  return $out;
}

// vim:et:sts=2:
?>
