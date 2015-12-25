<?php
// Copyright 2003-2009 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Info plugin for the MoniWiki
//
// $Id: Info.php,v 1.34 2011/10/07 00:53:11 wkpark Exp $

function _parse_rlog($formatter,$log,$options=array()) {
  global $DBInfo;

  $tz_offset=$formatter->tz_offset;
  if (!empty($DBInfo->wikimasters) and is_array($DBInfo->wikimasters) and in_array($options['id'],$DBInfo->wikimasters)) $admin=1;

  if (!empty($options['info_actions']))
    $actions=$options['info_actions'];
  else if (isset($DBInfo->info_actions))
    $actions=$DBInfo->info_actions;
  else
    $actions=array('recall'=>'view','raw'=>'source','diff'=>'diff');
  if (!$formatter->page->exists() && !in_array('revert', $actions)) {
    $actions['revert'] = 'revert';
  }
  if (!empty($DBInfo->use_avatar)) {
    if (is_string($DBInfo->use_avatar))
      $type = $DBInfo->use_avatar;
    else
      $type = 'identicon';
    $avatarlink = qualifiedUrl($formatter->link_url('', '?action='. $type .'&amp;seed='));
  }
  $ipicon = '<img src="'.$DBInfo->imgs_dir.'/misc/ip.png" />';

  $members = $DBInfo->members;
  // do not check admin member users
  $user = $DBInfo->user;
  $ismember = $user->is_member;

  $diff_action = null;
  if (isset($actions['diff'])) {
    $diff_action = _($actions['diff']);
    unset($actions['diff']);
  }

  // extra query string
  $extra = '';
  if (!empty($options['archive'])) {
    $archive = intval($options['archive']);
    $extra = '&amp;archive='.$archive;
  }

  $state=0;
  $flag=0;

  $time_current=time();

  $simple=!empty($options['simple']) ? 1:0;

  $url=$formatter->link_url($formatter->page->urlname);

  $diff_btn=_("Compare");
  $out = "<div class='wikiInfo'>\n";
  if (!empty($options['title']))
    $out.=$options['title'];
  else
    $out.="<h2>"._("Revision History")."</h2>\n";
  $out.="<form id='infoform' method='get' action='$url'>";
  $out.="<div><table class='info'><thead><tr>\n";
  $out.="<th>"._("Ver.")."</th><th>"._("Date")."</th>".
       "<th>"._("Changes")."</th>".
       "<th>"._("Editor")."</th>";
  if (!$simple)
    $out.= "<th><button type='submit'><span>$diff_btn</span></button></th>";
  $out.= "\n";
  if (!$simple) {
    if (!empty($actions))
      $out.="<th>"._("View")."</th>";
    if (isset($admin)) $out.= "<th>"._("admin.")."</th>";
  }
  $out.= "</tr>\n</thead>\n";

  $out.= "<tbody>\n";
  $users=array();
  $rr=0;
 
  #foreach ($lines as $line) {
  $count=0;
  $showcount=(!empty($options['count']) and $options['count']>5) ? $options['count']: 10;
  $line = '';
  $ok = 0;
  $log.="\n"; // hack
  $ii = 0;
  for(; !empty($line) or !empty($log); list($line,$log) = explode("\n",$log,2)) {
    if (!$state) {
      if (!preg_match("/^---/",$line)) { continue;}
      else {$state=1; continue;}
    }
    if ($state==1 and $ok==1) {
      if (!empty($options['action']))
        $act = $options['action'];
      else
        $act = 'info';
      if (empty($options['logonly'])) {
        $lnk=$formatter->link_to('?action='.$act.$extra.'&amp;rev='.$rev,_("Show next revisions"),' class="button small"');
        $out.='<tr><td colspan="2"></td><td colspan="'.(!empty($admin) ? 5:4).'">'.$lnk.'</td></tr>';
      }
      break;
    }
    
    switch($state) {
      case 1:
         $rr++;
         preg_match("/^revision ([0-9a-f\.]+)\s*/",$line,$match);
         $rev=$match[1];
         if (isset($match[2]) and preg_match("/\./",$match[2])) {
            $state=0;
            break;
         }
         $state=2;
         break;
      case 2:
         $change = '';
         $inf=preg_replace("/date:\s([0-9\/:\s]+)(;\s+author:.*;\s+state:.*;)?/","\\1",$line);
         if (strstr($inf, 'lines:') !== FALSE)
           list($inf,$change)=explode('lines:',$inf,2);

         if (!empty($options['ago'])) {
           if (preg_match('/^[0-9]+$/',$inf)) {
             $rrev='#'.$rr;
             $ed_time=$inf;
             $inf=gmdate("Y-m-d H:i:s",$ed_time+$tz_offset);
           } else {
             $ed_time=strtotime($inf.' GMT');
           }
           $time_diff=(int)($time_current - $ed_time)/60;
           if ($time_diff > 1440*31) {
             $inf=gmdate("Y-m-d H:i:s",strtotime($inf.' GMT')+$tz_offset);
           } else if (($time_diff=$time_diff/60) > 24) {
             $day=(int)($time_diff/24);
             if ($day==1) $inf=_("Yesterday");
             else $inf=sprintf(_("%s days ago"),(int)($time_diff/24));
           } else if ($time_diff > 1) {
             $inf=sprintf(_("%s hours ago"),(int)$time_diff);
           } else {
             $inf=sprintf(_("%s min ago"),$time_diff%60);
           }

         } else {
           if (preg_match('/^[0-9]+$/',$inf)) {
             $rrev='#'.$rr;
             $ed_time=$inf;
             $inf=gmdate("Y-m-d H:i:s",$inf+$tz_offset);
           } else {
             if ($tz_offset !='')
               $inf=gmdate("Y-m-d H:i:s",strtotime($inf.' GMT')+$tz_offset);
             else
               $inf=date("Y-m-d H:i:s",strtotime($inf)); // localtime
           }
         }
         if (!$simple)
         $inf=$formatter->link_to("?action=recall&amp;rev=$rev".$extra,$inf);

         $change=preg_replace("/\+(\d+)\s\-(\d+)/",
           "<span class='diff-added'><span>+\\1</span></span><span class='diff-removed'><span>-\\2</span></span>",$change);
         $state=3;
         break;
      case 3:
         $dummy=explode(';;',$line,3);
         $ip=$dummy[0];
         $realip = $lastip = $ip;
         if (($p = strpos($ip, ',')) !== false) {
           // IP addresses via proxies
           $realip = substr($ip, 0, $p);
           $tmp = explode(',', $ip);
           $lastip = $ip = array_pop($tmp);
         }
         $user=trim($dummy[1]);
         if (($p = strpos($user,' ')) !== false) { // XXX
           $user = substr($user, 0, $p);
         } else if (substr($user, 0, 9) == 'Anonymous') {
           $user = 'Anonymous';
         }

         if (!empty($DBInfo->use_avatar)) {
           $crypted = crypt($ip, $ip);
           $mylnk = preg_replace('/seed=/', 'seed='.$crypted, $avatarlink);
           $avatar = '<img src="'.$mylnk.'" style="width:16px;height:16px;vertical-align:middle" alt="avatar" />';
         } else {
           $avatar = '';
         }

         if ($user and $user!='Anonymous') {
           if (array_key_exists($user, $users)) $ip = $users[$user];
           else if (!empty($DBInfo->use_nick)) {
             $u = $DBInfo->udb->getUser($user);
             if (!empty($u->info['nick'])) {
               if ($DBInfo->interwiki['User']) {
                 $ip=$formatter->link_repl('[wiki:User:'.$user.' '.$u->info['nick'].']');
               } else if (!empty($u->info['home'])) {
                 $ip=$formatter->link_repl('['.$u->info['home'].' '.$u->info['nick'].']');
               } else {
                 $ip=$formatter->link_repl('[wiki:'.$user.' '.$u->info['nick'].']');
               }
             }
             $ip = $avatar.$ip;
             $users[$user]=$ip;
           } else if (strpos($user,' ') !== false) {
             $ip = $avatar. $formatter->link_repl($user);
             $users[$user] = $ip;
           } else if (empty($DBInfo->no_wikihomepage)) {
             if (empty($DBInfo->use_hostname) or $DBInfo->hasPage($user))
               $ip = $avatar.$formatter->link_tag($user);
             else
               $ip = $avatar.$user;
             $users[$user] = $ip;
           }
         }

         if (empty($users[$user])) {
           $rip = $ip;
           $u = $user;
           if ($u == 'Anonymous')
             $u = $ip;
           if (isset($DBInfo->interwiki['Whois']))
             $wip = "<a href='".$DBInfo->interwiki['Whois']."$ip' target='_blank'>$ipicon</a>";
           else
             $wip = "<a href='?action=whois&amp;q=".$ip."' target='_blank'>$ipicon</a>";

           if ($ismember) {
             if (!empty($DBInfo->use_admin_user_url))
               $u = '<a href="'.$DBInfo->use_admin_user_url.$u.'">'.$u.'</a>';
             if (!in_array($user, $members))
               $ip = $u.$wip;
             else
               $ip = $u;
           } else if (empty($DBInfo->show_hosts)) {
             $ip = $user;
           } else {
             if (!empty($DBInfo->mask_hostname)) {
               if ($user == 'Anonymous')
                 $ip = _mask_hostname($ip);
               else
                 $ip = $user;
             } else {
               if (!empty($members) and in_array($u, $members)) {
                 $ip = $user;
               } else if (!empty($avatar)) {
                 if ($user == 'Anonymous')
                   $u = _($user);
                 $ip = $u;
               } else {
                 $ip = $u.$wip;
               }
             }
           }
           $ip = $avatar.$ip;

           if ($user != 'Anonymous')
             $users[$user] = $ip;
           else
             $users[$rip] = $ip;
         }

         $comment=!empty($dummy[2]) ? _stripslashes($dummy[2]) : '';
         if ($realip != $lastip) {
           $via = '<span class="via-proxy">'.$realip.'</span>';
           $comment = isset($comment[0]) ? $via.' '.$comment : $via;
         }
         $state=4;
         break;
      case 4:
         if (!$rev) break;
         $rowspan=1;
         if (!$simple and $comment) $rowspan=2;

         $rrev= !empty($rrev) ? $rrev:$formatter->link_to("?action=recall&amp;rev=$rev".$extra,$rev);
         $alt = ($ii++ % 2 == 0) ? ' class="alt"' : '';
         $out.="<tr$alt>\n";
         $out.="<th class='rev' valign='top' rowspan=$rowspan>$rrev</th><td nowrap='nowrap' class='date'>$inf</td><td class='change'>$change</td><td class='author'>$ip&nbsp;</td>";
         $rrev='';

         if (!$simple):
         $achecked="";
         $bchecked="";
         if ($flag==1)
            $achecked="checked ";
         else if (!$flag)
            $bchecked="checked ";
         $onclick="onclick='ToggleRev(this)'";
         $out.="<th nowrap='nowrap' class='check'><input type='radio' name='rev' value='$rev' $achecked $onclick />\n";
         $out.="<input type='radio' name='rev2' value='$rev' $bchecked $onclick /></th>";

         $out.="<td nowrap='nowrap' class='view'>";
         foreach ($actions as $k=>$v) {
           $k=is_numeric($k) ? $v:$k;
           $out.=$formatter->link_to("?action=$k&amp;rev=$rev".$extra,_($v), ' class="button-small"').' ';
         }
         if ($flag) {
            if ($diff_action)
              $out.= " ".$formatter->link_to("?action=diff&amp;rev=$rev".$extra,$diff_action, ' class="button-small"');
            $out.="</td>";
            if (isset($admin))
              $out.=
                "<td><input type='checkbox' name='range[$flag]' value='$rev' /></td>";
         } else {
            $out.="</td>";
            if (isset($admin)) {
              $out.="<td><input type='image' src='$DBInfo->imgs_dir/smile/checkmark.png' onClick=\"ToggleAll('infoform');return false;\"/></td>";
            }
         }
         endif;
         $out.="</tr>\n";
         if (isset($comment[0]))
            $out.="<tr class='log'><td colspan='".(!empty($admin) ? 6:5). "'><p>$comment&nbsp;</p></td></tr>\n";
         $state=1;
         $flag++;
         $count++;
         if ((empty($options['all']) or $options['all'] != 1) and $count >=$showcount) $ok=1;
         break;
     }
  }
  if (!$simple and !empty($admin)):
  $out.="<tr><td colspan='".(!empty($admin) ? 7:6)."' align='right'><input type='checkbox' name='show' checked='checked' />"._("show only").' ';
  if ($DBInfo->security->is_protected("rcspurge",$options)) {
    $out.="<input type='password' name='passwd'>";
  }
  $out.="<input type='submit' name='rcspurge' value='"._("purge")."'></td></tr>";
  endif;
  $out.="<input type='hidden' name='action' value='diff'/>\n</tbody></table></div>";
  if ($archive) {
    $out.= "<input type='hidden' name='archive' value='".$archive."' />";
  }
  $out.="</form>\n";
  $out.="<script type='text/javascript' src='$DBInfo->url_prefix/local/checkbox.js'></script></div>\n";
  return $out; 
}

function macro_Info($formatter, $value = '', $options=array()) {
  global $DBInfo;

  if (empty($DBInfo->interwiki)) $formatter->macro_repl('InterWiki','',array('init'=>1));

  $value=(empty($value) and !empty($DBInfo->info_options)) ? $DBInfo->info_options : $value;
  $args=explode(',',$value);
  if (is_array($args)) {
    if (isset($args[0][0]) && $DBInfo->hasPage($args[0])) {
      $pagename = $args[0];
      array_shift($args);
    }
    foreach ($args as $arg) {
      $arg=trim($arg);
      if ($arg=='simple') $options['simple']=1;
      else if ($arg=='ago') $options['ago']=1;
    }
  }
  $pagename = isset($pagename) ? $pagename : $formatter->page->name;

  $warn = '';
  $archived = null;
  if ($DBInfo->version_class) {
    $version = $DBInfo->lazyLoad('version', $DBInfo);

    // setup suffix, rlog extra argments
    $args = array('-z');
    if (!empty($options['archive'])) {
      $archive = intval($options['archive']);
      $args['archive'] = $archive;
      $archived = $archive;
    }

    $out = $version->rlog($pagename, '', '-r', $args);
  } else {
    $msg=_("Version info is not available in this wiki");
    return "<h2>$msg</h2>";
  }

  if ($archived) {
    $options['title'] = '<h2>'.sprintf(_("Revision History. (archive number %d)"), $archived).'</h2>'."\n";
  } else if (method_exists($version, 'attics')) {
    $ret = $version->attics($pagename);
    if ($ret !== false) {
      $count = count($ret);
      if ($count > 1)
        $msg = sprintf(_("%s archived log files available."), $count);
      else
        $msg = sprintf(_("%s archived log file available."), $count);
      $info = "<h2>$msg</h2>";
      $warn .= '<div class="warn">'.$info."</div>";
    }
  }

  if (!isset($out[0])) {
    if (empty($msg)) {
      $msg = _("No older revisions available");
      $info = "<h2>$msg</h2>";
    }
  } else if (isset($out[0])) {
    // get the number of total revisions and the last revision.
    $total = 1;
    if (preg_match('/^total revisions: (\d+);/m', $out, $m))
      $total = $m[1];

    $rev = array();
    $rev0 = '';
    if (preg_match('/^revision 1.(\d+)+\s/m', $out, $m)) {
      $rev0 = $m[1];
      $rev[$rev0] = '1.'.$rev0;
    }

    if (!empty($DBInfo->rcs_check_broken) and method_exists($version, 'is_broken')) {
      $is_broken = $version->is_broken($pagename);
      if ($is_broken)
        $warn .= '<div class="warn">'._("WARNING: ")._("The history information of this page is broken.")."</div>";
    }

    // parse 'rev' query string
    $rev1 = '';
    if (!empty($options['rev']) and preg_match('/^1\.(\d+)$/', $options['rev'], $m)) {
      if ($m[1] < $rev0)
        $rev[$m[1]] = '1.'.$m[1];
    }
    $r = array_keys($rev);
    $range_max = !empty($DBInfo->info_range_max) ? $DBInfo->info_range_max : 30;
    $anon_range_limit = !empty($DBInfo->info_anonymous_range_limit) ? $DBInfo->info_anonymous_range_limit : $range_max;

    if ($options['id'] == 'Anonymous' && $r[0] - $r[1] > $anon_range_limit) {
      unset($rev[$r[1]]);
      unset($r[1]);
      $warn .= '<div class="warn">'._("WARNING: ")._("Anonymous user is not allowed to see older versions.")."</div>";
    }

    // make a range list like as "1.234:1.240\;1.110:1.140"
    $revstr = '';
    $count = 10;
    if (count($r) > 1) {
      if ($r[0] - $r[1] > $range_max) {
        $revstr.= '1.'.max($r[0] - 1, 0).':'.$rev[$r[0]];
        $revstr.= '\;1.'.max($r[1] - $count, 0).':'.$rev[$r[1]];
        $options['count'] = $count + 2;
      } else {
        $revstr.= '1.'.max($r[1] - $count, 0).':'.$rev[$r[0]];
        $options['count'] = $r[0] - $r[1] + $count;
      }
    } else {
      $revstr.= '1.'.max($r[0] - $count, 0).':'.$rev[$r[0]];
    }

    $out= $version->rlog($pagename,'',"-r$revstr", $args);
    if ($pagename != $formatter->page->name) {
      $p = $DBInfo->getPage($pagename);
      $f = new Formatter($p, $options);
    } else {
      $f = &$formatter;
    }
    $info= _parse_rlog($f, $out, $options);
  }
  return $warn.$info;
}


function do_info($formatter,$options) {
  global $DBInfo;
  $formatter->send_header("",$options);
  $formatter->send_title('','',$options);

  print macro_info($formatter,'',$options);
  $formatter->send_footer('',$options);
}

// vim:et:sts=2:
?>
