<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RecentChanges plugin for the MoniWiki
//
// $Id$

function do_RecentChanges($formatter,$options='') {
  $options['trail']='';
  $options['css_url']=$formatter->url_prefix.'/css/sidebar.css';
  $formatter->send_header("",$options);
  print "<div id='wikiBody'>";
  print macro_RecentChanges($formatter,'nobookmark,moztab',array('target'=>'_content'));
  print "</div></body></html>";
  return;
}

function macro_RecentChanges($formatter,$value='',$options='') {
  global $DBInfo;
  define(MAXSIZE,10000);
  define(DEFSIZE,6000);
  $checknew=1;

  $template_bra="";
  $template=
  '$out.= "$icon&nbsp;&nbsp;$title $date . . . . $user $count $extra<br />\n";';
  $template_cat="";
  $use_day=1;

  if ($options['target']) $target="target='$options[target]'";

  #$date_fmt='D d M Y';
  $date_fmt=$DBInfo->date_fmt_rc;

  preg_match("/(\d+)?(?:\s*,\s*)?(.*)?$/",$value,$match);
  if ($match) {
    $size=(int) $match[1];
    $args=explode(",",$match[2]);

    if (preg_match("/^[\s\/\-:aABdDFgGhHiIjmMOrSTY]+$/",$args[0]))
      $date_fmt=$args[0];

    if (in_array ("quick", $args)) $quick=1;
    if (in_array ("nonew", $args)) $checknew=0;
    if (in_array ("showhost", $args)) $showhost=1;
    if (in_array ("comment", $args)) $comment=1;
    if (in_array ("nobookmark", $args)) $nobookmark=1;
    if (!in_array ("noperma", $args)) $perma_icon=$formatter->perma_icon;
    if (in_array ("simple", $args)) {
      $use_day=0;
      $template=
  '$out.= "$icon&nbsp;&nbsp;$title @ $day $date by $user $count $extra<br />\n";';
    }
    if (in_array ("moztab", $args)) {
      $use_day=1;
      $template=
  '$out.= "<li>$title $date</li>\n";';
    }
    if (in_array ("table", $args)) {
      $bra="<table border='0' cellpadding='0' cellspading='0' width='100%'>";
      $template=
  '$out.= "<tr><td nowrap=\'nowrap\' width=\'2%\'>$icon</td><td width=\'40%\'>$title</td><td width=\'15%\'>$date</td><td>$user $count $extra</td></tr>\n";';
      $cat="</table>";
      $cat0="";
    }
  }
  if ($size > MAXSIZE) $size=DEFSIZE;

  $user=new User(); # retrive user info
  if ($user->id != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $udb->checkUser(&$user);
  }

  if ($user->id == 'Anonymous')
    $bookmark= $user->bookmark;
  else {
    $bookmark= $user->info['bookmark'];
  }
  if (!$bookmark) $bookmark=time();

  if ($quick)
    $lines= $DBInfo->editlog_raw_lines($size,1);
  else
    $lines= $DBInfo->editlog_raw_lines($size);
    
  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  foreach ($lines as $line) {
    $parts= explode("\t", $line,3);
    $page_key= $parts[0];
    $ed_time= $parts[2];

    $day = date('Ymd', $ed_time);
    if ($day != $ratchet_day) {
      $ratchet_day = $day;
      unset($logs);
    }

    if ($editcount[$page_key]) {
      if ($logs[$page_key]) {
        $editcount[$page_key]++;
        continue;
      }
      continue;
    }
    $editcount[$page_key]= 1;
    $logs[$page_key]= 1;
  }
  unset($logs);

  $out="";
  $ratchet_day= FALSE;
  $br="";
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_key=$parts[0];

    if ($logs[$page_key]) continue;

    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $log= stripslashes($parts[5]);
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    if ($formatter->group) {
      if (!preg_match("/^($formatter->group)(.*)$/",$page_name,$match)) continue;
      $title=$match[2];
    } else {
      $group='';
      if ($p=strpos($page_name,'~')) {
        $title=substr($page_name,$p+1);
        $group=' ('.substr($page_name,0,$p).')';
      } else
        $title=$page_name;
    }

    $day = date('Y-m-d', $ed_time);
    if ($use_day and $day != $ratchet_day) {
      $tag=str_replace('-','',$day);
      $perma="<a name='$tag'></a><a class='perma' href='#$tag'>$perma_icon</a>";
      $out.=$cat0;
      $out.=sprintf("%s<font class='rc-date' size='+1'>%s </font>$perma<font class='rc-bookmark' size='-1'>",
            $br, date($date_fmt, $ed_time));
      if (!$nobookmark)
        $out.='['.$formatter->link_tag($formatter->page->urlname,
                                 "?action=bookmark&amp;time=$ed_time",
                                 _("set bookmark"))."]</font><br />\n";
      $ratchet_day = $day;
      $br="<br />";
      $out.=$bra;
      $cat0=$cat;
    } else
      $day=$formatter->link_to("?action=bookmark&amp;time=$ed_time",$day);

    $pageurl=_rawurlencode($page_name);

    if (!$DBInfo->hasPage($page_name))
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon[del]);
    else if ($ed_time > $bookmark) {
      $icon= $formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon[updated]);
      if ($checknew) {
        $p= new WikiPage($page_name);
        $v= $p->get_rev($bookmark);
        if (!$v)
          $icon=
            $formatter->link_tag($pageurl,"?action=info",$formatter->icon['new']);
      }
    } else
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon[diff]);

    #$title= preg_replace("/((?<=[a-z0-9])[A-Z][a-z0-9])/"," \\1",$page_name);
    $title= get_title($title).$group;
    $title=htmlspecialchars($title);
    $title= $formatter->link_tag($pageurl,"",$title,$target);

    if (! empty($DBInfo->changed_time_fmt))
      $date= date($DBInfo->changed_time_fmt, $ed_time);

    if ($DBInfo->show_hosts) {
      if ($showhost && $user == 'Anonymous')
        $user= $addr;
      else {
        if ($DBInfo->hasPage($user)) {
          $user= $formatter->link_tag(_rawurlencode($user),"",$user);
        } else
          $user= $user;
      }
    }
    $count=""; $extra="";
    if ($editcount[$page_key] > 1)
      $count=" [".$editcount[$page_key]." changes]";
    if ($comment && $log)
      $extra="&nbsp; &nbsp; &nbsp; <font size='-1'>$log</font>";

    eval($template);

    $logs[$page_key]= 1;
  }
  return $out.$cat0;
}
// vim:et:sts=2:
?>
