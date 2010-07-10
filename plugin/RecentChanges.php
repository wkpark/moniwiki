<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RecentChanges plugin for the MoniWiki
//
// $Id$

function do_RecentChanges($formatter,$options='') {
  global $DBInfo;
  if (!empty($options['moztab'])) {
    $options['trail']='';
    $options['css_url']=$formatter->url_prefix.'/css/sidebar.css';
    $arg = 'nobookmark,moztab';
    $formatter->send_header('',$options);
    echo "<div id='wikiBody'>";
    echo macro_RecentChanges($formatter, $arg, array('target'=>'_content'));
    echo "</div></body></html>";
    return;
  } else if (!empty($DBInfo->rc_options)) {
    $arg = $DBInfo->rc_options;
  } else {
    $arg = 'board,comment,timesago,item=20';
  }
  $formatter->send_header('',$options);
  $formatter->send_title('', '', $options);
  echo macro_RecentChanges($formatter, $arg, $options);
  $formatter->send_footer('',$options);
  return;
}

function _timesago($timestamp, $date_fmt='Y-m-d', $tz_offset = 0) {
	// FIXME use $sys_datafmt ?
	$time_current = time();
	$diff=(int)($time_current - $timestamp);

	if ($diff < 0) {
		$ago = gmdate( $date_fmt, $timestamp + $tz_offset);
		return $ago;
	}
	if ($diff < 60*60 or $diff < 0) {
		$ago = sprintf(_("%d minute ago"),(int)($diff / 60), $diff % 60);
	} else if ( $diff < 60*60*24) {
		$ago = sprintf(_("%d hours ago"),(int)($diff / 60 / 60), ($diff / 60) % 60);
	} else if ( $diff < 60*60*24*7*2) {
		$ago = sprintf(_("%d days ago"),(int)($diff / 60 / 60 / 24), ($diff / 60 / 60) % 24);
	} else {
		$ago = gmdate( $date_fmt, $timestamp + $tz_offset);
	}
	return $ago;
}

function macro_RecentChanges($formatter,$value='',$options='') {
  global $DBInfo;

define('RC_MAX_DAYS',30);
define('RC_MAX_ITEMS',200);
define('RC_DEFAULT_DAYS',7);

  $checknew=1;

  $template_bra="";
  $template=
  '$out.= "$icon&nbsp;&nbsp;$title$updated $date . . . . $user $count $extra<br />\n";';
  $template_cat="";
  $use_day=1;
  $users = array();

  $target = '';
  if (!empty($options['target'])) $target="target='$options[target]'";

  // $date_fmt='D d M Y';
  $date_fmt=$DBInfo->date_fmt_rc;
  $days=!empty($DBInfo->rc_days) ? $DBInfo->rc_days:RC_DEFAULT_DAYS;
  $perma_icon=$formatter->perma_icon;
  $changed_time_fmt = $DBInfo->changed_time_fmt;

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
      else if ($arg=="comments") $comment=1;
      else if ($arg=="nobookmark") $nobookmark=1;
      else if ($arg=="noperma") $perma_icon='';
      else if ($arg=="button") $button=1;
      else if ($arg=="timesago") $timesago=1;
      else if ($arg=="hits") $use_hits=1;
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
  '$out.= "<tr><td style=\'white-space:nowrap;width:2%\'>$icon</td><td style=\'width:40%\'>$title$updated</td><td class=\'date\' style=\'width:15%\'>$date</td><td>$user $count $extra</td></tr>\n";';
        $cat="</table>";
        $cat0="";
      } else if ($arg=="board") {
        $changed_time_fmt = 'm-d [H:i]';
        $use_day=0;
        $template_bra="<table border='0' cellpadding='0' cellspacing='0' width='100%'>";
        $template_bra.="<thead><tr><th colspan='3' class='title'>"._("Title")."</th><th class='date'>".
          _("Change Date").'</th>';
        if (!empty($DBInfo->show_hosts))
          $template_bra.="<th class='author'>"._("Editor").'</th>';
        $template_bra.="<th class='editinfo'>"._("Changes").'</th>';
        if (!empty($DBInfo->use_counter))
          $template_bra.="<th class='hits'>"._("Hits")."</th>";
        $template_bra.="</tr></thead>\n<tbody>\n";
        $template=
  '$out.= "<tr$alt><td style=\'white-space:nowrap;width:2%\'>$icon</td><td class=\'title\' style=\'width:40%\'>$title$updated</td><td>$bmark</td><td class=\'date\' style=\'width:15%\'>$date</td>';
        if (!empty($DBInfo->show_hosts))
          $template.='<td class=\'author\'>$user</td>';
        $template.='<td class=\'editinfo\'>$count</td>';
        if (!empty($DBInfo->use_counter))
          $template.='<td class=\'hits\'>$hits</td>';
        $template_extra=$template.'</tr>\n<tr><td class=\'log\' colspan=\'6\'>$extra</td></tr>\n";';
        $template.='</tr>\n";';
        $template_cat="</tbody></table>";
        $cat0="";
      }
    }
  }
  // override days
  $days=!empty($_GET['days']) ? min(abs($_GET['days']),RC_MAX_DAYS):$days;

  // override ago
  empty($opts['ago']) ? $opts['ago'] = 0:null;
  $opts['ago']=!empty($_GET['ago']) ? abs($_GET['ago']):$opts['ago'];

  // daysago
  $daysago='&amp;days='.$days;
  $daysago=$opts['ago'] ? $daysago.'&amp;ago='.$opts['ago']:$daysago;
      

  $u=$DBInfo->user; # retrive user info

  if ($u->id != 'Anonymous') {
    $bookmark= !empty($u->info['bookmark']) ? $u->info['bookmark'] : '';
    $tz_offset= !empty($u->info['tz_offset']) ? $u->info['tz_offset'] : '';
  } else {
    $bookmark= $u->bookmark;
  }
  if (empty($tz_offset)) {
    $tz_offset=date("Z");
    $tz_offset;
  }

  if (!$bookmark) $bookmark=time();

  $time_current= time();
  $secs_per_day= 60*60*24;
  //$time_cutoff= $time_current - ($days * $secs_per_day);
  $lines= $DBInfo->editlog_raw_lines($days,$opts);

  // make a daysago button
  $btnlist = '';
  if (!empty($use_daysago) or !empty($_GET['ago'])) {
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

  $ratchet_day = FALSE;
  foreach ($lines as $line) {
    $parts= explode("\t", $line,6);
    $page_key= $parts[0];
    $ed_time= $parts[2];

    $day = gmdate('Ymd', $ed_time+$tz_offset);
    if ($day != $ratchet_day) {
      $ratchet_day = $day;
      unset($logs);
    }

    if (!empty($editcount[$page_key])) {
      if (!empty($logs[$page_key])) {
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
  $ii = 0;
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_key=$parts[0];

    if (!empty($logs[$page_key])) continue;

    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $DBInfo->mask_hostname ? _mask_hostname($parts[1]):$parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $log= _stripslashes($parts[5]);
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

    if (! empty($changed_time_fmt)) {
      $date= gmdate($changed_time_fmt, $ed_time+$tz_offset);
      if ($timesago) {
        $date = _timesago($ed_time, 'Y-m-d', $tz_offset);
        /*
        $time_diff=(int)($time_current - $ed_time)/60;
        if ($time_diff < 1440) {
          $date=sprintf(_("[%sh %sm ago]"),(int)($time_diff/60),$time_diff%60);
        }
        */
      }
    }

    $bmark = '';
    if ($day != $ratchet_day) {
      $ratchet_day = $day;
      if (!empty($use_day)) {
        $tag=str_replace('-','',$day);
        $perma="<a name='$tag'></a><a class='perma' href='#$tag'>$perma_icon</a>";
        $out.=$cat0;
        $rcdate=gmdate($date_fmt,$ed_time+$tz_offset);

        $out.=sprintf("%s<span class='rc-date' style='font-size:large'>%s ",
            $br, $rcdate);
        if (empty($nobookmark))
          $out.="<span class='rc-bookmark' style='font-size:small'>[".
            $formatter->link_tag($formatter->page->urlname,"?action=bookmark&amp;time=$ed_time".$daysago,
            _("set bookmark"))."]</span>\n";
        $br="<br />";
        $out.='</span>'.$perma.'<br />'.$bra;
        $cat0=$cat;
      } else {
        $bmark=$formatter->link_to("?action=bookmark&amp;time=$ed_time".$daysago,_("Bookmark"), 'class="button-small"');
      }
    }
    if (empty($use_day)) {
      $date=$formatter->link_to("?action=bookmark&amp;time=$ed_time".$daysago,$date);
    }

    $pageurl=_rawurlencode($page_name);

    #print $ed_time."/".$bookmark."//";
    $updated = '';
    if (!$DBInfo->hasPage($page_name))
      $icon= $formatter->link_tag($pageurl,"?action=info",$formatter->icon['del']);
    else if ($ed_time > $bookmark) {
      $icon= $formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon['diff']);
      $updated= ' '.$formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon['updated']);
      if ($checknew) {
        $p= new WikiPage($page_name);
        $v= $p->get_rev($bookmark);
        if (empty($v)) {
          $icon=
            $formatter->link_tag($pageurl,"?action=info",$formatter->icon['show']);
          $updated = ' '.$formatter->link_tag($pageurl,"?action=info",$formatter->icon['new']);
        }
      }
    } else
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon['diff']);

    #$title= preg_replace("/((?<=[a-z0-9])[A-Z][a-z0-9])/"," \\1",$page_name);
    $title0= get_title($title).$group;
    $title0=htmlspecialchars($title0);
    $attr = '';
    if (strlen(get_title($title)) > 20 and function_exists('mb_strimwidth')) {
      $title0=mb_strimwidth($title0,0,20,'...', $DBInfo->charset);
      $attr = ' title="'.$title.'"';
    }
    $title= $formatter->link_tag($pageurl,"",$title0,$target.$attr);

    if (!empty($use_hits)) {
      $hits = $DBInfo->counter->pageCounter($page_name);
    }

    if (!empty($DBInfo->show_hosts)) {
      if (!empty($showhost) && $user == 'Anonymous')
        $user= $addr;
      else {
        $ouser= $user;
        if (isset($users[$ouser])) $user = $users[$ouser];
        else if (!empty($DBInfo->use_nick)) {
          $uid = $user;
          if (($p = strpos($uid,' '))!==false)
            $uid= substr($uid, 0, $p);
          $u = $DBInfo->udb->getUser($uid);
          if (!empty($u->info)) {
            if (!empty($DBInfo->interwiki['User'])) {
              $user = $formatter->link_repl('[wiki:User:'.$uid.' '.$u->info['nick'].']');
            } else if (!empty($u->info['home'])) {
              $user = $formatter->link_repl('['.$u->info['home'].' '.$u->info['nick'].']');
            } else if (!empty($u->info['nick'])) {
              $user = $formatter->link_repl('[wiki:'.$uid.' '.$u->info['nick'].']');
            }
          }
          $users[$ouser] = $user;
        } else if (strpos($user,' ')!==false) {
          $user= $formatter->link_repl($user);
          $users[$ouser] = $user;
        } else if ($DBInfo->hasPage($user)) {
          $user= $formatter->link_tag(_rawurlencode($user),"",$user);
          $users[$ouser] = $user;
        } else
          $user= $user;
      }
    } else {
      $user = '&nbsp;';
    }
    $count=""; $extra="";
    if ($editcount[$page_key] > 1)
      $count=sprintf(_("%s changes"), " <span class='num'>".$editcount[$page_key]."</span>");
    if ($comment && $log)
      $extra="&nbsp; &nbsp; &nbsp; <small name='word-break'>$log</small>";

    $alt = ($ii % 2 == 0) ? ' class="alt"':'';
    if ($extra and isset($template_extra)) {
      eval($template_extra);
    } else {
      eval($template);
    }

    $logs[$page_key]= 1;
    ++$ii;
  }
  $title = "<h2>"._("Recent Changes")."</h2>";
  return $btnlist.'<div class="recentChanges">'.$title.$template_bra.$out.$template_cat.$cat0.'</div>';
}
// vim:et:sts=2:
?>
