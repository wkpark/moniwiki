<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a quicklinks macro plugin for the MoniWiki
//
// $Id: quicklinks.php,v 1.2 2008/12/10 09:59:49 wkpark Exp $

function do_quicklinks($formatter,$options) {
  global $DBInfo;

  if ($options['id'] != 'Anonymous') {
    $udb=&$DBInfo->udb;
    $userinfo=$udb->getUser($options['id']);
    $email=$userinfo->info['email'];
  } else {
    $title = _("Please login or make your ID.");
  }

  if ($options['id'] == 'Anonymous') {
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("== "._("Goto UserPreferences")." ==\n".
    _("If you want to customize your quicklinks, just make your ID and register your email address in the UserPreferences."));
    $formatter->send_footer();

    return;
  }

  if (isset($options['quick_links'])) {
    $pages=preg_replace("/\n\s*/","\n",$options['quick_links']);
    $pages=preg_replace("/\s*\n/","\n",$pages);
    $pages=explode("\n",$pages);
    $pages=array_unique ($pages);
    $page_list=join("\t",$pages);
    $userinfo->info['quicklinks']=$page_list;
    $udb->saveUser($userinfo);

    $title = _("QucikLinks are updated.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_page("Goto [$options[page]]\n");
    $formatter->send_footer();
    return;
  }

  $pages=explode("\t",$userinfo->info['quicklinks']);
  if (!in_array($options['page'],$pages)) $pages[]=$options['page'];
  $page_lists=join("\n",$pages);

  $title = sprintf(_("Do you want to customize your quicklinks ?"), $options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<form method='post'>
<table border='0'><tr>
<th>QuickLiks:</th><td><textarea name='quick_links' cols='30' rows='5' value='' />$page_lists</textarea></td></tr>
<tr><td></td><td>
    <input type='hidden' name='action' value='quicklinks' />
    <input type='submit' value='Update' />
</td></tr>
</table>
    </form>";
  $formatter->send_footer("",$options);
}

?>
