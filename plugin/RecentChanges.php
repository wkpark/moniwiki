<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RecentChanges plugin for the MoniWiki
//
// Since: 2003-08-09
// Name: RecentChanges
// Description: Show RecentChanges of the Wiki
// URL: MoniWiki:RecentChangesPlugin
// Version: $Revision: 1.65 $
// Depend: 1.1.3
// License: GPL
// $Id: RecentChanges.php,v 1.65 2011/10/07 14:10:29 wkpark Exp $

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

/**
 * get updated info
 *
 */

function ajax_RecentChanges($formatter, $options = array()) {
  global $DBInfo;

  // list style
  if (!empty($options['type']) and $options['type'] == 'list') {
    $options['call'] = 1;
    $opt = 'list';
    if (!empty($options['item'])) $opt.=',item='.$options['item'];
    $out = macro_RecentChanges($formatter, $opt, $options);
    echo $out;
    return;
  }

  if (empty($options['value'])) {
    echo '[]';
    return;
  }

  $checknew = 0;
  $checkchange = 0;
  if (!empty($options['new'])) $checknew = 1;
  if (!empty($options['change'])) $checkchange = 1;

  require_once('lib/JSON.php');
  $json = new Services_JSON();
  $rclist = $json->decode($options['value']);
  if (!is_array($rclist)) {
    echo '[]';
    return;
  }

  // get bookmark parameter and call bookmark macro
  if (!empty($options['time'])) {
    if (is_numeric($options['time']) and $options['time'] > 0) {
      $formatter->macro_repl('Bookmark', '', $options);
      //$bookmark = $options['time'];
    }
  }

  $u = $DBInfo->user; # retrive user info

  if ($u->id != 'Anonymous') {
    $bookmark = !empty($u->info['bookmark']) ? $u->info['bookmark'] : '';
  } else {
    $bookmark = $u->bookmark;
  }

  if (!$bookmark) $bookmark = time();

  $tz_offset=$formatter->tz_offset;

  $info = array();
  foreach ($rclist as $page_name) {
    $p= new WikiPage($page_name);
    if (!$p->exists()) {
      $info[$page_name]['state'] = 'deleted';
      continue; // XXX
    }

    $ed_time = $p->mtime();
    if ($ed_time <= $bookmark) break;

    $info[$page_name]['state'] = 'updated';

    $add = 0;
    $del = 0;

    if ($checknew or $checkchange) {
      $v= $p->get_rev($bookmark);
      if (empty($v)) {
        $info[$page_name]['state'] = 'new';
        $add+= $p->lines();
      }
    }

    if ($checkchange) {
      if (empty($v)) // new
        $infos = array();
      else
        $infos = $p->get_info('>'.$bookmark);
      foreach ($infos as $inf) {
        $tmp = explode(' ', trim($inf[1]));
        if (isset($tmp[1])) {
          $add+= $tmp[0];
          $del+= $tmp[1];
        }
      }

      $info[$page_name]['add'] = $add;
      $info[$page_name]['del'] = $del;
    }
  }
  $info['__-_-bookmark-_-__'] = $bookmark;

  echo $json->encode($info);
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
  '"$icon&nbsp;&nbsp;$title$updated $date . . . . $user $count$diff $extra<br />\n"';
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
  if (preg_match("/^[\s\/\-:aABdDFgGhHiIjmMOrSTY\[\]]+$/",$args[0]))
    $my_date_fmt=$args[0];

  $strimwidth = isset($DBInfo->rc_strimwidth) ? $DBInfo->rc_strimwidth : 20;
  // use javascript
  $use_js = 0;
  // show last edit entry only
  $last_entry_only = 1;
  $last_entry_check = 60*60*24;
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
      $k=trim(substr($arg,0,$p));
      $v=trim(substr($arg,$p+1));
      if ($k=='item' or $k=='items') $opts['items']=min((int)$v,RC_MAX_ITEMS);
      else if ($k=='days') $days=min(abs($v),RC_MAX_DAYS);
      else if ($k=="datefmt") $my_date_fmt=$v;
      else if ($k=='ago') {
        if (is_numeric($v) and $v == abs($v))
          $opts['ago'] = abs($v);
        else
          $opts['from'] = $v;
      }
      else if ($k=="new") $checknew=$v;
      else if ($k=='strimwidth' and is_numeric($v) and (abs($v) > 15 or $v == 0))
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
      else if ($arg=="noavatar") $use_avatar = 0;
      else if ($arg=="js") $use_js = 1;
      else if ($arg=="diffwidth") $use_diffwidth = 1;
      else if (in_array($arg, array('simple', 'moztab', 'board', 'table', 'list'))) $rctype = $arg;
    }
  }

  // set as dynamic macro or not.
  if ($formatter->_macrocache and empty($options['call'])) // and empty($use_js))
    return $formatter->macro_cache_repl('RecentChanges', $value);
  $formatter->_dynamic_macros['@RecentChanges'] = 1;

  if (empty($DBInfo->interwiki)) $formatter->macro_repl('InterWiki','',array('init'=>1));

  // reset some conflict params
  if (empty($DBInfo->use_counter))
    $use_hits = 0;
  if (empty($DBInfo->show_hosts))
    $showhost = 0;

  if (!empty($rctype)) {
      if ($rctype=="simple") {
        $checkchange = 0;
        $use_day=0;
        if ($showhost)
          $template=
  '"$icon&nbsp;&nbsp;$title @ $day $date by $user $count $extra<br />\n"';
        else
          $template=
  '"$icon&nbsp;&nbsp;$title @ $day $date $count $extra<br />\n"';
      } else if ($rctype=="list") {
        $rctitle='';
        $changed_time_fmt = !empty($my_date_fmt) ? $my_date_fmt : '[H:i]';
        $checkchange = 0;
        $use_day=0;
        $template= '"$date $title<br />\n"';
      } else if ($rctype=="moztab") {
        $use_day=1;
        $template= '"<li>$title $date</li>\n"';
      } else if ($rctype=="table") {
        $bra="<table border='0' cellpadding='0' cellspacing='0' width='100%'>";
        $template=
  '"<tr><td style=\'white-space:nowrap;width:2%\'>$icon</td><td style=\'width:40%\'>$title$updated</td><td class=\'date\' style=\'width:15%\'>$date</td><td>$user $count$diff $extra</td></tr>\n"';
        $cat="</table>";
        $cat0="";
      } else if ($rctype=="board") {
        $changed_time_fmt = !empty($my_date_fmt) ? $my_date_fmt : 'm-d [H:i]';
        $use_day=0;
        $template_bra="<table border='0' cellpadding='0' cellspacing='0' width='100%'>";

        if (empty($nobookmark)) $cols = 3;
        else $cols = 2;

        $template_bra.="<thead><tr><th colspan='$cols' class='title'>"._("Title")."</th>";
        if (!empty($showhost))
          $template_bra.="<th class='author'>"._("Editor").'</th>';
        $template_bra.="<th class='editinfo'>"._("Changes").'</th>';
        if (!empty($use_hits))
          $template_bra.="<th class='hits'>"._("Hits")."</th>";
        $template_bra.="<th class='date'>"._("Change Date").'</th>';
        $template_bra.="</tr></thead>\n<tbody>\n";
        $template=
  '"<tr$alt><td style=\'white-space:nowrap;width:2%\'>$icon</td><td class=\'title\' style=\'width:40%\'>$title$updated</td>';
        if (empty($nobookmark))
          $template.= '<td>$bmark</td>';
        if (!empty($showhost))
          $template.='<td class=\'author\'>$user</td>';
        $template.='<td class=\'editinfo\'>$count';
        if (!empty($checkchange) or !empty($checknew)) $template.=' $diff';
        $template.='</td>';
        if (!empty($use_hits))
          $template.='<td class=\'hits\'>$hits</td>';
        $template.= '<td class=\'date\'>$date</td>';
        $template_extra=$template.'</tr>\n<tr class=\'log\'$style><td colspan=\'6\'><div>$extra</div></td></tr>\n"';
        $template.='</tr>\n"';
        $template_cat="</tbody></table>";
        $cat0="";
      }
  }

  // override days
  $days=!empty($_GET['days']) ? min(abs($_GET['days']),RC_MAX_DAYS):$days;

  // override ago
  empty($opts['ago']) ? $opts['ago'] = 0:null;
  if (!empty($_GET['ago']) and is_numeric($_GET['ago']))
    $opts['ago'] = abs($_GET['ago']);
  else
    $opts['from'] = $_GET['ago'];

  // override times
  // accept both 'item' or 'items'
  $tmp = isset($_GET['item']) ? $_GET['item'] :
        (isset($_GET['items']) ? $_GET['items'] : null);
  !empty($tmp) ? $opts['items'] = min(abs($tmp), RC_MAX_ITEMS):null;
  unset($tmp);

  // daysago
  $daysago='&amp;days='.$days;
  $daysago=$opts['ago'] ? $daysago.'&amp;ago='.$opts['ago']:$daysago;
      

  $u=$DBInfo->user; # retrive user info

  if ($u->id != 'Anonymous') {
    $bookmark= !empty($u->info['bookmark']) ? $u->info['bookmark'] : '';
  } else {
    $bookmark= $u->bookmark;
  }
  $tz_offset=$formatter->tz_offset;

  if (!$bookmark or !empty($nobookmark)) {
    if (!empty($checknew) and preg_match('/^\d+(\s*\*\s*\d+)*$/',$checknew))
      $checknew = eval('return '.$checknew. ';');

    if ($checknew > 1)
      $bookmark = strtotime(date('Y-m-d', time() - $checknew).' 00:00:00');
  }
  if (!$bookmark) $bookmark = time();
  if (!empty($nobookmark)) $use_js = 0;

  // set search query
  if (isset($_GET['q'][0])) {
    $query = _preg_search_escape(trim($_GET['q']));
    if (@preg_match('/'.$query.'/', '') === false)
      unset($query);
  }

  // make rclog uniq key
  $locals = get_defined_vars();
  unset($locals['bookmark']);
  unset($locals['formatter']);
  unset($locals['options']);
  unset($locals['DBInfo']);
  unset($locals['args']);
  unset($locals['arg']);
  unset($locals['u']);
  unset($locals['k']);
  unset($locals['v']);
  $rckey = md5(serialize($locals));
  unset($locals);

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

  $rc = new Cache_text('rclogs');

  $ratchet_day = FALSE;
  $editors = array();
  $editcount = array();
  $rc_delay = 0; //60*2;

  $rctimestamp = 0;
  $needupdate = false;
  if (empty($formatter->refresh) and $DBInfo->checkUpdated($rc->mtime($rckey), $rc_delay) and ($val = $rc->fetch($rckey))) {
    $editors = $val['editors'];
    $editcount = $val['editcount'];
    $lastmod = $val['lastmod'];
    $rclastline = $val['lastline'];
    $rctimestamp = $val['timestamp'];
    $users = $val['users'];
  }
  $lastline = $lines[0];
  $tmp = explode("\t", $lastline, 6);
  $timestamp = $tmp[2];
  unset($tmp);
  $updatemod = array();

  $needupdate = $rctimestamp < $timestamp or $lastline != $rclastline;
  if ($needupdate)
  foreach ($lines as $line) {
    $parts= explode("\t", $line,6);
    if ($lastline == $rclastline) break;
    $page_key= $parts[0];
    $ed_time= $parts[2];
    $user= $parts[4];
    $addr= $parts[1];
    if ($user == 'Anonymous')
      $user = 'Anonymous-' . $addr;
    else
      $user = $user . "\t" . $addr;

    $day = gmdate('Ymd', $ed_time+$tz_offset);
    //if ($day != $ratchet_day) {
    //  $ratchet_day = $day;
    //}

    if ($last_entry_only and !empty($last_entry_check)) {
      if (!empty($lastmod[$page_key]) and $lastmod[$page_key] < $ed_time + $last_entry_check) {
        $edit_day = gmdate('Ymd', $lastmod[$page_key] + $tz_offset);
        $editors[$page_key][$edit_day][] = $user;
        $editcount[$page_key][$edit_day]++;
        if ($needupdate and empty($updatemod[$page_key])) $updatemod[$page_key] = $ed_time;
        continue;
      }
    } else if (!empty($editcount[$page_key][$day])) {
      $editors[$page_key][$day][] = $user;
      $editcount[$page_key][$day]++;
      if ($needupdate and empty($updatemod[$page_key])) $updatemod[$page_key] = $ed_time;
      continue;
    }
    if (empty($editcount[$page_key])) {
      $editcount[$page_key] = array();
      $editors[$page_key] = array();
    }

    $editcount[$page_key][$day]= 1;

    $editors[$page_key][$day] = array();
    $editors[$page_key][$day][] = $user;
    $lastmod[$page_key] = $ed_time;
    if ($needupdate) $updatemod[$page_key] = $ed_time;
  }

  if (!empty($lastmod))
    $lastmod = array_merge($lastmod, $updatemod);

  // search query
  if (isset($query[0])) {
    $lines = preg_grep("/$query/i", $lines);
  }

  $out="";
  $ratchet_day= FALSE;
  $br="";
  $ii = 0;
  $rc_list = array();
  $list = array();
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

    $addr= $parts[1];
    $user= $parts[4];
    $log= _stripslashes($parts[5]);
    $act= rtrim($parts[6]);

//    if ($ed_time < $time_cutoff)
//      break;

    $group = '';
    if ($formatter->group) {
      if (!preg_match("/^($formatter->group)(.*)$/",$page_name,$match)) continue;
      $title=$match[2];
    } else {
      if (!empty($formatter->use_group) and ($p = strpos($page_name,'~')) !== false) {
        $title=substr($page_name,$p+1);
        $group=' ('.substr($page_name,0,$p).')';
      } else
        $title=$page_name;
    }

    if (! empty($changed_time_fmt)) {
      if (empty($timesago)) {
        $date= gmdate($changed_time_fmt, $ed_time+$tz_offset);
      } else {
        $date = _timesago($ed_time, 'Y-m-d', $tz_offset);
      }
    }

    $pageurl=_rawurlencode($page_name);
    // get title
    $title0= get_title($title).$group;
    $title0=_html_escape($title0);

    if ($list) $attr = '';
    else $attr = " id='title-$ii'";
    if (!empty($strimwidth) and strlen(get_title($title)) > $strimwidth and function_exists('mb_strimwidth')) {
      $title0=mb_strimwidth($title0,0, $strimwidth,'...', $DBInfo->charset);
    }
    $attr.= ' title="'.$title0.'"';
    $title= $formatter->link_tag($pageurl,"",$title0,$target.$attr);

    // simple list format
    if ($rctype == 'list') {
      if (empty($logs[$page_key]))
        $logs[$page_key] = array();
      $logs[$page_key][$day] = 1;

      if (!$DBInfo->hasPage($page_name)) $act = 'DELETE';
      $list[$page_name] = array($title, $date, $act);
      continue;
    }

    $jsattr = '';
    if (!empty($use_js))
      $jsattr = ' onclick="update_bookmark('.$ed_time.');return false;"';
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
            _("set bookmark"), $jsattr)."]</span>\n";
        $br="<br />";
        $out.='</span>'.$perma.'<br />'.$bra;
        $cat0=$cat;
      } else {
        $bmark=$formatter->link_to($bookmark_action ."&amp;time=$ed_time".$daysago,_("Bookmark"),
          $jsattr.' class="button-small"');
      }
    }
    //if (empty($use_day) and empty($nobookmark)) {
    if (empty($nobookmark)) {
      $date=$formatter->link_to($bookmark_action ."&amp;time=$ed_time".$daysago,$date, ' id="time-'.$ii.'" '.$jsattr);
    }

    // print $ed_time."/".$bookmark."//";
    $diff = '';
    $updated = '';

    if ($act == 'UPLOAD') {
      $icon= $formatter->link_tag($pageurl,"?action=uploadedfiles",$formatter->icon['attach']);
    } else if (!$DBInfo->hasPage($page_name)) {
      $icon= $formatter->link_tag($pageurl,"?action=info",$formatter->icon['del']);
      if (!empty($use_js))
        $rc_list[] = $page_name;
    } else {
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon['diff'], " id='icon-$ii'");

      if (empty($use_js) and $ed_time > $bookmark) {
        $icon= $formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon['diff']);
        $updated= ' '.$formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon['updated'], 'class="updated"');

        $add = 0;
        $del = 0;
        if ($checknew or $checkchange) {
          $p = new WikiPage($page_name);
          $v= $p->get_rev($bookmark);
          if (empty($v)) {
            $icon=
              $formatter->link_tag($pageurl,"?action=info",$formatter->icon['show']);
            $updated = ' '.$formatter->link_tag($pageurl,"?action=info",$formatter->icon['new'], 'class="new"');
            $add+= $p->lines();
          }
        }
        if ($checkchange) {
          if (empty($v)) // new
            $infos = array();
          else
            $infos = $p->get_info('>'.$bookmark);
          foreach ($infos as $inf) {
            $tmp = explode(' ', trim($inf[1]));
            if (isset($tmp[1])) {
              $add+= $tmp[0];
              $del+= $tmp[1];
            }
          }
        }

        if (!empty($add))
          $diff.= '<span class="diff-added"><span>+'.$add.'</span></span>';
        if (!empty($del))
          $diff.= '<span class="diff-removed"><span>'.$del.'</span></span>';
      } else if (!empty($use_js)) {
        $diff = '<span id="diff-'.$ii.'"></span>';
        $rc_list[] = $page_name;
      }
    }

    if (!empty($use_hits)) {
      $hits = $DBInfo->counter->pageCounter($page_name);
    }

    if (!empty($showhost)) {
      $last_editor = $user;

      if ($last_editor_only) {
        // show last editor only
        $editor = $editors[$page_key][$day];
        if (is_array($editor)) $editor = $editor[count($editor) - 1];
      } else {
        // all show all authors
        // count edit number
        // make range list
        if ($use_editrange) { // MoinMoin like edit range
          $editor_list = array();
          if ($editors[$page_key][$day])
          foreach ($editors[$page_key][$day] as $idx=>$name) {
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
          $editor_counts = array_count_values($editors[$page_key][$day]);
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
          $ouser = $user;
          if (isset($users[$ouser])) $user = $users[$ouser];
          else {
          $checkaddr = null;
          $addr = null;
          $tmp = $user;
          if (strpos($user, "\t") !== false)
            list($tmp, $addr) = explode("\t", $user);

          $checkaddr = substr($tmp, 10); // Anonymous-127.0.0.1 or Anonymous-email@foo.bar
          $user = $addr ? $addr : $checkaddr;
          if (!is_numeric($checkaddr[0]) and preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i', $user)) {
            $user = $checkaddr;
            if (!empty($DBInfo->hide_emails))
              $user = substr(md5($user), 0, 8); // FIXME
            else
              $user = email_guard($user);
          } else if (!empty($DBInfo->mask_hostname))
            $user = _mask_hostname($user);

          if (!empty($use_avatar)) {
            $crypted = md5($addr . $rckey);
            $mylnk = preg_replace('/seed=/', 'seed='.$crypted, $avatarlink);
            $user = '<img src="'.$mylnk.'" style="width:16px;height:16px;vertical-align:middle" alt="avatar" />'. _('Anonymous');
          }
          $users[$ouser] = $user;
          }
        } else {
          list($user, $addr) = explode("\t", $user);
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
          } else if (empty($DBInfo->no_wikihomepage) and $DBInfo->hasPage($user)) {
            $user= $formatter->link_tag(_rawurlencode($user),"",$user);
            $users[$ouser] = $user;
          } else {
            if (substr($user, 0, 9) == 'Anonymous') {
              $addr = substr($user, 10);
              $user = _('Anonymous');
            }
            if (preg_match('/^[a-z][a-z0-9_\-\.]+@[a-z][a-z0-9_\-]+(\.[a-z0-9_]+)+$/i', $user)) {
              if (!empty($DBInfo->hide_emails))
                $user = substr(md5($user), 0, 8); // FIXME
              else
                $user = email_guard($user);
            }

            if (!empty($use_avatar)) {
              $crypted = crypt($addr, $addr);
              $mylnk = preg_replace('/seed=/', 'seed='.$crypted, $avatarlink);
              $user = '<img src="'.$mylnk.'" style="width:16px;height:16px;vertical-align:middle" alt="avatar" />'.$user;
            }
            $users[$ouser] = $user;
          }
        }
        $all_user[] = $user.$count;
      }
      if (isset($editor[1]))
        $user = '<span class="rc-editors"><span class="editor">'.implode("</span> <span class='editor'>", $all_user)."</span></span>\n";
      else
        $user = $all_user[0];
    } else {
      $user = '&nbsp;';
    }
    $count=""; $extra="";
    if ($editcount[$page_key][$day] > 1)
      $count = '<span id="change-'.$ii.'">'.sprintf(_("%s changes"), " <span class='num'>".$editcount[$page_key][$day]."</span>").'</span>';
    else
      $count = '<span id="change-'.$ii.'"></span>';
    if (!empty($comment) && !empty($log))
      $extra="&nbsp; &nbsp; &nbsp; <small name='word-break'>$log</small>";

    $alt = ($ii % 2 == 0) ? ' class="alt"':'';
    if ($extra and isset($template_extra)) {
      if ($rctype == 'board' and !empty($use_js))
        $style = ' style="display:none"';
      else
        $style = '';

      if (!empty($use_js))
        $title = '<button onclick="toggle_log(this);return false;"><span>+</span></button>' . $title;
      $out.= eval('return '.$template_extra.';');
    } else {
      $out.= eval('return '.$template.';');
    }

    if (empty($logs[$page_key]))
      $logs[$page_key] = array();
    $logs[$page_key][$day] = 1;
    ++$ii;
  }

  if ($needupdate)
    $rc->update($rckey, array(
          'editors'=>$editors,
          'editcount'=>$editcount,
          'lastmod'=>$lastmod,
          'lastline'=>$lastline,
          'timestamp'=>$timestamp,
          'users'=>$users));

  $js = '';
  if (!empty($rc_list)) {
    require_once('lib/JSON.php');
    $json = new Services_JSON();

    $icon_new = $formatter->icon['new'];
    $icon_updated = $formatter->icon['updated'];
    $icon_show = $formatter->icon['show'];
    $icon_diff = $formatter->icon['diff'];

    $js = "<script type='text/javascript'>\n/*<![CDATA[*/\nvar rclist =";
    $ext = array();
    if (!empty($checknew)) $ext[] = 'new=1';
    if (!empty($checkchange)) $ext[] = 'change=1';
    $arg = implode('&', $ext);
    $url = qualifiedURL($formatter->link_url('RecentChanges')); // FIXME
    $postdata = "action=recentchanges/ajax" . ($arg ? '&'.$arg : '');
    $js.= $json->encode($rc_list).";\n";
    if ($use_diffwidth)
      $js.= "var use_diffwidth = true;\n";
    else
      $js.= "var use_diffwidth = false;\n";
    $js.= <<<EOF
function diff_width(size) {
    if (size < 0)
        size = -size;
    if (size < 5)
      return '';
    else if (size < 10)
      return 'display:inline-block;width:25px';
    else
      return 'display:inline-block;width:' + ~~(25 + 2*Math.sqrt(size)) + 'px';
}

function update_bookmark(time) {
    var url = "$url";
    if (rclist.length) {
      var timetag;
      if (typeof time == 'undefined') timetag = '';
      else timetag = '&time=' + time;

      var data = "$postdata";
      data += timetag + '&value=' + encodeURIComponent(json_encode(rclist));
      var txt = HTTPPost(url, data);
      var ret;
      if (txt == null) return;

      var icon_new = "$icon_new";
      var icon_updated = "$icon_updated";
      var icon_show = "$icon_show";
      var icon_diff = "$icon_diff";

      ret = window["eval"]("(" + txt + ")");
      var bookmark = ret['__-_-bookmark-_-__'];
      var jj = 0;
      for (var ii = 0; ii < rclist.length; ii++) {
        // update time
        var time = document.getElementById('time-' + ii);
        var tstr = time.firstChild.innerText;
        var d0 = Date.parse(tstr); // test
        if (isNaN(d0)) {
          // recalc time string
          var timestamp = time.href.match(/time=(\d+)/);
          tstr = timesago(timestamp[1], "$date_fmt", $tz_offset);
          if (tstr != null)
            time.firstChild.innerText = tstr;
        }

        var item = document.getElementById('title-' + ii);
        var title = item.getAttribute('title');
        if (rclist[jj] != title) {
          var re = new RegExp("^.*" + url_prefix + '/');
          title = decodeURIComponent(item.href.replace(re, ''));
        }

        if (ret[title] && ret[title]['state'] == 'deleted') { jj++; continue; }

        if (rclist[jj] == title && ret[title]) {
          var icon = document.getElementById('icon-' + ii);
          var state = document.createElement('SPAN');
          if (ret[title]['state'] == 'new') {
            state.innerHTML = icon_new;
            state.setAttribute('class', 'new');
            icon.href = icon.href.replace(/action=(diff|info)((?:&|&amp;)date=\d+)?/, 'action=info');
            icon.innerHTML = icon_show;
          } else {
            state.innerHTML = icon_updated;
            state.setAttribute('class', 'updated');
            icon.href = icon.href.replace(/action=(diff|info)((?:&|&amp;)date=\d+)?/, 'action=diff&date=' + bookmark);
            icon.innerHTML = icon_diff;
          }

          // remove previous icon
          if (item.firstChild.nextSibling)
            item.removeChild(item.firstChild.nextSibling);
          item.appendChild(state); // add updated / new icon

          var change = document.getElementById('change-' + ii);
          if (!change) continue;
          var diff = document.getElementById('diff-' + ii);
          var nodiff = !diff;

          // remove previous diff info
          if (change.lastChild && change.lastChild.tagName == 'SPAN')
            change.removeChild(change.lastChild);
          else if (diff && diff.lastChild)
            diff.removeChild(diff.lastChild);

          // add diff info
          var diff0 = document.createElement('SPAN');
          if (ret[title]['add']) {
            var add = document.createElement('SPAN');
            var add2 = document.createElement('SPAN');
            add.setAttribute('class', 'diff-added');
            var txt = document.createTextNode('+' + ret[title]['add']);
            add2.appendChild(txt);
            add.appendChild(add2);
            diff0.appendChild(add);
            if (use_diffwidth)
            add.style.cssText = diff_width(ret[title]['add']);
          }
          if (ret[title]['del']) {
            var del = document.createElement('SPAN');
            var del2 = document.createElement('SPAN');
            del.setAttribute('class', 'diff-removed');
            var txt = document.createTextNode(ret[title]['del']);
            del2.appendChild(txt);
            del.appendChild(del2);
            diff0.appendChild(del);
            if (use_diffwidth)
            del.style.cssText = diff_width(ret[title]['del']);
          }
          if (nodiff)
            change.appendChild(diff0);
          else
            diff.appendChild(diff0);
          jj++;
        } else {
          if (item.firstChild.nextSibling)
            item.removeChild(item.firstChild.nextSibling);

          var change = document.getElementById('change-' + ii);
          if (!change) continue;
          var diff = document.getElementById('diff-' + ii);

          // remove diff info
          if (change.lastChild && change.lastChild.tagName == 'SPAN')
            change.removeChild(change.lastChild);
          else if (diff && diff.lastChild)
            diff.removeChild(diff.lastChild);

          // recover diff icon and link
          var icon = document.getElementById('icon-' + ii);
          if (icon && icon.firstChild) {
            var alt = icon.firstChild.getAttribute('alt');
            if (alt != 'D' && alt != '@') {
              icon.innerHTML = icon_diff;
            }
            // recover link
            icon.href = icon.href.replace(/action=(diff|info)(&date=\d+)?/, 'action=diff');
          }
        }
      }
    }
}
update_bookmark();
/*]]>*/
</script>
EOF;

  } else if (!empty($list)) {
    $out = '';
    foreach ($list as $k=>$v) {
      $out.= $v[1].' '.$v[0].'<br />';
    }

    if (!empty($options['call'])) {
      return $out;
    }
  }

  if (in_array($rctype, array('list', 'simple')) and $use_js) {
    static $rc_id = 1;

    $rcid = ' id="rc'.$rc_id.'"';

    $extra = '';
    if (!empty($opts['items']))
      $extra.= '&item='.$opts['items'];
    
    $url = $formatter->link_url('RecentChanges', "?action=recentchanges/ajax&type=$rctype".$extra);
    $js = <<<JS
<script type='text/javascript'>
/*<![CDATA[*/
  var url = "$url";
  var txt = HTTPGet(url);
  var rc = document.getElementById("rc$rc_id");
  rc.innerHTML = txt;
/*]]>*/
</script>
JS;
    $rc_id++;
  } else if ($use_js and $rctype == 'board') {
    $js.= <<<JS
<script type='text/javascript'>
/*<![CDATA[*/
function toggle_log(el)
{
  var item = el.parentNode.parentNode; // container
  var log = item.nextSibling;
  if (log.tagName == undefined)
    log = log.nextSibling; // for IE6

  if (log.style.display == "none") {
    el.className = "close";
    log.style.display = "";
  } else {
    el.className = "open";
    log.style.display = "none";
  }
}
/*]]>*/
</script>
JS;
  }

  return $btnlist.'<div class="recentChanges"'. $rcid .'>'.$rctitle.$template_bra.$out.$template_cat.$cat0.'</div>'.$js;
}
// vim:et:sts=2:sw=2:
?>
