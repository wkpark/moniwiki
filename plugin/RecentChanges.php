<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RecentChanges plugin for the MoniWiki
//
// Since: 2003-08-09
// Name: RecentChanges
// Description: Show RecentChanges of the Wiki
// URL: MoniWiki:RecentChangesPlugin
// Version: $Revision$
// Depend: 1.1.3
// License: GPL
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
  if (!empty($options['time'])) {
    $ret = array();
    $options['ret'] = &$ret;
    $formatter->macro_repl('bookmark', '', $options);
    if (!empty($ret))
      $options = array_merge($options, $ret);
  }

  $formatter->send_header('',$options);
  $formatter->send_title('', '', $options);
  $options['myaction'] = 'recentchanges';
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

define('RC_MAX_DAYS',30);
define('RC_MAX_ITEMS',200);
define('RC_DEFAULT_DAYS',7);

function macro_RecentChanges($formatter,$value='',$options='') {
  global $DBInfo;

  $checknew=1;
  $checkchange=0;

  $template_bra="";
  $template=
  '$out.= "$icon&nbsp;&nbsp;$title$updated $date . . . . $user $count$diff $extra<br />\n";';
  $template_cat="";
  $use_day=1;
  $users = array();

  $target = '';
  if (!empty($options['target'])) $target="target='$options[target]'";
  $bookmark_action = empty($options['action']) ? '?action=bookmark' : '?action=' . $options['action'];

  // $date_fmt='D d M Y';
  $date_fmt=$DBInfo->date_fmt_rc;
  $days=!empty($DBInfo->rc_days) ? $DBInfo->rc_days:RC_DEFAULT_DAYS;
  $perma_icon=$formatter->perma_icon;
  $changed_time_fmt = $DBInfo->changed_time_fmt;

  $args=explode(',',$value);

  // first arg assumed to be a date fmt arg
  if (preg_match("/^[\s\/\-:aABdDFgGhHiIjmMOrSTY]+$/",$args[0]))
    $date_fmt=$args[0];

  $strimwidth = isset($DBInfo->rc_strimwidth) ? $DBInfo->rc_strimwidth : 20;
  // show last edit entry only
  $last_entry_only = 1;
  // show last editor only
  $last_editor_only = 1;
  // show editrange like as MoinMoin
  $use_editrange = 0;
  // avatar
  $use_avatar = 0;
  $avatar_type = 'identicon';
  if (!empty($DBInfo->use_avatar)) {
    $use_avatar = 1;
    if (is_string($DBInfo->use_avatar))
      $avatar_type = $DBInfo->use_avatar;
  }

  $avatarlink = qualifiedUrl($formatter->link_url('', '?action='. $avatar_type .'&amp;seed='));

  $trash = 0;
  $rctype = '';

  $bra = '';
  $cat = '';
  $cat0 = '';
  $rctitle = "<h2>"._("Recent Changes")."</h2>";
  foreach ($args as $arg) {
    $arg=trim($arg);
    if (($p=strpos($arg,'='))!==false) {
      $k=substr($arg,0,$p);
      $v=substr($arg,$p+1);
      if ($k=='item') $opts['items']=min((int)$v,RC_MAX_ITEMS);
      else if ($k=='days') $days=min(abs($v),RC_MAX_DAYS);
      else if ($k=='ago') $opts['ago']=abs($v);
      else if ($k=='strimwidth' and is_numeric($k) and (abs($v) > 15 or $v == 0))
        $strimwidth =abs($v);
    } else {
      if ($arg =="quick") $opts['quick']=1;
      else if ($arg=="nonew") $checknew=0;
      else if ($arg=="change") $checkchange=1;
      else if ($arg=="showhost") $showhost=1;
      else if ($arg=="comment") $comment=1;
      else if ($arg=="comments") $comment=1;
      else if ($arg=="nobookmark") $nobookmark=1;
      else if ($arg=="noperma") $perma_icon='';
      else if ($arg=="button") $button=1;
      else if ($arg=="timesago") $timesago=1;
      else if ($arg=="notitle") $rctitle='';
      else if ($arg=="hits") $use_hits=1;
      else if ($arg=="daysago") $use_daysago=1;
      else if ($arg=="trash") $trash = 1;
      else if ($arg=="editrange") $use_editrange = 1;
      else if ($arg=="allauthors") $last_editor_only = 0;
      else if ($arg=="allusers") $last_editor_only = 0;
      else if ($arg=="allentries") $last_entry_only = 0;
      else if ($arg=="avatar") $use_avatar = 1;
      else if (in_array($arg, array('simple', 'moztab', 'board', 'table'))) $rctype = $arg;
    }
  }

  if (empty($DBInfo->use_counter))
    $use_hits = 0;

  if (!empty($rctype)) {
      if ($rctype=="simple") {
        $checkchange = 0;
        $use_day=0;
        $template=
  '$out.= "$icon&nbsp;&nbsp;$title @ $day $date by $user $count $extra<br />\n";';
      } else if ($rctype=="moztab") {
        $use_day=1;
        $template= '$out.= "<li>$title $date</li>\n";';
      } else if ($rctype=="table") {
        $bra="<table border='0' cellpadding='0' cellspacing='0' width='100%'>";
        $template=
  '$out.= "<tr><td style=\'white-space:nowrap;width:2%\'>$icon</td><td style=\'width:40%\'>$title$updated</td><td class=\'date\' style=\'width:15%\'>$date</td><td>$user $count$diff $extra</td></tr>\n";';
        $cat="</table>";
        $cat0="";
      } else if ($rctype=="board") {
        $changed_time_fmt = 'm-d [H:i]';
        $use_day=0;
        $template_bra="<table border='0' cellpadding='0' cellspacing='0' width='100%'>";

        if (empty($nobookmark)) $cols = 3;
        else $cols = 2;

        $template_bra.="<thead><tr><th colspan='$cols' class='title'>"._("Title")."</th><th class='date'>".
          _("Change Date").'</th>';
        if (!empty($DBInfo->show_hosts))
          $template_bra.="<th class='author'>"._("Editor").'</th>';
        $template_bra.="<th class='editinfo'>"._("Changes").'</th>';
        if (!empty($DBInfo->use_counter))
          $template_bra.="<th class='hits'>"._("Hits")."</th>";
        $template_bra.="</tr></thead>\n<tbody>\n";
        $template=
  '$out.= "<tr$alt><td style=\'white-space:nowrap;width:2%\'>$icon</td><td class=\'title\' style=\'width:40%\'>$title$updated</td>';
        if (empty($nobookmark))
          $template.= '<td>$bmark</td>';
        $template.= '<td class=\'date\' style=\'width:15%\'>$date</td>';
        if (!empty($DBInfo->show_hosts))
          $template.='<td class=\'author\'>$user</td>';
        $template.='<td class=\'editinfo\'>$count';
        if (!empty($checkchange)) $template.=' $diff';
        $template.='</td>';
        if (!empty($DBInfo->use_counter))
          $template.='<td class=\'hits\'>$hits</td>';
        $template_extra=$template.'</tr>\n<tr><td class=\'log\' colspan=\'6\'>$extra</td></tr>\n";';
        $template.='</tr>\n";';
        $template_cat="</tbody></table>";
        $cat0="";
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
  $editors = array();
  $editcount = array();
  foreach ($lines as $line) {
    $parts= explode("\t", $line,6);
    $page_key= $parts[0];
    $ed_time= $parts[2];
    $user= $parts[4];
    $addr= $parts[1];
    if ($user == 'Anonymous')
      $user = 'Anonymous-' . $addr;

    $day = gmdate('Ymd', $ed_time+$tz_offset);
    if ($day != $ratchet_day) {
      $ratchet_day = $day;
    }

    if (!empty($editcount[$day][$page_key])) {
      $editors[$day][$page_key][] = $user;
      $editcount[$day][$page_key]++;
      continue;
    }
    if (empty($editcount[$day])) {
      $editcount[$day] = array();
      $editors[$day] = array();
    }

    $editcount[$day][$page_key]= 1;

    $editors[$day][$page_key] = array();
    $editors[$day][$page_key][] = $user;
  }

  $out="";
  $ratchet_day= FALSE;
  $br="";
  $ii = 0;
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_key=$parts[0];
    $ed_time = $parts[2];

    $day = gmdate('Ymd', $ed_time+$tz_offset);

    // show last edit only
    if (!empty($last_entry_only) and !empty($logs[$page_key])) continue;
    else if (!empty($logs[$page_key][$day])) continue;

    $page_name= $DBInfo->keyToPagename($parts[0]);

    // show trashed pages only
    if ($trash and $DBInfo->hasPage($page_name)) continue;

    $addr= $DBInfo->mask_hostname ? _mask_hostname($parts[1]):$parts[1];
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

    if (! empty($changed_time_fmt)) {
      $date= gmdate($changed_time_fmt, $ed_time+$tz_offset);
      if (!empty($timesago)) {
        $date = _timesago($ed_time, 'Y-m-d', $tz_offset);
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
            $formatter->link_tag($formatter->page->urlname, $bookmark_action ."&amp;time=$ed_time".$daysago,
            _("set bookmark"))."]</span>\n";
        $br="<br />";
        $out.='</span>'.$perma.'<br />'.$bra;
        $cat0=$cat;
      } else {
        $bmark=$formatter->link_to($bookmark_action ."&amp;time=$ed_time".$daysago,_("Bookmark"), 'class="button-small"');
      }
    }
    if (empty($use_day) and empty($nobookmark)) {
      $date=$formatter->link_to($bookmark_action ."&amp;time=$ed_time".$daysago,$date);
    }

    $pageurl=_rawurlencode($page_name);

    #print $ed_time."/".$bookmark."//";
    $diff = '';
    $updated = '';
    if (!$DBInfo->hasPage($page_name))
      $icon= $formatter->link_tag($pageurl,"?action=info",$formatter->icon['del']);
    else if ($ed_time > $bookmark) {
      $icon= $formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon['diff']);
      $updated= ' '.$formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon['updated']);

      if ($checknew or $checkchange)
        $p= new WikiPage($page_name);

      $add = 0;
      $del = 0;
      if ($checknew) {
        $v= $p->get_rev($bookmark);
        if (empty($v)) {
          $icon=
            $formatter->link_tag($pageurl,"?action=info",$formatter->icon['show']);
          $updated = ' '.$formatter->link_tag($pageurl,"?action=info",$formatter->icon['new']);
          $add+= $p->lines();
        }
      }
      if ($checkchange) {
        $infos = $p->get_info('>'.$bookmark);
        foreach ($infos as $inf) {
          $tmp = explode(' ', trim($inf[1]));
          if (isset($tmp[1])) {
            $add+= $tmp[0];
            $del+= $tmp[1];
          }
        }

        if (!empty($add))
          $diff.= '<span class="diff-added">+'.$add.'</span>';
        if (!empty($del))
          $diff.= '<span class="diff-removed">'.$del.'</span>';
      }

    } else
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon['diff']);

    #$title= preg_replace("/((?<=[a-z0-9])[A-Z][a-z0-9])/"," \\1",$page_name);
    $title0= get_title($title).$group;
    $title0=htmlspecialchars($title0);
    $attr = '';
    if (!empty($strimwidth) and strlen(get_title($title)) > $strimwidth and function_exists('mb_strimwidth')) {
      $title0=mb_strimwidth($title0,0, $strimwidth,'...', $DBInfo->charset);
      $attr = ' title="'.$title.'"';
    }
    $title= $formatter->link_tag($pageurl,"",$title0,$target.$attr);

    if (!empty($use_hits)) {
      $hits = $DBInfo->counter->pageCounter($page_name);
    }

    if (!empty($DBInfo->show_hosts)) {
      $last_editor = $user;

      if ($last_editor_only) {
        // show last editor only
        $editor = array_pop($editors[$day][$page_key]);
      } else {
        // all show all authors
        // count edit number
        // make range list
        if ($use_editrange) { // MoinMoin like edit range
          $editor_list = array();
          foreach ($editors[$day][$page_key] as $idx=>$name) {
            if (empty($editor_list[$name])) $editor_list[$name] = array();
            $editor_list[$name][] = $idx + 1;
          }
          $editor_counts = array();

          foreach ($editor_list as $name=>$edits) {
            $range = ',';
            if (isset($edits[1])) {
              $edits[] = 999999; // MoinMoin method
              for($i = 0, $sz = count($edits)-1; $i < $sz; $i++) {
                if (substr($range, -1) == ',') {
                  $range.= $edits[$i];
                  if ($edits[$i] + 1 == $edits[$i+1])
                    $range.= '-';
                  else
                    $range.= ',';
                } else {
                  if ($edits[$i] + 1 != $edits[$i+1])
                    $range.= $edits[$i].',';
                }
              }
              $range = trim($range, ',-');
              $editor_counts[$name] = $range;
            } else {
              $editor_counts[$name] = $edits[0];
            }
          }
        } else {
          $editor_counts = array_count_values($editors[$day][$page_key]);
        }
        $editor = array_keys($editor_counts);
      }

      $all_user = array();
      foreach ((array)$editor as $user) {
        if (!$last_editor_only and isset($editor[1]) and isset($editor_counts[$user]))
          $count = " <span class='range'>[".$editor_counts[$user]."]</span>";
        else
          $count = '';

        if (!empty($showhost) && substr($user, 0, 9) == 'Anonymous') {
          $user= $addr;
          if (!empty($use_avatar)) {
            $crypted = crypt($addr, $addr);
            $mylnk = preg_replace('/seed=/', 'seed='.$crypted, $avatarlink);
            $user = '<img src="'.$mylnk.'" style="width:16px;height:16px;vertical-align:middle" alt="avatar" />Anonymous';
          }
        } else {
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
          } else {
            if (substr($user, 0, 9) == 'Anonymous') {
              $addr = substr($user, 10);
              $user = 'Anonymous';
            }
            if (!empty($use_avatar)) {
              $crypted = crypt($addr, $addr);
              $mylnk = preg_replace('/seed=/', 'seed='.$crypted, $avatarlink);
              $user = '<img src="'.$mylnk.'" style="width:16px;height:16px;vertical-align:middle" alt="avatar" />'.$user;
            }
          }
        }
        $all_user[] = $user.$count;
      }
      if (isset($editor[1]))
        $user = '<span class="rc-editors"><span>'.implode("</span> <span>", $all_user)."</span></span>\n";
      else
        $user = $all_user[0];
    } else {
      $user = '&nbsp;';
    }
    $count=""; $extra="";
    if ($editcount[$day][$page_key] > 1)
      $count=sprintf(_("%s changes"), " <span class='num'>".$editcount[$day][$page_key]."</span>");
    if (!empty($comment) && !empty($log))
      $extra="&nbsp; &nbsp; &nbsp; <small name='word-break'>$log</small>";

    $alt = ($ii % 2 == 0) ? ' class="alt"':'';
    if ($extra and isset($template_extra)) {
      eval($template_extra);
    } else {
      eval($template);
    }

    if (empty($logs[$page_key]))
      $logs[$page_key] = array();
    $logs[$page_key][$day] = 1;
    ++$ii;
  }
  return $btnlist.'<div class="recentChanges">'.$rctitle.$template_bra.$out.$template_cat.$cat0.'</div>';
}
// vim:et:sts=2:sw=2:
?>
