<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a subscribe action plugin for the MoniWiki
//
// $Id$

function do_subscribe($formatter,$options) {
  global $DBInfo;

  if (!$DBInfo->notify) {
    $options['title']=_("EmailNotification is not activated");
    $options['msg']=_("If you want to subscribe this page please contact the WikiMaster to activate the e-mail notification");
    do_invalid($formatter,$options);
  }

  if ($options['id'] != 'Anonymous') {
    $udb=new UserDB($DBInfo);
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

  $pages=explode("\t",$userinfo->info['subscribed_pages']);
  if (!in_array($options['page'],$pages)) $pages[]=$options['page'];
  $page_lists=join("\n",$pages);

  $title = sprintf(_("Do you want to subscribe \"%s\" ?"), $options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<form method='post'>
<table border='0'><tr>
<th>Subscribe pages:</th><td><textarea name='subscribed_pages' cols='30' rows='5' value='' />$page_lists</textarea></td></tr>
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
