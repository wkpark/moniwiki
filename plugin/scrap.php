<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a scrap action plugin for the MoniWiki
//
// $Id$

function macro_Scrap($formatter,$value,$options) {
  global $DBInfo;

  $user=new User(); # get cookie
  if ($user->id != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $udb->checkUser($user);
  }
  if ($user->id == 'Anonymous') {
    return '[[Scrap]]';
  }
  $userinfo=$udb->getUser($user->id);
  $pages=explode("\t",$userinfo->info['scrapped_pages']);
  if (!in_array($options['page'],$pages)) $pages[]=$options['page'];
  $out='';
  foreach ($pages as $p) {
    if ($DBInfo->hasPage($p))
      $out.='<li>'.($formatter->link_tag(_urlencode($p),'',$p)).'</li>';
    else if ($p)
      $out.=substr($formatter->macro_repl('PageList',$p),4,-6);
  }
  return '<ul>'.$out.'</ul>';
}


function do_scrap($formatter,$options) {
  global $DBInfo;

  if ($options['id'] == 'Anonymous') {
    $title = _("Please login or make your ID.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("== "._("Goto UserPreferences")." ==\n");
    $formatter->send_footer();

    return;
  }

  $udb=new UserDB($DBInfo);
  $userinfo=$udb->getUser($options['id']);
  if (isset($options['scrapped_pages'])) {
    $pages=preg_replace("/\n\s*/","\n",$options['scrapped_pages']);
    $pages=preg_replace("/\s*\n/","\n",$pages);
    $pages=explode("\n",$pages);
    $pages=array_unique ($pages);
    $page_list=join("\t",$pages);
    $userinfo->info['scrapped_pages']=$page_list;
    $udb->saveUser($userinfo);

    $title = _("Scrap lists updated.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("Goto [$options[page]]\n");
    $formatter->send_footer();
    return;
  }

  $pages=explode("\t",$userinfo->info['scrapped_pages']);
  if (!in_array($options['page'],$pages)) $pages[]=$options['page'];
  $page_lists=join("\n",$pages);

  $title = sprintf(_("Do you want to scrap \"%s\" ?"), $options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<form method='post'>
<table border='0'><tr>
<th>Scrap pages:</th><td><textarea name='scrapped_pages' cols='30' rows='5' value='' />$page_lists</textarea></td></tr>
<tr><td></td><td>
    <input type='hidden' name='action' value='scrap' />
    <input type='submit' value='Scrap' />
</td></tr>
</table>
    </form>";
  $formatter->send_footer("",$options);
}

// vim:et:sts=4
?>
