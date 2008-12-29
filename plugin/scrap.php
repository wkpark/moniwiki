<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a scrap action plugin for the MoniWiki
//
// $Id$

function macro_Scrap($formatter,$value='',$options=array()) {
  global $DBInfo;

  $user=&$DBInfo->user; # get cookie
  if ($user->id == 'Anonymous') return '';

  $userinfo=$DBInfo->udb->getUser($user->id);
  $pages=explode("\t",$userinfo->info['scrapped_pages']);
  if (!in_array($options['page'],$pages)) $pages[]=$options['page'];
  $out='';
  foreach ($pages as $p) {
    if ($DBInfo->hasPage($p))
      $out.='<li>'.($formatter->link_tag(_urlencode($p),'',$p)).'</li>';
    else if (!empty($p)) {
      $list = $formatter->macro_repl('PageList',$p,array('rawre'=>1));
      if (empty($list))
      	$out.=substr($list,4,-6);
    }
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

  $udb=&$DBInfo->udb;
  $userinfo=$udb->getUser($options['id']);
  if (isset($options['scrapped_pages']) or (empty($DBInfo->scrap_manual) and empty($options['manual']))) {
    $pages = array();
    if (isset($options['scrapped_pages'])) {
        $pages = preg_replace("/\n\s*/","\n",$options['scrapped_pages']);
        $pages = preg_replace("/\s*\n/","\n",$pages);
        $pages = explode("\n",$pages);
        $pages = array_unique ($pages);
        $title = _("Scrap lists updated.");
    } else {
        $pages = explode("\t",$userinfo->info['scrapped_pages']);
        if (!empty($options['unscrap'])) {
            $tmp = array_flip($pages);
            if (isset($tmp[$formatter->page->name]))
                unset($tmp[$formatter->page->name]);
            $pages = array_flip($tmp);
            $title = sprintf(_("\"%s\" is unscrapped."), $formatter->page->name);
        } else {
            $pages[] = $formatter->page->name;
            $title = sprintf(_("\"%s\" is scrapped."), $formatter->page->name);
        }
        $pages = array_unique ($pages);
    }
    $page_list = join("\t",$pages);
    $userinfo->info['scrapped_pages'] = $page_list;
    $udb->saveUser($userinfo);

    if ($DBInfo->use_refresh) {
      $sec = $DBInfo->use_refresh - 1;
      $lnk = $formatter->link_url($formatter->page->urlname,'?action=show');
      $myrefresh = 'Refresh: '.$sec.'; url='.qualifiedURL($lnk);
    }

    $formatter->send_header($myrefresh,$options);
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
  $msg = _("Scrapped pages");
  print "<form method='post'>
<table border='0'><tr>
<th>$msg :</th><td><textarea name='scrapped_pages' cols='40' rows='5' value='' />$page_lists</textarea></td></tr>
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
