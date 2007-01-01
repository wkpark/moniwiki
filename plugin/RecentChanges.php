<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
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

define('RC_MAX_DAYS',30);
define('RC_MAX_ITEMS',200);
define('RC_DEFAULT_DAYS',7);

  $checknew=1;

  $template_bra="";
  $template=
  '$out.= "$icon&nbsp;&nbsp;$title $date . . . . $user $count $extra<br />\n";';
  $template_cat="";
  $use_day=1;

  if ($options['target']) $target="target='$options[target]'";

  // $date_fmt='D d M Y';
  $date_fmt=$DBInfo->date_fmt_rc;
  $days=$DBInfo->rc_days ? $DBInfo->rc_days:RC_DEFAULT_DAYS;
  $perma_icon=$formatter->perma_icon;

  $args=explode(',',$value);

  // first arg assumed to be a date fmt arg
  if (preg_match("/^[\s\/\-:aABdDFgGhHiIjmMOrSTY]+$/",$args[0]))
    $date_fmt=$args[0];

  foreach ($args as $arg) {
    $arg=trim($arg);
    if (($p=strpos($arg,'='))!==false) {
      $k=substr($arg,0,$p);
      $v=substr($arg,$p+1);
      if ($k=='item') $opts['items']=min((int)$v,RC_MAX_ITEMS);
      else if ($k=='days') $days=min(abs($v),RC_MAX_DAYS);
      else if ($k=='ago') $opts['ago']=abs($v);
    } else {
      if ($arg =="quick") $opts['quick']=1;
      else if ($arg=="nonew") $checknew=0;
      else if ($arg=="showhost") $showhost=1;
      else if ($arg=="comment") $comment=1;
      else if ($arg=="nobookmark") $nobookmark=1;
      else if ($arg=="noperma") $perma_icon='';
      else if ($arg=="button") $button=1;
      else if ($arg=="timesago") $timesago=1;
      else if ($arg=="daysago") $use_daysago=1;
      else if ($arg=="simple") {
        $use_day=0;
        $template=
  '$out.= "$icon&nbsp;&nbsp;$title @ $day $date by $user $count $extra<br />\n";';
      } else if ($arg=="moztab") {
        $use_day=1;
        $template= '$out.= "<li>$title $date</li>\n";';
      } else if ($arg=="table") {
        $bra="<table border='0' cellpadding='0' cellspacing='0' width='100%'>";
        $template=
  '$out.= "<tr><td style=\'white-space:nowrap;width:2%\'>$icon</td><td style=\'width:40%\'>$title</td><td style=\'width:15%\'>$date</td><td>$user $count $extra</td></tr>\n";';
        $cat="</table>";
        $cat0="";
      }
    }
  }
  // override days
  $days=$_GET['days'] ? min(abs($_GET['days']),RC_MAX_DAYS):$days;

  // override ago
  if ($_GET['ago'])
    $opts['ago']=$_GET['ago'] ? abs($_GET['ago']):$opts['ago'];

  // daysago
  $daysago='&amp;days='.$days;
  $daysago=$opts['ago'] ? $daysago.'&amp;ago='.$opts['ago']:$daysago;
      

  $user=new User(); # retrive user info
  if ($user->id != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $udb->checkUser($user);
  }
  if ($user->id != 'Anonymous') {
    $bookmark= $user->info['bookmark'];
    $tz_offset= $user->info['tz_offset'];
  } else {
    $bookmark= $user->bookmark;
  }
  if ($tz_offset == '') {
    $tz_offset=date("Z");
    $tz_offset;
  }

  if (!$bookmark) $bookmark=time();

  $time_current= time();
  $secs_per_day= 60*60*24;
  //$time_cutoff= $time_current - ($days * $secs_per_day);
  $lines= $DBInfo->editlog_raw_lines($days,$opts);

  // make a daysago button
  if ($use_daysago or $_GET['ago']) {
    $msg[0]=_("Show changes for ");
    $agolist=array(-$days,$days,2*$days,3*$days);
    $btn=array();

    $arg='days='.$days.'&amp;ago';
    $msg[1]=_("days ago");

    foreach ($agolist as $d) {
      $d+=$opts['ago'];
      if ($d<=0) continue;
      $link=
        $formatter->link_tag($formatter->page_urlname,"?$arg=".$d,$d);
      $btn[]=$link;
    }
    #if (sizeof($lines)==0) $btn=array_slice($btn,0,1);

    $btn[]=$formatter->link_tag($formatter->page_urlname,"?$arg=...",'...',
      'onClick="return daysago(this)"');
    $script="<script type='text/javascript' src='$DBInfo->url_prefix/local/rc.js' ></script>";
    $btnlist=$msg[0].' <ul><li>'.implode("</li>\n<li>",$btn).
      '</li></ul> '.$msg[1];
    $btnlist=$script."<div class='rc-button'>\n".$btnlist."</div>\n";
  }

  foreach ($lines as $line) {
    $parts= explode("\t", $line,6);
    $page_key= $parts[0];
    $ed_time= $parts[2];

    $day = gmdate('Ymd', $ed_time+$tz_offset);
    if ($day != $ratchet_day) {
      $ratchet_day = $day;
      unset($logs);
    }

    if ($editcount[$page_key]) {
      if ($logs[$page_key]) {
        $editcount[$page_key]++;
        #$editors[$page_key].=':'.$parts[4];
        continue;
      }
      continue;
    }
    $editcount[$page_key]= 1;
    $logs[$page_key]= 1;
    #$editors[$page_key]= $parts[4];
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
    $addr= $DBInfo->mask_hostname ? _mask_hostname($parts[1]):$parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $log= stripslashes($parts[5]);
    $act= rtrim($parts[6]);

//    if ($ed_time < $time_cutoff)
//      break;

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

    $day = gmdate('Y-m-d', $ed_time+$tz_offset);
    if ($use_day and $day != $ratchet_day) {
      $tag=str_replace('-','',$day);
      $perma="<a name='$tag'></a><a class='perma' href='#$tag'>$perma_icon</a>";
      $out.=$cat0;
      $rcdate=gmdate($date_fmt,$ed_time+$tz_offset);

      $out.=sprintf("%s<span class='rc-date' style='font-size:large'>%s ",
            $br, $rcdate);
      if (!$nobookmark)
        $out.="<span class='rc-bookmark' style='font-size:small'>[".
          $formatter->link_tag($formatter->page->urlname,"?action=bookmark&amp;time=$ed_time".$daysago,
          _("set bookmark"))."]</span>\n";
      $ratchet_day = $day;
      $br="<br />";
      $out.='</span>'.$perma.'<br />'.$bra;
      $cat0=$cat;
    } else
      $day=$formatter->link_to("?action=bookmark&amp;time=$ed_time".$daysago,$day);

    $pageurl=_rawurlencode($page_name);

    #print $ed_time."/".$bookmark."//";
    if (!$DBInfo->hasPage($page_name))
      $icon= $formatter->link_tag($pageurl,"?action=info",$formatter->icon['del']);
    else if ($ed_time > $bookmark) {
      $icon= $formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon['updated']);
      if ($checknew) {
        $p= new WikiPage($page_name);
        $v= $p->get_rev($bookmark);
        if (!$v)
          $icon=
            $formatter->link_tag($pageurl,"?action=info",$formatter->icon['new']);
      }
    } else
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon['diff']);

    #$title= preg_replace("/((?<=[a-z0-9])[A-Z][a-z0-9])/"," \\1",$page_name);
    $title= get_title($title).$group;
    $title=htmlspecialchars($title);
    $title= $formatter->link_tag($pageurl,"",$title,$target);

    if (! empty($DBInfo->changed_time_fmt)) {
      $date= gmdate($DBInfo->changed_time_fmt, $ed_time+$tz_offset);
      if ($timesago) {
        $time_diff=(int)($time_current - $ed_time)/60;
        if ($time_diff < 1440) {
          $date=sprintf(_("[%sh %sm ago]"),(int)($time_diff/60),$time_diff%60);
        }
      }
    }

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
      $extra="&nbsp; &nbsp; &nbsp; <small>$log</small>";

    eval($template);

    $logs[$page_key]= 1;
  }
  return $btnlist.$out.$cat0;
}
// vim:et:sts=2:
?>
