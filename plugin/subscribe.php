<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a subscribe action plugin for the MoniWiki
//
// $Id: subscribe.php,v 1.6 2008/12/10 09:59:49 wkpark Exp $


function macro_Subscribe($formatter,$value,$options=array()) {
  global $DBInfo;

  $user=$DBInfo->user; # get cookie

  if ($user->id != 'Anonymous') {
    $udb=&$DBInfo->udb;
    $userinfo=$udb->getUser($user->id);
    $email=$userinfo->info['email'];
  } else {
    $title = _("Please login or make your ID.");
    return $title;
  }

  if (!$userinfo->info['subscribed_pages'])
    return _("You did'nt subscribed any pages yet.");
  #$page_list=_preg_search_escape($userinfo->info['subscribed_pages']);
  $page_list=$userinfo->info['subscribed_pages'];
  if (!trim($page_list))
    return _("You did'nt subscribed any pages yet.");
  $page_lists=explode("\t",$page_list);
  $page_rule='^'.join("$|^",$page_lists).'$';

  $out= macro_TitleSearch($formatter,$page_rule,$ret);
  if ($ret['hits'] > 0)
    return '<div class="subscribePages">'.$out.'</div>';
  return _("No subscribed pages found.");
}

function do_subscribe($formatter,$options) {
  global $DBInfo;

  if (!$DBInfo->notify and 0) { # XXX
    $options['title']=_("EmailNotification is not activated");
    $options['msg']=_("If you want to subscribe this page please contact the WikiMaster to activate the e-mail notification");
    do_invalid($formatter,$options);
    return;
  }

  if ($options['id'] != 'Anonymous') {
    $udb=&$DBInfo->udb;
    $userinfo=$udb->getUser($options['id']);
    $email=$userinfo->info['email'];
    #$subs=$udb->getPageSubscribers($options[page]);
    if (!$email) $title = _("Please enter your email address first.");
  } else {
    $title = _("Please login or make your ID.");
  }

  if ($options['id'] == 'Anonymous' or !$email) {
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("== "._("Goto UserPreferences")." ==\n".
    _("If you want to subscribe this page, just make your ID and register your email address in the UserPreferences."));
    $formatter->send_footer();

    return;
  }

  if (isset($options['subscribed_pages'])) {
    $pages=preg_replace("/\n\s*/","\n",$options['subscribed_pages']);
    $pages=preg_replace("/\s*\n/","\n",$pages);
    $pages=explode("\n",$pages);
    $pages=array_unique ($pages);
    $page_list=join("\t",$pages);
    $userinfo->info['subscribed_pages']=$page_list;
    $udb->saveUser($userinfo);

    $title = _("Subscribe lists updated.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("Goto [$options[page]]\n");
    $formatter->send_footer();
    return;
  }

  $plist=_preg_search_escape($userinfo->info['subscribed_pages']);
  $check=1;
  if (trim($plist)) {
    $plists=explode("\t",$plist);
    $prule='^'.join("$|^",$plists).'$';
    if (preg_match('/('.$prule.')/',_preg_search_escape($options['page']))) {
      $title = sprintf(_("\"%s\" is already subscribed."), $options['page']);
      $check=0;
    }
  }
  $pages=explode("\t",$userinfo->info['subscribed_pages']);
  if ($check) {
    if (!in_array($options['page'],$pages)) {
      $pages[]=$options['page'];
    }
    $title = sprintf(_("Do you want to subscribe \"%s\" ?"), $options['page']);
  }
  $page_lists=join("\n",$pages);

  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $msg=_("Subscribed pages");
  print "<form method='post'>
<table border='0'><tr>
<th>$msg :</th><td><textarea name='subscribed_pages' cols='30' rows='5' value='' />$page_lists</textarea></td></tr>
<tr><td></td><td>
    <input type='hidden' name='action' value='subscribe' />
    <input type='submit' value='Subscribe' />
</td></tr>
</table>
    </form>";
#  $formatter->send_page();
  $formatter->send_footer("",$options);
}

?>
